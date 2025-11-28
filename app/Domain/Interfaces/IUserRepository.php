<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\User;

interface IUserRepository
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByPhone(string $phone): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
}

