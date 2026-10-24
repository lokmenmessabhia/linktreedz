<?php
// export-db.php - Database Backup Handler for Linky Dz
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$auth = new Auth();
$auth->requireAuth();

// Get database connection
$db = Database::getInstance()->getConnection();

$tables = [];
$result = $db->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$return = "-- Linky Dz Database Backup\n";
$return .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
$return .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    // Drop table if exists
    $return .= "DROP TABLE IF EXISTS `$table`;\n";
    
    // Get create table statement
    $qShow = "SHOW CREATE TABLE `$table`";
    $stmt = $db->query($qShow);
    $row2 = $stmt->fetch(PDO::FETCH_NUM);
    $return .= "\n\n" . $row2[1] . ";\n\n";
    
    // Get table data
    $qSelect = "SELECT * FROM `$table`";
    $stmt = $db->query($qSelect);
    $columnCount = $stmt->columnCount();
    
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $return .= "INSERT INTO `$table` VALUES(";
        for ($j = 0; $j < $columnCount; $j++) {
            if (isset($row[$j])) {
                // Escape strings
                $val = str_replace("\n", "\\n", addslashes($row[$j]));
                $return .= '"' . $val . '"';
            } else {
                $return .= 'NULL';
            }
            if ($j < ($columnCount - 1)) {
                $return .= ',';
            }
        }
        $return .= ");\n";
    }
    $return .= "\n\n\n";
}

$return .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Set filename: [date] backup.sql
$filename = date('Y-m-d') . ' backup.sql';

// Save local copy for extra redundancy
$backupFolder = __DIR__ . '/backups/';
if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}
file_put_contents($backupFolder . $filename, $return);

// Force Download
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $filename . "\"");

echo $return;
exit;
