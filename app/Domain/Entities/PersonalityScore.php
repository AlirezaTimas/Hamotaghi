<?php

namespace App\Domain\Entities;

class PersonalityScore
{
    public int $userId;
    public float $openness;
    public float $conscientiousness;
    public float $extraversion;
    public float $agreeableness;
    public float $neuroticism;
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
            'user_id' => $this->userId,
            'openness' => $this->openness,
            'conscientiousness' => $this->conscientiousness,
            'extraversion' => $this->extraversion,
            'agreeableness' => $this->agreeableness,
            'neuroticism' => $this->neuroticism,
            'created_at' => $this->createdAt,
        ];
    }

    public function toVector(): array
    {
        return [
            $this->openness,
            $this->conscientiousness,
            $this->extraversion,
            $this->agreeableness,
            $this->neuroticism,
        ];
    }
}

