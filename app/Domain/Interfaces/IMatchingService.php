<?php

namespace App\Domain\Interfaces;

interface IMatchingService
{
    public function getRecommendations(int $userId, array $options = []): array;
    public function sendRequest(int $fromUserId, int $listingId, ?string $message = null): array;
    public function accept(int $matchId, int $actorId): array;
    public function reject(int $matchId, int $actorId, ?string $reason = null): bool;
    public function computeMatchScore(array $requesterProfile, array $ownerProfile, array $listing): int;
}

