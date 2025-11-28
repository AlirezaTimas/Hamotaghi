<?php
/**
 * Request Model
 */

declare(strict_types=1);

class Request extends BaseModel
{
    protected string $table = 'requests';
    protected string $primaryKey = 'id';

    /**
     * Check if user has pending request for house
     */
    public function hasPendingRequest(int $userId, int $houseId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table} 
            WHERE user_id = ? AND house_id = ? AND status = 'در انتظار'
        ");
        $stmt->execute([$userId, $houseId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Get request with house and user details
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   h.title as house_title, h.address as house_address, h.price as house_price,
                   h.available_capacity, h.capacity,
                   u.full_name as applicant_name, u.email as applicant_email, 
                   u.phone as applicant_phone, u.personality_type as applicant_personality,
                   u.user_score as applicant_score, u.avatar as applicant_avatar
            FROM {$this->table} r
            JOIN houses h ON r.house_id = h.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get requests for house
     */
    public function findByHouse(int $houseId, ?string $status = null): array
    {
        $sql = "
            SELECT r.*, 
                   u.full_name as applicant_name, u.personality_type as applicant_personality,
                   u.user_score as applicant_score, u.avatar as applicant_avatar
            FROM {$this->table} r
            JOIN users u ON r.user_id = u.id
            WHERE r.house_id = ?
        ";
        
        $params = [$houseId];
        
        if ($status !== null) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get requests by user
     */
    public function findByUser(int $userId, ?string $status = null): array
    {
        $sql = "
            SELECT r.*, 
                   h.title as house_title, h.address as house_address, h.price as house_price,
                   h.city as house_city, h.province as house_province,
                   u.full_name as owner_name, u.avatar as owner_avatar
            FROM {$this->table} r
            JOIN houses h ON r.house_id = h.id
            JOIN users u ON h.user_id = u.id
            WHERE r.user_id = ?
        ";
        
        $params = [$userId];
        
        if ($status !== null) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Update status
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get statistics for user
     */
    public function getStats(int $userId, string $userType): array
    {
        if ($userType === 'صاحب‌خانه') {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'در انتظار' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'تایید شده' THEN 1 ELSE 0 END) as accepted_requests,
                    SUM(CASE WHEN status = 'رد شده' THEN 1 ELSE 0 END) as rejected_requests
                FROM {$this->table}
                WHERE house_id IN (SELECT id FROM houses WHERE user_id = ?)
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'در انتظار' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'تایید شده' THEN 1 ELSE 0 END) as accepted_requests,
                    SUM(CASE WHEN status = 'رد شده' THEN 1 ELSE 0 END) as rejected_requests,
                    SUM(CASE WHEN status = 'لغو شده' THEN 1 ELSE 0 END) as cancelled_requests
                FROM {$this->table}
                WHERE user_id = ?
            ");
        }
        
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }
}

