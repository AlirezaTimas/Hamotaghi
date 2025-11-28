<?php

namespace App\Domain\Entities;

class Profile
{
    public int $userId;
    public ?int $age = null;
    public ?string $job = null;
    public ?string $city = null;
    public ?int $budgetMin = null;
    public ?int $budgetMax = null;
    public ?bool $smoker = null;
    public ?bool $pets = null;
    public ?string $sleepTimeStart = null;
    public ?string $sleepTimeEnd = null;
    public ?int $cleanliness = null;
    public bool $completed = false;

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
            'age' => $this->age,
            'job' => $this->job,
            'city' => $this->city,
            'budget_min' => $this->budgetMin,
            'budget_max' => $this->budgetMax,
            'smoker' => $this->smoker,
            'pets' => $this->pets,
            'sleep_time_start' => $this->sleepTimeStart,
            'sleep_time_end' => $this->sleepTimeEnd,
            'cleanliness' => $this->cleanliness,
            'completed' => $this->completed,
        ];
    }
}

