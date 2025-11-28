<?php
/**
 * Favorite Model
 */

declare(strict_types=1);

class Favorite extends BaseModel
{
    protected string $table = 'favorites';
    protected string $primaryKey = 'id';

    /**
     * Check if house is favorited by user
     */
    public function isFavorited(int $userId, int $houseId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE user_id = ? AND house_id = ?
        ");
        $stmt->execute([$userId, $houseId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Toggle favorite
     */
    public function toggle(int $userId, int $houseId): bool
    {
        if ($this->isFavorited($userId, $houseId)) {
            return $this->remove($userId, $houseId);
        } else {
            return $this->add($userId, $houseId);
        }
    }

    /**
     * Add favorite
     */
    public function add(int $userId, int $houseId): bool
    {
        try {
            $this->create([
                'user_id' => $userId,
                'house_id' => $houseId
            ]);
            return true;
        } catch (PDOException $e) {
            // Duplicate entry
            return false;
        }
    }

    /**
     * Remove favorite
     */
    public function remove(int $userId, int $houseId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE user_id = ? AND house_id = ?
        ");
        return $stmt->execute([$userId, $houseId]);
    }

    /**
     * Get user favorites with house details
     */
    public function getUserFavorites(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT f.id as favorite_id, f.created_at as favorited_at,
                   h.*, u.full_name as owner_name, u.personality_type as owner_personality
            FROM {$this->table} f
            JOIN houses h ON f.house_id = h.id
            JOIN users u ON h.user_id = u.id
            WHERE f.user_id = ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get favorite count for house
     */
    public function getCount(int $houseId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE house_id = ?
        ");
        $stmt->execute([$houseId]);
        return (int)$stmt->fetchColumn();
    }
}

