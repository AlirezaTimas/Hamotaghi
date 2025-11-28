<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\Profile;

interface IProfileRepository
{
    public function findByUserId(int $userId): ?Profile;
    public function create(array $data): Profile;
    public function update(int $userId, array $data): Profile;
}

