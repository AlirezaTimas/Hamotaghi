<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\Message;

interface IMessageRepository
{
    public function findByRoomId(int $roomId, ?string $since = null): array;
    public function create(array $data): Message;
    public function markAsRead(int $messageId, int $userId): bool;
}

