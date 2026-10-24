<?php
// Link management operations

class LinkManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Create a new link
     */
    public function createLink($linkPageId, $title, $url, $priority = 0) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO links (link_page_id, title, url, priority) 
                VALUES (:link_page_id, :title, :url, :priority)
            ");
            $stmt->execute([
                'link_page_id' => $linkPageId,
                'title' => $title,
                'url' => $url,
                'priority' => $priority
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create link error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a link
     */
    public function updateLink($id, $title, $url, $isActive, $priority) {
        try {
            $stmt = $this->db->prepare("
                UPDATE links 
                SET title = :title, url = :url, is_active = :is_active, priority = :priority
                WHERE id = :id
            ");
            return $stmt->execute([
                'id' => $id,
                'title' => $title,
                'url' => $url,
                'is_active' => $isActive ? 1 : 0,
                'priority' => $priority
            ]);
        } catch (PDOException $e) {
            error_log("Update link error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a link
     */
    public function deleteLink($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM links WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Delete link error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all links for a link page
     */
    public function getLinks($linkPageId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM links 
                WHERE link_page_id = :link_page_id 
                ORDER BY priority ASC, created_at ASC
            ");
            $stmt->execute(['link_page_id' => $linkPageId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get links error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active links for a link page (for public display)
     */
    public function getActiveLinks($linkPageId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM links 
                WHERE link_page_id = :link_page_id AND is_active = 1
                ORDER BY priority ASC, created_at ASC
            ");
            $stmt->execute(['link_page_id' => $linkPageId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get active links error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Reorder links
     */
    public function reorderLinks($linkIds) {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("UPDATE links SET priority = :priority WHERE id = :id");
            
            foreach ($linkIds as $priority => $id) {
                $stmt->execute([
                    'priority' => $priority,
                    'id' => $id
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Reorder links error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get link page by token
     */
    public function getLinkPageByToken($tokenString) {
        try {
            $stmt = $this->db->prepare("
                SELECT lp.* 
                FROM link_pages lp
                JOIN tokens t ON lp.token_id = t.id
                WHERE t.token = :token AND t.is_active = 1
            ");
            $stmt->execute(['token' => $tokenString]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get link page error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a new link page
     */
    public function createLinkPage($tokenId, $profileName) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO link_pages (token_id, profile_name) 
                VALUES (:token_id, :profile_name)
            ");
            $stmt->execute([
                'token_id' => $tokenId,
                'profile_name' => $profileName
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Create link page error: " . $e->getMessage());
            return false;
        }
    }
}
