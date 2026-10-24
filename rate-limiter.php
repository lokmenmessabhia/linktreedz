<?php
require_once 'database.php';

class RateLimiter {
    private static $db = null;

    private static function getDb() {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * General function to check and block if limit reached
     */
    public static function checkLimit($action, $maxAttempts, $windowSeconds) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $db = self::getDb();
        
        try {
            // Prune old records for this action/IP to keep table small
            $stmt = $db->prepare("DELETE FROM rate_limits WHERE action = ? AND attempt_time < ?");
            $stmt->execute([$action, time() - $windowSeconds]);

            // Count recent attempts
            $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND action = ?");
            $stmt->execute([$ip, $action]);
            $count = $stmt->fetchColumn();

            if ($count >= $maxAttempts) {
                // Return descriptive error or just boolean
                return false; 
            }
            return true;
        } catch (Exception $e) {
            // Fallback: allow request if DB fails (prevent total site lockout)
            return true;
        }
    }

    /**
     * Record a new attempt
     */
    public static function recordAttempt($action) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $db = self::getDb();
        try {
            $stmt = $db->prepare("INSERT INTO rate_limits (ip_address, action, attempt_time) VALUES (?, ?, ?)");
            $stmt->execute([$ip, $action, time()]);
        } catch (Exception $e) { }
    }

    /**
     * Clear all attempts for an IP (use after successful login)
     */
    public static function clearAttempts($action) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $db = self::getDb();
        try {
            $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ?");
            $stmt->execute([$ip, $action]);
        } catch (Exception $e) { }
    }

    /**
     * Global request limiter (60 per minute)
     */
    public static function handleGlobalLimit() {
        // Global limit: 60 per minute
        if (!self::checkLimit('global', 60, 60)) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Too many requests. Please wait a minute.']));
        }
        self::recordAttempt('global');
    }
}
