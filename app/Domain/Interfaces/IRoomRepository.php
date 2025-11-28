<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\Room;

interface IRoomRepository
{
    public function findById(int $id): ?Room;
    public function findByMatchId(int $matchId): ?Room;
    public function create(array $data): Room;
}

