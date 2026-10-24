<?php
// Token generation system

class TokenGenerator {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate a unique 6-character alphanumeric token
     */
    public function generateToken() {
        $maxAttempts = 100;
        $attempts = 0;
        
        do {
            $token = $this->createRandomToken();
            $attempts++;
            
            if ($attempts >= $maxAttempts) {
                throw new Exception("Failed to generate unique token after {$maxAttempts} attempts");
            }
        } while (!$this->isTokenUnique($token));
        
        return $token;
    }
    
    /**
     * Create a random 6-character token
     */
    private function createRandomToken() {
        $chars = TOKEN_CHARS;
        $token = '';
        $charsLength = strlen($chars);
        
        for ($i = 0; $i < TOKEN_LENGTH; $i++) {
            $token .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $token;
    }
    
    /**
     * Check if token is unique in database
     */
    public function isTokenUnique($token) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM tokens WHERE token = :token");
            $stmt->execute(['token' => $token]);
            $result = $stmt->fetch();
            
            return $result['count'] == 0;
        } catch (PDOException $e) {
            error_log("Token uniqueness check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a random 8-character password
     */
    private function generatePassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ23456789';
        $count = mb_strlen($chars);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }
        return $result;
    }

    /**
     * Save token to database with auto-generated portal password
     */
    public function saveToken($token, $reference = null) {
        try {
            $pass = $this->generatePassword();
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO tokens (token, reference, password_plain, password_hash) VALUES (:token, :reference, :pass, :hash)");
            $stmt->execute([
                'token' => $token, 
                'reference' => $reference,
                'pass' => $pass,
                'hash' => $hash
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Token save error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get token by string
     */
    public function getTokenByString($tokenString) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM tokens WHERE token = :token AND is_active = 1");
            $stmt->execute(['token' => $tokenString]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Token fetch error: " . $e->getMessage());
            return null;
        }
    }
}
