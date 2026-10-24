<?php
ob_start();
error_reporting(0); // Completely suppress errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']]);
        exit;
    }
});

header('Content-Type: application/json');
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'token-generator.php';
require_once 'qr-generator.php';

// Increase execution time for large batches
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

function send_json_response($data) {
    if (ob_get_length()) ob_clean();
    echo json_encode($data);
    exit;
}

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    send_json_response(['success' => false, 'error' => 'Unauthorized']);
}

$input = json_decode(file_get_contents('php://input'), true);
$count = isset($input['count']) ? (int)$input['count'] : 0;
$refType = $input['ref_type'] ?? 'new'; // 'new' or 'existing'
$reference = $input['reference'] ?? '';

if ($count <= 0 || $count > 500) {
    send_json_response(['success' => false, 'error' => 'Invalid count (1-500)']);
}

$db = Database::getInstance()->getConnection();

try {
    if ($refType === 'new' && empty($reference)) {
        // Auto-generate reference if none provided for 'new'
        $stmt = $db->query("SELECT MAX(id) as last_id FROM tokens");
        $lastIdRes = $stmt->fetch();
        $nextId = ($lastIdRes['last_id'] ?? 0) + 1;
        $reference = str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    $tokenGen = new TokenGenerator();
    $qrGen = new QRGenerator();
    $templatePath = __DIR__ . '/card-template.png';

    $successCount = 0;
    $errors = [];

    for ($i = 0; $i < $count; $i++) {
        try {
            $token = $tokenGen->generateToken();
            $tokenId = $tokenGen->saveToken($token, $reference);
            
            if ($tokenId) {
                $qrFilename = $qrGen->generateQRCode($token);
                if ($qrFilename) {
                    // Generate default Premium Glass card
                    $cardFilename = $qrGen->overlayQROnTemplate($qrFilename, $templatePath);
                    if ($cardFilename) {
                        $qrGen->saveQRCard($tokenId, 'Premium Glass', $qrFilename, $cardFilename);
                    }

                    // Always create link page (Auto-Activation)
                    require_once 'link-manager.php';
                    $linkManager = new LinkManager();
                    
                    $stmt = $db->prepare("INSERT INTO link_pages (token_id, profile_name) VALUES (?, ?)");
                    $stmt->execute([$tokenId, 'Profile ' . $token]);
                    $linkPageId = $db->lastInsertId();

                    // Pre-populate social links if templates are selected
                    if (!empty($input['templates'])) {
                        // Add social media links based on selected platforms
                        $platformUrls = [
                            'facebook' => 'https://facebook.com/',
                            'instagram' => 'https://instagram.com/',
                            'tiktok' => 'https://tiktok.com/@',
                            'review' => 'https://www.google.com/maps/place/'
                        ];
                        
                        $order = 1;
                        foreach ($input['templates'] as $platform) {
                            if (isset($platformUrls[$platform])) {
                                $title = $platform === 'review' ? 'Review us on Google' : ucfirst($platform);
                                $linkManager->createLink($linkPageId, $title, $platformUrls[$platform], $order++);
                            }
                        }
                    }
                    
                    $successCount++;
                } else {
                    $errors[] = "QR generation failed for $token";
                }
            } else {
                $errors[] = "Failed to save token $token to DB";
            }
        } catch (Exception $e) {
            $errors[] = "Error at index $i: " . $e->getMessage();
        }
    }

    send_json_response([
        'success' => $successCount > 0,
        'total' => $count,
        'generated' => $successCount,
        'reference' => $reference,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
}
