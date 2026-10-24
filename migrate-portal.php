<?php
require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance()->getConnection();

echo "Starting DB migration...\n";

// Add columns
try {
    $db->exec("ALTER TABLE tokens ADD COLUMN password_hash VARCHAR(255) NULL");
    echo "Added password_hash column.\n";
} catch (Exception $e) {
    echo "password_hash column might already exist: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE tokens ADD COLUMN password_plain VARCHAR(64) NULL");
    echo "Added password_plain column.\n";
} catch (Exception $e) {
    echo "password_plain column might already exist: " . $e->getMessage() . "\n";
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS page_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_page_id INT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (link_page_id) REFERENCES link_pages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created page_views table.\n";
} catch (Exception $e) {
    echo "page_views error: " . $e->getMessage() . "\n";
}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS link_clicks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_id INT NOT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created link_clicks table.\n";
} catch (Exception $e) {
    echo "link_clicks error: " . $e->getMessage() . "\n";
}

echo "Generating passwords for existing tokens...\n";

// Function to generate a random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ23456789';
    $count = mb_strlen($chars);
    for ($i = 0, $result = ''; $i < $length; $i++) {
        $index = rand(0, $count - 1);
        $result .= mb_substr($chars, $index, 1);
    }
    return $result;
}

$stmt = $db->query("SELECT id FROM tokens WHERE password_plain IS NULL");
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateStmt = $db->prepare("UPDATE tokens SET password_plain = ?, password_hash = ? WHERE id = ?");

$count = 0;
foreach ($tokens as $token) {
    $pass = generatePassword();
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $updateStmt->execute([$pass, $hash, $token['id']]);
    $count++;
}

echo "Migration complete! Updated {$count} tokens with passwords.\n";
