<?php

namespace App\Domain\Entities;

class User
{
    public ?int $id = null;
    public ?string $email = null;
    public ?string $phone = null;
    public string $password;
    public string $role = 'roommate';
    public ?string $gender = null;
    public bool $firstLogin = true;
    public bool $isActive = true;
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
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'gender' => $this->gender,
            'first_login' => $this->firstLogin,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isRoommate(): bool
    {
        return $this->role === 'roommate';
    }
}

