<?php

namespace App\Domain\Entities;

class Listing
{
    public ?int $id = null;
    public int $ownerId;
    public string $title;
    public ?string $description = null;
    public int $price;
    public string $city;
    public ?string $address = null;
    public int $roomCount = 1;
    public ?string $availableFrom = null;
    public string $genderAllowed = 'any';
    public ?array $rules = null;
    public ?array $images = null;
    public string $status = 'active';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

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
                if ($property === 'rules' || $property === 'images') {
                    $this->$property = is_string($value) ? json_decode($value, true) : $value;
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
            'owner_id' => $this->ownerId,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'city' => $this->city,
            'address' => $this->address,
            'room_count' => $this->roomCount,
            'available_from' => $this->availableFrom,
            'gender_allowed' => $this->genderAllowed,
            'rules' => $this->rules,
            'images' => $this->images,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}

