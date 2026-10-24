<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$linkPageId = $_POST['link_page_id'] ?? null;
$uploadType = $_POST['upload_type'] ?? 'profile'; // 'profile' or 'background'

// Debug: Log the upload attempt
error_log("Upload check: ID=$linkPageId, Type=$uploadType");

if (!$linkPageId) {
    http_response_code(400);
    die(json_encode(['error' => 'Link page ID required']));
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Image upload failed']));
}

$file = $_FILES['profile_image'];

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
    http_response_code(400);
    die(json_encode(['error' => $errorMsg]));
}

// Check if file is empty
if ($file['size'] === 0) {
    error_log("Upload error: File is empty (0 bytes)");
    http_response_code(400);
    die(json_encode(['error' => 'File is empty. Please select a valid image file.']));
}

// Check if tmp file exists
if (!file_exists($file['tmp_name'])) {
    error_log("Upload error: Temporary file does not exist at " . $file['tmp_name']);
    http_response_code(400);
    die(json_encode(['error' => 'Temporary file not found. Upload failed.']));
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif', 'image/bmp', 'image/tiff', 'image/svg+xml'];

// Verify MIME type with finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMimes)) {
    error_log("Upload error: Invalid MIME type - " . $mime);
    http_response_code(400);
    die(json_encode(['error' => 'Invalid file content. Only JPG, PNG, WebP, GIF, BMP, TIFF, and SVG are allowed.']));
}

$ext_map = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/bmp' => 'bmp', 'image/tiff' => 'tiff', 'image/svg+xml' => 'svg'];
$ext = $ext_map[$mime];
$prefix = ($uploadType === 'background') ? 'bg_' : 'pfp_';
$filename = $prefix . $linkPageId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$targetPath = PFP_DIR . $filename; // Keep in same directory for now

// Ensure directory exists and is writable
if (!file_exists(PFP_DIR)) {
    if (!@mkdir(PFP_DIR, 0755, true)) {
        error_log("Upload error: Failed to create directory - " . PFP_DIR);
        http_response_code(500);
        die(json_encode(['error' => 'Server Configuration Error: Failed to create upload directory.']));
    }
}
if (!is_writable(PFP_DIR)) {
    error_log("Upload error: Directory not writable - " . PFP_DIR);
    http_response_code(500);
    die(json_encode(['error' => 'Server Configuration Error: The upload directory is not writable. Check folder permissions (755).']));
}

// Log file info before move
error_log("Upload info: Size=" . $file['size'] . " bytes, Tmp=" . $file['tmp_name'] . ", Target=" . $targetPath);

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Verify file was actually moved and has content
    if (!file_exists($targetPath) || filesize($targetPath) === 0) {
        error_log("Upload error: File move succeeded but file is empty or missing at " . $targetPath);
        http_response_code(500);
        die(json_encode(['error' => 'File was uploaded but appears to be empty. Please try again.']));
    }
    // Update database based on upload type
    $db = Database::getInstance()->getConnection();
    if ($uploadType === 'profile') {
        $stmt = $db->prepare("UPDATE link_pages SET profile_image = ? WHERE id = ?");
        $stmt->execute([$filename, $linkPageId]);
    } else if ($uploadType === 'background') {
        // Check if design_settings exists
        $stmt = $db->prepare("SELECT id FROM design_settings WHERE link_page_id = ?");
        $stmt->execute([$linkPageId]);
        if ($stmt->fetchColumn()) {
            $stmt = $db->prepare("UPDATE design_settings SET bg_image = ? WHERE link_page_id = ?");
            $stmt->execute([$filename, $linkPageId]);
        } else {
            $stmt = $db->prepare("INSERT INTO design_settings (link_page_id, bg_image) VALUES (?, ?)");
            $stmt->execute([$linkPageId, $filename]);
        }
    }

    $url = ($uploadType === 'background') ? BG_URL . $filename : PFP_URL . $filename;

    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'url' => $url
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
}
?>
