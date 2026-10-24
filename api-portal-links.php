<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'database.php';

if (!isset($_SESSION['portal_token_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$tokenId = $_SESSION['portal_token_id'];
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT id FROM link_pages WHERE token_id = ?");
$stmt->execute([$tokenId]);
$pageId = $stmt->fetchColumn();

if (!$pageId) {
    echo json_encode(['success' => false, 'error' => 'Page not found']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

try {
    if ($method === 'POST') {
        // Add new link
        if (empty($data['title']) || empty($data['url'])) {
            throw new Exception("Title and URL are required");
        }
        
        $stmt = $db->prepare("INSERT INTO links (link_page_id, title, url) VALUES (?, ?, ?)");
        $stmt->execute([$pageId, $data['title'], $data['url']]);
        
        echo json_encode(['success' => true]);
        
    } elseif ($method === 'PUT') {
        // Update link
        if (empty($data['id'])) {
            throw new Exception("Link ID required");
        }
        
        // Verify ownership
        $stmt = $db->prepare("SELECT id FROM links WHERE id = ? AND link_page_id = ?");
        $stmt->execute([$data['id'], $pageId]);
        if (!$stmt->fetchColumn()) throw new Exception("Unauthorized");
        
        $isActive = isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1;
        $title = $data['title'] ?? null;
        $url = $data['url'] ?? null;
        
        $query = "UPDATE links SET is_active = ?";
        $params = [$isActive];
        
        if ($title) { $query .= ", title = ?"; $params[] = $title; }
        if ($url) { $query .= ", url = ?"; $params[] = $url; }
        
        $query .= " WHERE id = ? AND link_page_id = ?";
        $params[] = $data['id'];
        $params[] = $pageId;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
        
    } elseif ($method === 'DELETE') {
        // Delete link
        if (empty($data['id'])) {
            throw new Exception("Link ID required");
        }
        
        $stmt = $db->prepare("DELETE FROM links WHERE id = ? AND link_page_id = ?");
        $stmt->execute([$data['id'], $pageId]);
        
        echo json_encode(['success' => true]);
        
    } else {
        throw new Exception("Method not allowed");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
