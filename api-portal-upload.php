<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'database.php';

if (!isset($_SESSION['portal_token_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['profile_image'])) {
    echo json_encode(['success' => false, 'error' => 'No image uploaded']);
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

$file = $_FILES['profile_image'];
$type = $_POST['upload_type'] ?? 'profile'; // 'profile' or 'background'

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error (Code: ' . $file['error'] . ')';
    error_log("Upload error: " . $errorMsg);
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// Check if file is empty
if ($file['size'] === 0) {
    error_log("Upload error: File is empty (0 bytes)");
    echo json_encode(['success' => false, 'error' => 'File is empty. Please select a valid image file.']);
    exit;
}

// Check if tmp file exists
if (!file_exists($file['tmp_name'])) {
    error_log("Upload error: Temporary file does not exist at " . $file['tmp_name']);
    echo json_encode(['success' => false, 'error' => 'Temporary file not found. Upload failed.']);
    exit;
}

// Define target directory based on type
$targetDir = $type === 'background' ? BG_UPLOAD_DIR : PFP_UPLOAD_DIR;

// Validate file content type securely
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif', 'image/bmp', 'image/tiff', 'image/svg+xml'];
$mime = '';

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
} else {
    // Fallback if finfo is not enabled on the live server
    $mime = getimagesize($file['tmp_name'])['mime'] ?? '';
}

if (!in_array($mime, $allowedMimes)) {
    error_log("Upload error: Invalid MIME type - " . $mime);
    echo json_encode(['success' => false, 'error' => 'Invalid file format. Only JPG, PNG, WebP, GIF, BMP, TIFF, and SVG are allowed. (Detected: ' . $mime . ')']);
    exit;
}

// Generate unique filename
$ext_map = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/bmp' => 'bmp', 'image/tiff' => 'tiff', 'image/svg+xml' => 'svg'];
$extension = $ext_map[$mime] ?? 'jpg';
$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$filepath = $targetDir . $filename;

// Ensure directory exists and is writable
if (!file_exists($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        error_log("Upload error: Failed to create directory - " . $targetDir);
        echo json_encode(['success' => false, 'error' => 'Server Configuration Error: Failed to create upload directory.']);
        exit;
    }
}
if (!is_writable($targetDir)) {
    error_log("Upload error: Directory not writable - " . $targetDir);
    echo json_encode(['success' => false, 'error' => 'Server Configuration Error: The upload directory is not writable. Check folder permissions (755).']);
    exit;
}

// Log file info before move
error_log("Upload info: Size=" . $file['size'] . " bytes, Tmp=" . $file['tmp_name'] . ", Target=" . $filepath);

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Verify file was actually moved and has content
    if (!file_exists($filepath) || filesize($filepath) === 0) {
        error_log("Upload error: File move succeeded but file is empty or missing at " . $filepath);
        echo json_encode(['success' => false, 'error' => 'File was uploaded but appears to be empty. Please try again.']);
        exit;
    }
    try {
        if ($type === 'background') {
            // Check if settings exist
            $stmt = $db->prepare("SELECT id FROM design_settings WHERE link_page_id = ?");
            $stmt->execute([$pageId]);
            
            if ($stmt->fetchColumn()) {
                $stmt = $db->prepare("UPDATE design_settings SET bg_image = ? WHERE link_page_id = ?");
                $stmt->execute([$filename, $pageId]);
            } else {
                $stmt = $db->prepare("INSERT INTO design_settings (link_page_id, bg_image) VALUES (?, ?)");
                $stmt->execute([$pageId, $filename]);
            }
            $url = BG_URL . $filename;
        } else {
            // Delete old profile picture if exists
            $stmt = $db->prepare("SELECT profile_image FROM link_pages WHERE id = ?");
            $stmt->execute([$pageId]);
            $oldImage = $stmt->fetchColumn();
            
            if ($oldImage && file_exists($targetDir . $oldImage)) {
                unlink($targetDir . $oldImage);
            }
            
            $stmt = $db->prepare("UPDATE link_pages SET profile_image = ? WHERE id = ?");
            $stmt->execute([$filename, $pageId]);
            $url = PFP_URL . $filename;
        }
        
        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'url' => $url
        ]);
    } catch (Exception $e) {
        // Cleaning up the uploaded file if DB update fails
        unlink($filepath);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
