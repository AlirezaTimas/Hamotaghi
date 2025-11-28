<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\PersonalityScore;

interface IPersonalityScoreRepository
{
    public function findByUserId(int $userId): ?PersonalityScore;
    public function create(array $data): PersonalityScore;
    public function update(int $userId, array $data): PersonalityScore;
}

