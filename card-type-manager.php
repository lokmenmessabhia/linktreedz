<?php
/**
 * Card Type Manager
 * Handles card type operations and metadata
 */

class CardTypeManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get all available card types
     */
    public function getCardTypes() {
        $stmt = $this->db->query("SELECT * FROM card_types ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get specific card type by ID
     */
    public function getCardTypeById($id) {
        $stmt = $this->db->prepare("SELECT * FROM card_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get specific card type by name
     */
    public function getCardTypeByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM card_types WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get card dimensions in cm
     */
    public function getCardDimensions($cardTypeId) {
        $cardType = $this->getCardTypeById($cardTypeId);
        if ($cardType) {
            return [
                'width' => (float)$cardType['width_cm'],
                'height' => (float)$cardType['height_cm']
            ];
        }
        return ['width' => 13.0, 'height' => 18.0]; // Default to all_links size
    }
    
    /**
     * Get template image path for card type
     */
    public function getTemplateImage($cardTypeId) {
        $cardType = $this->getCardTypeById($cardTypeId);
        if ($cardType && !empty($cardType['template_image'])) {
            return __DIR__ . '/' . $cardType['template_image'];
        }
        return __DIR__ . '/card-template.png'; // Fallback
    }
    
    /**
     * Check if card type supports sub-tokens (social media cards)
     */
    public function supportsSubTokens($cardTypeId) {
        $cardType = $this->getCardTypeById($cardTypeId);
        if ($cardType) {
            // Social media cards support sub-tokens (not all_links)
            return in_array($cardType['name'], ['facebook', 'instagram', 'tiktok', 'review']);
        }
        return false;
    }
    
    /**
     * Get card types that can be sub-tokens
     */
    public function getSubTokenCardTypes() {
        $stmt = $this->db->query("SELECT * FROM card_types WHERE name IN ('facebook', 'instagram', 'tiktok', 'review') ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
