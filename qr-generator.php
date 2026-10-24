<?php
// QR Code generation system
// Requires: composer require phpqrcode/phpqrcode OR use included phpqrcode library

class QRGenerator {
    private $db;
    private $qrRawDir;
    private $qrCardDir;
    
    // --- TOKEN TEXT CONFIGURATION ---
    // Change these values to adjust token text appearance/position
    public $tokenFontSize = 27 ;
    public $tokenYOffset  = 2; // Increased padding (from -7) to move text further below QR bottom
    public $tokenXOffset  = 0;  // Horizontal offset from center
    // --------------------------------
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->qrRawDir = QR_RAW_DIR;
        $this->qrCardDir = QR_CARD_DIR;
        
        // Create storage directories if they don't exist
        if (!file_exists(STORAGE_DIR)) mkdir(STORAGE_DIR, 0755, true);
        if (!file_exists($this->qrRawDir)) mkdir($this->qrRawDir, 0755, true);
        if (!file_exists($this->qrCardDir)) mkdir($this->qrCardDir, 0755, true);
    }
    
    /**
     * Generate QR code for a token
     * @param string $token The base token (6 characters)
     * @param int $size QR code size in pixels
     * @param string $cardType Card type (all_links, facebook, instagram, tiktok, review)
     */
    public function generateQRCode($token, $size = 300, $cardType = 'all_links') {
        try {
            // Include phpqrcode library
            $phpqrcodePath = __DIR__ . '/phpqrcode/qrlib.php';
            if (file_exists($phpqrcodePath)) {
                require_once $phpqrcodePath;
            }

            if (!class_exists('QRcode') || !extension_loaded('gd')) {
                // Fallback to simple generation if library missing OR GD library missing
                return $this->generateQRCodeSimple($token, $size, $cardType);
            }
            
            // Build URL with sub-token suffix for social media cards
            $url = BASE_URL . '/u/' . $token;
            if ($cardType !== 'all_links') {
                $url .= '.' . $cardType . '#';
            }
            
            $filename = $token . ($cardType !== 'all_links' ? '_' . $cardType : '') . '.png';
            $filepath = $this->qrRawDir . $filename;
            
            // Generate QR code using phpqrcode library
            try {
                QRcode::png($url, $filepath, QR_ECLEVEL_L, 10, 2);
            } catch (Throwable $t) {
                error_log("phpqrcode failed: " . $t->getMessage());
                return $this->generateQRCodeSimple($token, $size, $cardType);
            }
            
            return $filename;
        } catch (Throwable $e) {
            error_log("QR code generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Simple QR code generation using API (fallback)
     */
    private function generateQRCodeSimple($token, $size = 300, $cardType = 'all_links') {
        try {
            // Build URL with sub-token suffix for social media cards
            $url = BASE_URL . '/u/' . $token;
            if ($cardType !== 'all_links') {
                $url .= '.' . $cardType . '#';
            }
            
            $filename = $token . ($cardType !== 'all_links' ? '_' . $cardType : '') . '.png';
            $filepath = $this->qrRawDir . $filename;
            
            if (QR_API_URL === '') {
                throw new Exception('QR_API_URL is not configured');
            }

            $qrUrl = rtrim(QR_API_URL, '/') . '?size=' . $size . 'x' . $size . '&data=' . urlencode($url);

            $imageData = file_get_contents($qrUrl);
            if ($imageData === false) {
                throw new Exception("Failed to fetch QR code from API");
            }
            
            file_put_contents($filepath, $imageData);
            
            return $filename;
        } catch (Exception $e) {
            error_log("Simple QR code generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get QR position config based on card type and template dimensions
     */
    private function getQRConfig($cardType, $templateWidth, $templateHeight) {
        // Default: Centered
        $config = [
            'x_percent' => 50,
            'y_percent' => 50,
            'size_percent' => 30
        ];

        // Custom positions for specific card types
        // Most Linkydz templates use a bottom-right box layout
        switch ($cardType) {
            case 'facebook':
            case 'instagram':
            case 'tiktok':
            case 'review':
                $config = [
                    'x_percent' => 68.4, 
                    'y_percent' => 74, // Moved down (from 73.5)
                    'size_percent' => 23.36
                ];
                break;
            case 'all_links':
                $config = [
                    'x_percent' => 71, 
                    'y_percent' => 75, // Moved down (from 75.0)
                    'size_percent' => 24.98
                ];
                break;
        }

        return $config;
    }

    /**
     * Overlay QR code on PNG template
     */
    public function overlayQROnTemplate($qrFilename, $templatePath, $outputFilename = null, $cardType = 'all_links', $token = null) {
        try {
            $qrPath = $this->qrRawDir . $qrFilename;
            
            if (!file_exists($qrPath)) {
                throw new Exception("QR code file not found at " . $qrPath);
            }
            
            if (!file_exists($templatePath)) {
                throw new Exception("Template file not found at " . $templatePath);
            }
            
            // Load images with type detection
            if (!extension_loaded('gd')) {
                throw new Exception("GD library is not enabled in PHP.");
            }
            
            // Helper function to create image from file
            $loadImage = function($path) {
                $info = getimagesize($path);
                if (!$info) return false;
                switch ($info[2]) {
                    case IMAGETYPE_JPEG: return imagecreatefromjpeg($path);
                    case IMAGETYPE_PNG:  return imagecreatefrompng($path);
                    case IMAGETYPE_GIF:  return imagecreatefromgif($path);
                    default: return false;
                }
            };

            $template = $loadImage($templatePath);
            $qr = $loadImage($qrPath);
            
            if (!$template) {
                throw new Exception("Failed to load template image");
            }
            if (!$qr) {
                throw new Exception("Failed to load QR image");
            }
            
            // Get dimensions
            $templateWidth = imagesx($template);
            $templateHeight = imagesy($template);
            
            // Create a final canvas and fill with white (Fixes "black background" issue)
            $finalCard = imagecreatetruecolor($templateWidth, $templateHeight);
            $white = imagecolorallocate($finalCard, 255, 255, 255);
            imagefilledrectangle($finalCard, 0, 0, $templateWidth, $templateHeight, $white);
            
            // Handle transparency for template overlay
            imagealphablending($finalCard, true);
            imagecopy($finalCard, $template, 0, 0, 0, 0, $templateWidth, $templateHeight);
            
            // Get QR config for this card type
            $config = $this->getQRConfig($cardType, $templateWidth, $templateHeight);
            
            // Calculate target size
            $targetSize = (int)(($templateWidth * $config['size_percent']) / 100);
            
            // Resize QR code to target size
            $resizedQR = imagecreatetruecolor($targetSize, $targetSize);
            
            // Handle transparency for resized QR
            imagealphablending($resizedQR, false);
            imagesavealpha($resizedQR, true);
            $transparent = imagecolorallocatealpha($resizedQR, 255, 255, 255, 127);
            imagefilledrectangle($resizedQR, 0, 0, $targetSize, $targetSize, $transparent);
            
            imagecopyresampled(
                $resizedQR, $qr, 
                0, 0, 0, 0, 
                $targetSize, $targetSize, 
                imagesx($qr), imagesy($qr)
            );
            
            // Calculate QR position
            $x = (int)(($templateWidth * $config['x_percent'] / 100) - ($targetSize / 2));
            $y = (int)(($templateHeight * $config['y_percent'] / 100) - ($targetSize / 2));
            
            // Overlay QR code on final card
            imagecopy($finalCard, $resizedQR, $x, $y, 0, 0, $targetSize, $targetSize);
            
            // Overlay Token Text directly on the picture if provided
            if ($token) {
                $black = imagecolorallocate($finalCard, 0, 0, 0);
                
                // Use configurable font size
                $fontSize = $this->tokenFontSize; 
                // Use bundled font if available, otherwise fallback to system paths
                $fontPath = __DIR__ . '/arialbd.ttf'; 
                
                if (!file_exists($fontPath)) {
                    $fontPath = 'C:/Windows/Fonts/arialbd.ttf'; 
                }
                if (!file_exists($fontPath)) {
                    $fontPath = 'C:/Windows/Fonts/arial.ttf';
                }
                if (!file_exists($fontPath)) {
                    $fontPath = __DIR__ . '/arial.ttf';
                }
                
                // Get QR horizontal center
                $qrCenterX = $templateWidth * ($config['x_percent'] / 100);
                
                if (file_exists($fontPath)) {
                    $bbox = imagettfbbox($fontSize, 0, $fontPath, $token);
                    $tw = abs($bbox[4] - $bbox[0]);
                    
                    $tx = (int)($qrCenterX - ($tw / 2) + $this->tokenXOffset);
                    $ty = (int)($y + $targetSize + $this->tokenYOffset);
                    
                    imagettftext($finalCard, $fontSize, 0, $tx, $ty, $black, $fontPath, $token);
                } else {
                    $gfs = 5; 
                    $tw = strlen($token) * imagefontwidth($gfs);
                    $tx = (int)($qrCenterX - ($tw / 2) + $this->tokenXOffset);
                    $ty = (int)($y + $targetSize + $this->tokenYOffset);
                    
                    imagestring($finalCard, $gfs, $tx, $ty, $token, $black);
                    imagestring($finalCard, $gfs, $tx + 1, $ty, $token, $black);
                }
            }
            
            // Save output
            if ($outputFilename === null) {
                $outputFilename = 'card_' . pathinfo($qrFilename, PATHINFO_FILENAME) . '.png';
            }
            $outputPath = $this->qrCardDir . $outputFilename;
            
            imagepng($finalCard, $outputPath);
            
            // Free memory
            imagedestroy($template);
            imagedestroy($qr);
            imagedestroy($resizedQR);
            imagedestroy($finalCard);
            
            return $outputFilename;
        } catch (Exception $e) {
            error_log("QR overlay error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save QR card record to database
     */
    public function saveQRCard($tokenId, $templateName, $qrCodePath, $cardImagePath) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO qr_cards (token_id, template_name, qr_code_path, card_image_path)
                VALUES (:token_id, :template_name, :qr_code_path, :card_image_path)
            ");
            $stmt->execute([
                'token_id' => $tokenId,
                'template_name' => $templateName,
                'qr_code_path' => $qrCodePath,
                'card_image_path' => $cardImagePath
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Save QR card error: " . $e->getMessage());
            return false;
        }
    }
}
