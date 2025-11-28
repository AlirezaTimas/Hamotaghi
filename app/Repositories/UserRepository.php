<?php

namespace App\Repositories;

use App\Domain\Entities\User;
use App\Domain\Interfaces\IUserRepository;

class UserRepository implements IUserRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = \Database::getInstance();
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $data ? new User($data) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $data ? new User($data) : null;
    }

    public function findByPhone(string $phone): ?User
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $data ? new User($data) : null;
    }

    public function create(array $data): User
    {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO users (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        $data['id'] = (int)$this->db->lastInsertId();
        return new User($data);
    }

    public function update(int $id, array $data): User
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        return $this->findById($id);
    }
}

