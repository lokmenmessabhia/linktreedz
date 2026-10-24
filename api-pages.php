<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'page-manager.php';
require_once 'link-manager.php';
require_once 'design-manager.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ob_start();

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$pageManager = new PageManager();

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $tokenId = $data['token_id'] ?? null;
        $profileName = $data['profile_name'] ?? 'My Links';
        
        if (!$tokenId) {
            throw new Exception('Token ID is required');
        }
        
        // 1. Create the link page
        $linkManager = new LinkManager();
        $linkPageId = $linkManager->createLinkPage($tokenId, $profileName);
        
        if (!$linkPageId) {
            throw new Exception('Failed to create link page');
        }
        
        // 2. Initialize default design
        $designManager = new DesignManager();
        $designManager->initializeDefaultSettings($linkPageId);
        
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'link_page_id' => $linkPageId
        ]);

    } elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        
        if (!$id) {
            throw new Exception('Page ID is required');
        }
        
        $success = $pageManager->deletePage($id);
        
        ob_end_clean();
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete page']);
        }
    } else {
        throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
