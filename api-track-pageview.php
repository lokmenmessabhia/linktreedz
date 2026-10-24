<?php
require_once 'config.php';
require_once 'database.php';

// FAST UNLOCK: config.php starts a session. We must close it instantly so concurrent views don't freeze the browser.
session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['link_page_id'])) {
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if link page exists
    $stmt = $db->prepare("SELECT id FROM link_pages WHERE id = ?");
    $stmt->execute([$data['link_page_id']]);
    if (!$stmt->fetchColumn()) exit;

    require_once 'analytics_helper.php';
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;
    
    $uaData = parseUserAgent($userAgent);
    $locationData = getLocationFromIp($ip);
    $sessionId = getAnalyticsSession();
    
    $stmt = $db->prepare("INSERT INTO page_analytics (link_page_id, ip_address, user_agent, referrer, device_type, os, browser, country, city, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['link_page_id'], 
        $ip, 
        $userAgent, 
        $referrer,
        $uaData['device_type'],
        $uaData['os'],
        $uaData['browser'],
        $locationData['country'],
        $locationData['city'],
        $sessionId
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Silently fail for analytics
}
