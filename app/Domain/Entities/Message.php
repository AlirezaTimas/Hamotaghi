<?php

namespace App\Domain\Entities;

class Message
{
    public ?int $id = null;
    public int $roomId;
    public int $fromId;
    public int $toId;
    public string $content;
    public ?string $createdAt = null;
    public ?string $readAt = null;

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
            'room_id' => $this->roomId,
            'from_id' => $this->fromId,
            'to_id' => $this->toId,
            'content' => $this->content,
            'created_at' => $this->createdAt,
            'read_at' => $this->readAt,
        ];
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}

