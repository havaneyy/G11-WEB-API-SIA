<?php
/**
 * services/NotificationService.php
 * Handles real-time in-app notifications for CodeCraft users.
 */
require_once __DIR__ . '/../config.php';

class NotificationService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ensureTable();
    }

    private function ensureTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('order', 'escrow', 'shipping', 'system', 'promo') NOT NULL DEFAULT 'system',
            link VARCHAR(255) DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_user (user_id),
            INDEX idx_notif_read (is_read),
            INDEX idx_notif_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->db->exec($sql);
    }

    public function create(int $userId, string $title, string $message, string $type = 'system', ?string $link = null): int {
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, title, message, type, link, is_read) 
             VALUES (?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([$userId, $title, $message, $type, $link]);
        return (int)$this->db->lastInsertId();
    }

    public function getForUser(int $userId, int $limit = 40): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'title' => $n['title'],
                'message' => $n['message'],
                'type' => $n['type'],
                'link' => $n['link'],
                'is_read' => (bool)$n['is_read'],
                'created_at' => $n['created_at'],
                'time_ago' => self::timeAgo($n['created_at'])
            ];
        }, $rows);
    }

    public function getUnreadCount(int $userId): int {
        $stmt = $this->db->prepare("SELECT count(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function markRead(int $notificationId, int $userId): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    }

    public function markAllRead(int $userId): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }

    public function delete(int $notificationId, int $userId): bool {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    }

    public static function timeAgo(string $datetime): string {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M d, Y', $time);
    }
}
