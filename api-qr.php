<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'token-generator.php';
require_once 'link-manager.php';
require_once 'design-manager.php';
require_once 'qr-generator.php';

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

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $profileName = $data['profile_name'] ?? 'My Links';
        $templateName = $data['template_name'] ?? 'default';
        
        // Generate unique token
        $tokenGenerator = new TokenGenerator();
        $token = $tokenGenerator->generateToken();
        
        // Save token to database
        $tokenId = $tokenGenerator->saveToken($token);
        
        if (!$tokenId) {
            throw new Exception('Failed to save token');
        }
        
        // Create link page
        $linkManager = new LinkManager();
        $linkPageId = $linkManager->createLinkPage($tokenId, $profileName);
        
        if (!$linkPageId) {
            throw new Exception('Failed to create link page');
        }
        
        // Initialize default design settings
        $designManager = new DesignManager();
        $designManager->initializeDefaultSettings($linkPageId);
        
        // Generate QR code
        $qrGenerator = new QRGenerator();
        $qrFilename = $qrGenerator->generateQRCode($token);
        
        if (!$qrFilename) {
            throw new Exception('Failed to generate QR code');
        }
        
        // Overlay QR on template (if template exists)
        $templatePath = __DIR__ . '/card-template.png';
        $cardFilename = null;
        
        if (file_exists($templatePath)) {
            try {
                // Pass token to display it below the QR code on the generated card
                $cardFilename = $qrGenerator->overlayQROnTemplate($qrFilename, $templatePath, null, 'all_links', $token);
            } catch (Exception $e) {
                error_log("Overlay failed: " . $e->getMessage());
                $cardFilename = null; // Fallback to just QR
            }
        }
        
        // Save QR card record
        $qrGenerator->saveQRCard(
            $tokenId,
            $templateName,
            $qrFilename,
            $cardFilename ?: $qrFilename
        );
        
        $response = [
            'success' => true,
            'token' => $token,
            'link_page_id' => $linkPageId,
            'qr_code_url' => QR_RAW_URL . $qrFilename,
            'card_url' => $cardFilename ? QR_CARD_URL . $cardFilename : QR_RAW_URL . $qrFilename,
            'public_url' => BASE_URL . '/u/' . $token
        ];
        
        ob_end_clean();
        echo json_encode($response);
        
    } else {
        ob_end_clean();
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
