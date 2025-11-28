<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\Listing;

interface IListingRepository
{
    public function findById(int $id): ?Listing;
    public function create(array $data): Listing;
    public function update(int $id, array $data): Listing;
    public function search(array $filters, int $limit = 20, int $offset = 0): array;
    public function findByOwnerId(int $ownerId): array;
}

