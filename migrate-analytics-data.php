<?php
require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance()->getConnection();

echo "Starting analytics data migration...\n";

try {
    // 1. Migrate Page Views
    echo "Migrating Page Views...\n";
    $countViews = $db->exec("
        INSERT INTO page_analytics (link_page_id, ip_address, viewed_at) 
        SELECT link_page_id, ip_address, created_at 
        FROM page_views
    ");
    echo "Migrated $countViews page views.\n";
    
    // 2. Migrate Link Clicks
    echo "Migrating Link Clicks...\n";
    $countClicks = $db->exec("
        INSERT INTO link_analytics (link_id, ip_address, clicked_at) 
        SELECT link_id, ip_address, created_at 
        FROM link_clicks
    ");
    echo "Migrated $countClicks link clicks.\n";

    echo "\nMigration successful! You can now safely remove the old tables if you wish.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
