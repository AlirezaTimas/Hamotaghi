<?php
/**
 * Notification Model
 */

declare(strict_types=1);

class Notification extends BaseModel
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'id';

    /**
     * Create notification
     */
    public function createNotification(int $userId, string $title, string $message, string $type = 'info', ?int $relatedId = null): int
    {
        return $this->create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'related_id' => $relatedId,
            'is_read' => 0
        ]);
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, bool $unreadOnly = false, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark as read
     */
    public function markAsRead(int $id): bool
    {
        return $this->update($id, ['is_read' => 1]);
    }

    /**
     * Mark all as read
     */
    public function markAllAsRead(int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET is_read = 1
            WHERE user_id = ? AND is_read = 0
        ");
        return $stmt->execute([$userId]);
    }
}

