<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\Match;

interface IMatchRepository
{
    public function findById(int $id): ?Match;
    public function create(array $data): Match;
    public function update(int $id, array $data): Match;
    public function findByRequesterId(int $requesterId): array;
    public function findByOwnerId(int $ownerId): array;
    public function findByListingId(int $listingId): array;
}

