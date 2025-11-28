<?php

namespace App\Domain\Entities;

class Match
{
    public ?int $id = null;
    public int $listingId;
    public int $ownerId;
    public int $requesterId;
    public string $status = 'pending';
    public ?int $matchPercentage = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }

    public function hydrate(array $data): void
    {
        foreach ($data as $key => $value) {
            $property = $this->camelCase($key);
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    private function camelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listingId,
            'owner_id' => $this->ownerId,
            'requester_id' => $this->requesterId,
            'status' => $this->status,
            'match_percentage' => $this->matchPercentage,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }
}

