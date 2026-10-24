<?php
// Authentication system

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Login user with username/email and password
     */
    public function login($username, $password) {
        $username = trim($username);
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check rate limit (5 attempts / 15 mins)
        if (!RateLimiter::checkLimit('admin_login', 5, 900)) {
            return 'blocked';
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash 
                FROM admins 
                WHERE username = :u1 OR email = :u2
                LIMIT 1
            ");
            $stmt->execute(['u1' => $username, 'u2' => $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Clear failed attempts on success
                RateLimiter::clearAttempts('admin_login');
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Set session variables
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                return true;
            }
            
            // Record failed attempt
            RateLimiter::recordAttempt('admin_login');
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        session_destroy();
        return true;
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Require authentication - redirect to login if not authenticated
     */
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            header('Location: login');
            exit;
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
            $this->logout();
            header('Location: login?timeout=1');
            exit;
        }
    }
    
    /**
     * Get current admin user data
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_id'] ?? null,
            'username' => $_SESSION['admin_username'] ?? null,
            'email' => $_SESSION['admin_email'] ?? null,
        ];
    }
    
    /**
     * Check if any admin exists in the database
     */
    public function hasAdmins() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM admins");
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Create initial admin account
     */
    public function createAdmin($username, $email, $password) {
        try {
            $hash = $this->hashPassword($password);
            $stmt = $this->db->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
            return $stmt->execute([$username, $email, $hash]);
        } catch (PDOException $e) {
            error_log("Create admin error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}
