<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';
require_once 'database.php';

if (!isset($_SESSION['portal_token_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
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

$data = json_decode(file_get_contents('php://input'), true);

try {
    $db->beginTransaction();

    // Update Profile Name if provided
    if (isset($data['profile_name'])) {
        $stmt = $db->prepare("UPDATE link_pages SET profile_name = ? WHERE id = ?");
        $stmt->execute([$data['profile_name'], $pageId]);
    }

    // Update Phone Number if provided
    if (isset($data['phone_number'])) {
        $stmt = $db->prepare("UPDATE link_pages SET phone_number = ? WHERE id = ?");
        $stmt->execute([$data['phone_number'], $pageId]);
    }

    // Update Design Settings — atomic UPSERT
    if (isset($data['settings'])) {
        $settings = $data['settings'];
        $stmt = $db->prepare("
            INSERT INTO design_settings
                (link_page_id, template_id, background_color, button_color, text_color, font_family, bg_image)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                template_id      = VALUES(template_id),
                background_color = VALUES(background_color),
                button_color     = VALUES(button_color),
                text_color       = VALUES(text_color),
                font_family      = VALUES(font_family),
                bg_image         = VALUES(bg_image)
        ");
        $stmt->execute([
            $pageId,
            $settings['template_id']      ?? 'simple',
            $settings['background_color'] ?? '#0F172A',
            $settings['button_color']     ?? '#6366f1',
            $settings['text_color']       ?? '#ffffff',
            $settings['font_family']      ?? 'Inter',
            $settings['bg_image']         ?? null,
        ]);
    }

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
