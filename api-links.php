<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'link-manager.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ob_start();

$auth = new Auth();

// Require authentication
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$linkManager = new LinkManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get all links for a link page
            $linkPageId = $_GET['link_page_id'] ?? null;
            
            if (!$linkPageId) {
                http_response_code(400);
                echo json_encode(['error' => 'link_page_id is required']);
                exit;
            }
            
            $links = $linkManager->getLinks($linkPageId);
            echo json_encode(['success' => true, 'links' => $links]);
            break;
            
        case 'POST':
            // Create new link
            $data = json_decode(file_get_contents('php://input'), true);
            
            $linkPageId = $data['link_page_id'] ?? null;
            $title = $data['title'] ?? '';
            $url = $data['url'] ?? '';
            $priority = $data['priority'] ?? 0;
            
            if (!$linkPageId || !$title || !$url) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $linkId = $linkManager->createLink($linkPageId, $title, $url, $priority);
            
            if ($linkId) {
                echo json_encode(['success' => true, 'link_id' => $linkId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create link']);
            }
            break;
            
        case 'PUT':
            // Update link
            $data = json_decode(file_get_contents('php://input'), true);
            
            $id = $data['id'] ?? null;
            $title = $data['title'] ?? '';
            $url = $data['url'] ?? '';
            $isActive = $data['is_active'] ?? true;
            $priority = $data['priority'] ?? 0;
            
            if (!$id || !$title || !$url) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $success = $linkManager->updateLink($id, $title, $url, $isActive, $priority);
            
            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update link']);
            }
            break;
            
        case 'DELETE':
            // Delete link
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Link ID is required']);
                exit;
            }
            
            $success = $linkManager->deleteLink($id);
            
            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete link']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Flush JSON response and clean any stray output
if (ob_get_level() > 0) ob_end_flush();


