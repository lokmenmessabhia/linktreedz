<?php
/**
 * API: Order Assets & Settings Management
 * Handles price updates and gallery image management (upload/delete).
 */

header('Content-Type: application/json');
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'security.php';

// Security: Require Admin Auth
$auth = new Auth();
$auth->requireAuth();
Security::verifyCSRF();

$db = Database::getInstance()->getConnection();
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? '';

try {
    if ($action === 'update_prices') {
        $settings = [
            'order_old_price' => $data['old_price'] ?? '3500',
            'order_new_price' => $data['new_price'] ?? '2000',
            'price_pack_all' => $data['price_all'] ?? '2000',
            'price_pack_4' => $data['price_4'] ?? '2900',
            'order_hero_subtitle_ar' => $data['subtitle_ar'] ?? '',
            'order_hero_subtitle_fr' => $data['subtitle_fr'] ?? '',
            'order_product_name_ar' => $data['product_name_ar'] ?? '',
            'order_product_name_fr' => $data['product_name_fr'] ?? '',
            'admin_contact_phone' => $data['admin_phone'] ?? ''
        ];

        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }

        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        exit;
    }

    if ($action === 'upload_gallery') {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No image uploaded or upload error');
        }

        $galleryDir = 'uploads/order_gallery/';
        if (!is_dir($galleryDir)) {
            mkdir($galleryDir, 0755, true);
        }

        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            throw new Exception('Invalid file type');
        }

        $filename = 'gallery_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $galleryDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode(['success' => true, 'message' => 'Image uploaded', 'filename' => $filename]);
        } else {
            throw new Exception('Failed to move uploaded file');
        }
        exit;
    }

    if ($action === 'delete_gallery') {
        $filename = $data['filename'] ?? '';
        if (empty($filename)) throw new Exception('Filename missing');

        // Security check: ensure path is within order_gallery
        $galleryDir = realpath('uploads/order_gallery/');
        $filePath = realpath('uploads/order_gallery/' . $filename);

        if ($filePath && strpos($filePath, $galleryDir) === 0 && file_exists($filePath)) {
            unlink($filePath);
            echo json_encode(['success' => true, 'message' => 'Image deleted']);
        } else {
            throw new Exception('Unauthorized or file not found');
        }
        exit;
    }

    if ($action === 'set_hero_image') {
        $filename = $data['filename'] ?? '';
        if (empty($filename)) throw new Exception('Filename missing');
        
        // Use a robust update/insert logic (Upsert)
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'index_hero_image'");
        $stmtCheck->execute();
        
        if ($stmtCheck->fetchColumn() > 0) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'index_hero_image'");
            $stmt->execute([$filename]);
        } else {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('index_hero_image', ?)");
            $stmt->execute([$filename]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Hero image updated successfully']);
        exit;
    }

    throw new Exception('Invalid action');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
