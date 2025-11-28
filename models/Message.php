<?php
/**
 * Message Model
 */

declare(strict_types=1);

class Message extends BaseModel
{
    protected string $table = 'messages';
    protected string $primaryKey = 'id';

    /**
     * Get conversation between two users for a house
     */
    public function getConversation(int $houseId, int $userId1, int $userId2): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE house_id = ? 
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            ORDER BY created_at ASC
        ");
        $stmt->execute([$houseId, $userId1, $userId2, $userId2, $userId1]);
        return $stmt->fetchAll();
    }

    /**
     * Get contacts for user (people they have conversations with)
     */
    public function getContacts(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                CASE 
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as partner_id,
                house_id,
                MAX(created_at) as last_message_time
            FROM {$this->table}
            WHERE sender_id = ? OR receiver_id = ?
            GROUP BY partner_id, house_id
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([$userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(int $houseId, int $receiverId, int $senderId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_read = 1
            WHERE house_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = 0
        ");
        return $stmt->execute([$houseId, $receiverId, $senderId]);
    }

    /**
     * Check if users can message (have active request)
     */
    public function canMessage(int $userId, int $otherUserId, int $houseId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM requests r
            JOIN houses h ON r.house_id = h.id
            WHERE r.house_id = ?
            AND r.status IN ('در انتظار', 'تایید شده')
            AND (
                (r.user_id = ? AND h.user_id = ?)
                OR (r.user_id = ? AND h.user_id = ?)
            )
            LIMIT 1
        ");
        $stmt->execute([$houseId, $userId, $otherUserId, $otherUserId, $userId]);
        return (bool)$stmt->fetchColumn();
    }
}

