<?php

namespace App\Services;

use App\Domain\Interfaces\IMatchingService;
use App\Domain\Interfaces\IUserRepository;
use App\Domain\Interfaces\IProfileRepository;
use App\Domain\Interfaces\IListingRepository;
use App\Domain\Interfaces\IMatchRepository;
use App\Domain\Interfaces\IPersonalityScoreRepository;
use App\Repositories\UserRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\ListingRepository;
use App\Repositories\MatchRepository;
use App\Repositories\PersonalityScoreRepository;

class MatchingService implements IMatchingService
{
    private IUserRepository $userRepo;
    private IProfileRepository $profileRepo;
    private IListingRepository $listingRepo;
    private IMatchRepository $matchRepo;
    private IPersonalityScoreRepository $personalityRepo;

    public function __construct(
        ?IUserRepository $userRepo = null,
        ?IProfileRepository $profileRepo = null,
        ?IListingRepository $listingRepo = null,
        ?IMatchRepository $matchRepo = null,
        ?IPersonalityScoreRepository $personalityRepo = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->profileRepo = $profileRepo ?? new ProfileRepository();
        $this->listingRepo = $listingRepo ?? new ListingRepository();
        $this->matchRepo = $matchRepo ?? new MatchRepository();
        $this->personalityRepo = $personalityRepo ?? new PersonalityScoreRepository();
    }

    public function getRecommendations(int $userId, array $options = []): array
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user->isOwner()) {
            return [];
        }

        $profile = $this->profileRepo->findByUserId($userId);
        if (!$profile || !$profile->completed) {
            return [];
        }

        $filters = [
            'status' => 'active',
            'gender_allowed' => $user->gender === 'male' ? 'male' : ($user->gender === 'female' ? 'female' : 'any')
        ];

        $listings = $this->listingRepo->search($filters, 20, 0);
        $recommendations = [];

        foreach ($listings as $listing) {
            $owner = $this->userRepo->findById($listing->ownerId);
            $ownerProfile = $owner ? $this->profileRepo->findByUserId($owner->id) : null;

            $matchScore = $this->computeMatchScore(
                $profile->toArray(),
                $ownerProfile ? $ownerProfile->toArray() : [],
                $listing->toArray()
            );

            $recommendations[] = [
                'listing' => $listing->toArray(),
                'owner' => $owner ? $owner->toArray() : null,
                'match_percentage' => $matchScore
            ];
        }

        usort($recommendations, fn($a, $b) => $b['match_percentage'] <=> $a['match_percentage']);

        return $recommendations;
    }

    public function sendRequest(int $fromUserId, int $listingId, ?string $message = null): array
    {
        $listing = $this->listingRepo->findById($listingId);
        if (!$listing || !$listing->isActive()) {
            throw new \RuntimeException('Listing not found or not active');
        }

        $requester = $this->userRepo->findById($fromUserId);
        $requesterProfile = $this->profileRepo->findByUserId($fromUserId);
        $owner = $this->userRepo->findById($listing->ownerId);
        $ownerProfile = $owner ? $this->profileRepo->findByUserId($owner->id) : null;

        $matchScore = $this->computeMatchScore(
            $requesterProfile ? $requesterProfile->toArray() : [],
            $ownerProfile ? $ownerProfile->toArray() : [],
            $listing->toArray()
        );

        $match = $this->matchRepo->create([
            'listing_id' => $listingId,
            'owner_id' => $listing->ownerId,
            'requester_id' => $fromUserId,
            'status' => 'pending',
            'match_percentage' => $matchScore
        ]);

        return $match->toArray();
    }

    public function accept(int $matchId, int $actorId): array
    {
        $match = $this->matchRepo->findById($matchId);
        if (!$match || $match->ownerId !== $actorId) {
            throw new \RuntimeException('Match not found or unauthorized');
        }

        $this->matchRepo->update($matchId, ['status' => 'accepted']);

        return $this->matchRepo->findById($matchId)->toArray();
    }

    public function reject(int $matchId, int $actorId, ?string $reason = null): bool
    {
        $match = $this->matchRepo->findById($matchId);
        if (!$match || $match->ownerId !== $actorId) {
            throw new \RuntimeException('Match not found or unauthorized');
        }

        $this->matchRepo->update($matchId, ['status' => 'rejected']);
        return true;
    }

    public function computeMatchScore(array $requesterProfile, array $ownerProfile, array $listing): int
    {
        $personalityScore = $this->computePersonalityScore($requesterProfile['user_id'] ?? 0, $ownerProfile['user_id'] ?? 0);
        $lifestyleScore = $this->computeLifestyleScore($requesterProfile, $ownerProfile);
        $budgetScore = $this->computeBudgetScore($requesterProfile, $listing);
        $locationScore = $this->computeLocationScore($requesterProfile, $listing);

        $match = round(
            0.4 * $personalityScore +
            0.3 * $lifestyleScore +
            0.2 * $budgetScore +
            0.1 * $locationScore
        );

        return max(0, min(100, $match));
    }

    private function computePersonalityScore(int $requesterId, int $ownerId): float
    {
        if ($requesterId === 0 || $ownerId === 0) {
            return 50.0;
        }

        $requesterScore = $this->personalityRepo->findByUserId($requesterId);
        $ownerScore = $this->personalityRepo->findByUserId($ownerId);

        if (!$requesterScore || !$ownerScore) {
            return 50.0;
        }

        $reqVector = $requesterScore->toVector();
        $ownVector = $ownerScore->toVector();

        return $this->cosineSimilarity($reqVector, $ownVector) * 100;
    }

    private function computeLifestyleScore(array $requesterProfile, array $ownerProfile): float
    {
        $score = 0.0;
        $maxScore = 100.0;

        if (isset($requesterProfile['smoker']) && isset($ownerProfile['smoker'])) {
            if ($requesterProfile['smoker'] === $ownerProfile['smoker']) {
                $score += 25;
            }
        }

        if (isset($requesterProfile['pets']) && isset($ownerProfile['pets'])) {
            if ($requesterProfile['pets'] === $ownerProfile['pets']) {
                $score += 25;
            }
        }

        if (isset($requesterProfile['cleanliness']) && isset($ownerProfile['cleanliness'])) {
            $diff = abs($requesterProfile['cleanliness'] - $ownerProfile['cleanliness']);
            $score += max(0, 50 - ($diff * 10));
        }

        return min($maxScore, $score);
    }

    private function computeBudgetScore(array $requesterProfile, array $listing): float
    {
        if (!isset($requesterProfile['budget_min']) || !isset($requesterProfile['budget_max'])) {
            return 50.0;
        }

        $price = $listing['price'] ?? 0;
        $budgetMin = $requesterProfile['budget_min'];
        $budgetMax = $requesterProfile['budget_max'];

        if ($price >= $budgetMin && $price <= $budgetMax) {
            return 100.0;
        }

        if ($price > $budgetMax) {
            $diff = $price - $budgetMax;
            $penalty = ($diff / $budgetMax) * 100;
            return max(0, 100 - $penalty);
        }

        return 50.0;
    }

    private function computeLocationScore(array $requesterProfile, array $listing): float
    {
        $requesterCity = $requesterProfile['city'] ?? '';
        $listingCity = $listing['city'] ?? '';

        if ($requesterCity === $listingCity) {
            return 100.0;
        }

        return 0.0;
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}

