<?php
require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance()->getConnection();

echo "Starting Server Database Migration...\n<br><br>";

try {
    // 1. Structural changes - Adding new metadata columns
    echo "<b>Step 1: Updating table structures</b><br>";
    $tables = ['page_analytics', 'link_analytics'];
    
    foreach ($tables as $table) {
        $columns = [
            "ADD COLUMN device_type VARCHAR(50) NULL AFTER ip_address",
            "ADD COLUMN os VARCHAR(100) NULL AFTER device_type",
            "ADD COLUMN browser VARCHAR(100) NULL AFTER os",
            "ADD COLUMN country VARCHAR(100) NULL AFTER browser",
            "ADD COLUMN city VARCHAR(100) NULL AFTER country",
            "ADD COLUMN session_id VARCHAR(255) NULL AFTER city"
        ];
    
        foreach ($columns as $colDef) {
            try {
                $db->exec("ALTER TABLE $table $colDef");
                echo "Added column to $table: $colDef <br>";
            } catch (Exception $e) {
                // Silently skip if column already exists
                echo "<span style='color:gray'>Skipped (might already exist): $colDef</span><br>";
            }
        }
    }
    echo "<br>";

    // 2. Data Migration - Moving old stats to new tables (if needed)
    echo "<b>Step 2: Migrating existing stats</b><br>";
    
    try {
        $countViews = $db->exec("
            INSERT IGNORE INTO page_analytics (link_page_id, ip_address, viewed_at) 
            SELECT link_page_id, ip_address, created_at 
            FROM page_views
        ");
        echo "Migrated $countViews page views.<br>";
    } catch (Exception $e) {
        echo "<span style='color:gray'>Page views migration skipped/failed: " . $e->getMessage() . "</span><br>";
    }
    
    try {
        $countClicks = $db->exec("
            INSERT IGNORE INTO link_analytics (link_id, ip_address, clicked_at) 
            SELECT link_id, ip_address, created_at 
            FROM link_clicks
        ");
        echo "Migrated $countClicks link clicks.<br>";
    } catch (Exception $e) {
        echo "<span style='color:gray'>Link clicks migration skipped/failed: " . $e->getMessage() . "</span><br>";
    }

    echo "<br><b style='color:green'>Migration absolutely complete! You can delete this file from the server now.</b>\n";

} catch (Exception $e) {
    echo "<br><b style='color:red'>Migration encountered a fatal error: " . $e->getMessage() . "</b>\n";
}
