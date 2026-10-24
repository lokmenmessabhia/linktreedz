<?php
require_once 'config.php';
require_once 'database.php';
try {
    $db = Database::getInstance()->getConnection();
    $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        action VARCHAR(50) NOT NULL,
        attempt_time INT UNSIGNED NOT NULL,
        INDEX idx_ip_action (ip_address, action),
        INDEX idx_time (attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo "Table rate_limits created successfully.";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage();
}
// Keep for a moment to verify
