<?php
/**
 * House Model
 */

declare(strict_types=1);

class House extends BaseModel
{
    protected string $table = 'houses';
    protected string $primaryKey = 'id';

    /**
     * Get houses with filters
     */
    public function search(array $filters = [], string $sort = 'newest', int $limit = 12, int $offset = 0): array
    {
        $sql = "SELECT h.*, u.full_name as owner_name, u.personality_type as owner_personality,
                       (SELECT COUNT(*) FROM house_photos WHERE house_id = h.id) as photo_count
                FROM {$this->table} h
                LEFT JOIN users u ON h.user_id = u.id
                WHERE 1=1";
        
        $params = [];

        // Available capacity filter
        if (!isset($filters['include_full']) || !$filters['include_full']) {
            $sql .= " AND h.available_capacity > 0";
        }

        // Status filter
        if (!empty($filters['status'])) {
            $sql .= " AND h.status = ?";
            $params[] = $filters['status'];
        } else {
            $sql .= " AND h.status = 'فعال'";
        }

        // City filter
        if (!empty($filters['city'])) {
            $sql .= " AND h.city = ?";
            $params[] = $filters['city'];
        }

        // Province filter
        if (!empty($filters['province'])) {
            $sql .= " AND h.province = ?";
            $params[] = $filters['province'];
        }

        // Gender filter
        if (!empty($filters['gender'])) {
            $sql .= " AND h.gender = ?";
            $params[] = $filters['gender'];
        }

        // Price range
        if (!empty($filters['min_price'])) {
            $sql .= " AND h.price >= ?";
            $params[] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $sql .= " AND h.price <= ?";
            $params[] = $filters['max_price'];
        }

        // Sorting
        $orderBy = match($sort) {
            'price_low' => 'h.price ASC',
            'price_high' => 'h.price DESC',
            'newest' => 'h.created_at DESC',
            'oldest' => 'h.created_at ASC',
            default => 'h.created_at DESC'
        };
        $sql .= " ORDER BY {$orderBy}";

        // Limit and offset
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get house with owner and photos
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.full_name as owner_name, u.personality_type as owner_personality,
                   u.avatar as owner_avatar, u.bio as owner_bio
            FROM {$this->table} h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get house photos
     */
    public function getPhotos(int $houseId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM house_photos 
            WHERE house_id = ? 
            ORDER BY is_primary DESC, id ASC
        ");
        $stmt->execute([$houseId]);
        return $stmt->fetchAll();
    }

    /**
     * Get primary photo
     */
    public function getPrimaryPhoto(int $houseId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM house_photos 
            WHERE house_id = ? AND is_primary = 1 
            LIMIT 1
        ");
        $stmt->execute([$houseId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Update available capacity
     */
    public function updateCapacity(int $houseId, int $change): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table} 
            SET available_capacity = GREATEST(available_capacity + ?, 0)
            WHERE id = ?
        ");
        return $stmt->execute([$change, $houseId]);
    }

    /**
     * Get houses by owner
     */
    public function findByOwner(int $userId, array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

