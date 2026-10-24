<?php

class Security {
    /**
     * Globally sanitize all input data (GET and POST)
     */
    public static function sanitizeInputs() {
        $_GET = self::sanitizeArray($_GET);
        $_POST = self::sanitizeArray($_POST);
        
        // Also sanitize headers if needed, but GET/POST are primary concerns
    }

    /**
     * Recursively sanitize array values
     */
    private static function sanitizeArray($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                // Trim whitespace
                $value = trim($value);
                
                // Strip HTML tags to prevent XSS
                $value = strip_tags($value);
                
                // Enforce length limits (e.g. 2000 chars per field)
                if (strlen($value) > 2000) {
                    $value = substr($value, 0, 2000);
                }
                
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Generate or return existing CSRF token
     */
    public static function getCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token from request
     */
    public static function verifyCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (empty($token) || !hash_equals(self::getCSRFToken(), $token)) {
                header('HTTP/1.1 403 Forbidden');
                die("CSRF validation failed. Please refresh the page and try again.");
            }
        }
    }

    /**
     * General function to reject malformed data patterns (SQLi bait)
     */
    public static function blockSuspicious() {
        // Skip for legitimate file uploads if needed, but primary check is on string inputs
        $inputString = $_SERVER['QUERY_STRING'] . file_get_contents('php://input');
        
        $suspicious_patterns = [
            '/union\s+select/i',
            '/exec\s+\(/i',
            '/<script/i',
            '/base64_/i' 
        ];

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $inputString)) {
                header('HTTP/1.1 403 Forbidden');
                die("Access Denied: Malformed request detected.");
            }
        }
    }
}
