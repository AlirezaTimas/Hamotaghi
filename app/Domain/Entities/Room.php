<?php

namespace App\Domain\Entities;

class Room
{
    public ?int $id = null;
    public int $matchId;
    public array $participants = [];
    public ?string $createdAt = null;

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
                if ($property === 'participants' && is_string($value)) {
                    $this->$property = json_decode($value, true) ?? [];
                } else {
                    $this->$property = $value;
                }
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
            'match_id' => $this->matchId,
            'participants' => $this->participants,
            'created_at' => $this->createdAt,
        ];
    }
}

