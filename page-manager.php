<?php
class PageManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Delete a link page and all associated data
     */
    public function deletePage($pageId) {
        try {
            $this->db->beginTransaction();

            // 1. Get token_id first
            $stmt = $this->db->prepare("SELECT token_id FROM link_pages WHERE id = ?");
            $stmt->execute([$pageId]);
            $page = $stmt->fetch();

            if (!$page) {
                throw new Exception("Page not found");
            }

            $tokenId = $page['token_id'];
            
            // 2. Get file paths to delete from disk
            $stmt = $this->db->prepare("SELECT qr_code_path, card_image_path FROM qr_cards WHERE token_id = ?");
            $stmt->execute([$tokenId]);
            $files = $stmt->fetchAll();

            // 3. Delete links
            $stmt = $this->db->prepare("DELETE FROM links WHERE link_page_id = ?");
            $stmt->execute([$pageId]);

            // 4. Delete design settings
            $stmt = $this->db->prepare("DELETE FROM design_settings WHERE link_page_id = ?");
            $stmt->execute([$pageId]);

            // 5. Delete QR cards records
            $stmt = $this->db->prepare("DELETE FROM qr_cards WHERE token_id = ?");
            $stmt->execute([$tokenId]);

            // 6. Delete link page
            $stmt = $this->db->prepare("DELETE FROM link_pages WHERE id = ?");
            $stmt->execute([$pageId]);

            // 7. Delete token
            $stmt = $this->db->prepare("DELETE FROM tokens WHERE id = ?");
            $stmt->execute([$tokenId]);

            $this->db->commit();
            
            // 8. Physical file cleanup (after successful DB transaction)
            foreach($files as $file) {
                if ($file['qr_code_path']) {
                    $rawPath = QR_RAW_DIR . $file['qr_code_path'];
                    if (file_exists($rawPath)) @unlink($rawPath);
                }
                if ($file['card_image_path']) {
                    $cardPath = QR_CARD_DIR . $file['card_image_path'];
                    if (file_exists($cardPath)) @unlink($cardPath);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Delete page error: " . $e->getMessage());
            return false;
        }
    }
}
?>
