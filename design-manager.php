<?php
// Design settings management

class DesignManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get design settings for a link page
     */
    public function getDesignSettings($linkPageId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM design_settings 
                WHERE link_page_id = :link_page_id
            ");
            $stmt->execute(['link_page_id' => $linkPageId]);
            $settings = $stmt->fetch();
            
            // If no settings exist, return defaults
            if (!$settings) {
                return $this->getDefaultSettings();
            }
            
            return $settings;
        } catch (PDOException $e) {
            error_log("Get design settings error: " . $e->getMessage());
            return $this->getDefaultSettings();
        }
    }
    
    /**
     * Update design settings — uses atomic UPSERT to avoid race conditions
     * and correctly handles new tokens that don't have a design row yet.
     */
    public function updateDesignSettings($linkPageId, $settings) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO design_settings
                    (link_page_id, background_color, button_color, text_color, font_family, button_style, template_id, bg_image)
                VALUES
                    (:link_page_id, :background_color, :button_color, :text_color, :font_family, :button_style, :template_id, :bg_image)
                ON DUPLICATE KEY UPDATE
                    background_color = VALUES(background_color),
                    button_color     = VALUES(button_color),
                    text_color       = VALUES(text_color),
                    font_family      = VALUES(font_family),
                    button_style     = VALUES(button_style),
                    template_id      = VALUES(template_id),
                    bg_image         = VALUES(bg_image)
            ");

            return $stmt->execute([
                'link_page_id'     => $linkPageId,
                'background_color' => $settings['background_color'] ?? '#0F172A',
                'button_color'     => $settings['button_color']     ?? '#3B82F6',
                'text_color'       => $settings['text_color']       ?? '#FFFFFF',
                'font_family'      => $settings['font_family']      ?? 'Inter',
                'button_style'     => $settings['button_style']     ?? 'rounded',
                'template_id'      => $settings['template_id']      ?? 'simple',
                'bg_image'         => $settings['bg_image']         ?? null,
            ]);
        } catch (PDOException $e) {
            error_log("Update design settings error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get default design settings
     */
    public function getDefaultSettings() {
        return [
            'background_color' => '#0F172A',
            'button_color' => '#3B82F6',
            'text_color' => '#FFFFFF',
            'font_family' => 'Inter',
            'button_style' => 'rounded',
            'template_id' => 'simple',
            'bg_image' => null
        ];
    }
    
    /**
     * Initialize default settings for a link page
     */
    public function initializeDefaultSettings($linkPageId) {
        return $this->updateDesignSettings($linkPageId, $this->getDefaultSettings());
    }
}
