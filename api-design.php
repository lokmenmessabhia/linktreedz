<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'design-manager.php';

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

$designManager = new DesignManager();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get design settings
            $linkPageId = $_GET['link_page_id'] ?? null;
            
            if (!$linkPageId) {
                http_response_code(400);
                echo json_encode(['error' => 'link_page_id is required']);
                exit;
            }
            
            $settings = $designManager->getDesignSettings($linkPageId);
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'POST':
            // Update design settings
            $data = json_decode(file_get_contents('php://input'), true);
            
            $linkPageId = $data['link_page_id'] ?? null;
            $settings = $data['settings'] ?? [];
            $profileName = $data['profile_name'] ?? null;
            $phoneNumber = $data['phone_number'] ?? null;
            $reference = $data['reference'] ?? null;
            
            if (!$linkPageId) {
                http_response_code(400);
                echo json_encode(['error' => 'link_page_id is required']);
                exit;
            }
            
            // 1. Update Profile Name if provided
            if ($profileName !== null) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("UPDATE link_pages SET profile_name = ? WHERE id = ?");
                $stmt->execute([$profileName, $linkPageId]);
            }

            // 2. Update Phone Number if provided
            if ($phoneNumber !== null) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("UPDATE link_pages SET phone_number = ? WHERE id = ?");
                $stmt->execute([$phoneNumber, $linkPageId]);
            }

            // 3. Update Token Reference if provided
            if ($reference !== null) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("UPDATE tokens t JOIN link_pages lp ON t.id = lp.token_id SET t.reference = ? WHERE lp.id = ?");
                $stmt->execute([$reference, $linkPageId]);
            }
            
            $success = $designManager->updateDesignSettings($linkPageId, $settings);
            
            if ($success) {
                $response = ['success' => true];
            } else {
                http_response_code(500);
                $response = ['error' => 'Failed to update design settings'];
            }
            break;
            
        default:
            http_response_code(405);
            $response = ['error' => 'Method not allowed'];
            break;
    }
    
    ob_end_clean();
    echo json_encode($response ?? ['error' => 'Unknown error']);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
