<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function debug_log($msg) {
    file_put_contents(__DIR__ . '/debug_pdf.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

debug_log("API Start - " . $_SERVER['REQUEST_METHOD']);

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'phpqrcode/qrlib.php';

function utf8_to_cp1256($text) {
    static $map = null;
    if ($map === null) {
        $map = [];
        $lines = file(__DIR__ . '/fpdf/makefont/cp1256.map');
        foreach ($lines as $line) {
            if (preg_match('/^!([0-9A-F]{2}) U\+([0-9A-F]+)/i', $line, $m)) {
                $map[hexdec($m[2])] = hexdec($m[1]);
            }
        }
    }
    $out = '';
    $i = 0; $len = strlen($text);
    while ($i < $len) {
        $b = ord($text[$i]);
        if ($b < 0x80) {
            $out .= chr($b); $i++;
        } elseif (($b & 0xE0) === 0xC0) {
            $cp = (($b & 0x1F) << 6) | (ord($text[$i+1]) & 0x3F);
            $out .= chr($map[$cp] ?? ord('?'));
            $i += 2;
        } elseif (($b & 0xF0) === 0xE0) {
            $cp = (($b & 0x0F) << 12) | ((ord($text[$i+1]) & 0x3F) << 6) | (ord($text[$i+2]) & 0x3F);
            $out .= chr($map[$cp] ?? ord('?'));
            $i += 3;
        } elseif (($b & 0xF8) === 0xF0) {
            $cp = (($b & 0x07) << 18) | ((ord($text[$i+1]) & 0x3F) << 12) | ((ord($text[$i+2]) & 0x3F) << 6) | (ord($text[$i+3]) & 0x3F);
            $out .= chr($map[$cp] ?? ord('?'));
            $i += 4;
        } else { $i++; }
    }
    return $out;
}

function utf8_to_custom($text) {
    static $map = null;
    if ($map === null) {
        $map = [];
        $lines = file(__DIR__ . '/fpdf/makefont/arabic_shaped.map');
        foreach ($lines as $line) {
            if (preg_match('/^!([0-9A-F]{2}) (U\+[0-9A-F]+)/i', $line, $m)) {
                $map[hexdec(substr($m[2],2))] = hexdec($m[1]);
            }
        }
    }
    $out = '';
    $i = 0; $len = strlen($text);
    while ($i < $len) {
        $b = ord($text[$i]);
        if ($b < 0x80) {
            $out .= chr($b); $i++;
        } elseif (($b & 0xE0) === 0xC0) {
            $cp = (($b & 0x1F) << 6) | (ord($text[$i+1]) & 0x3F);
            $out .= chr($map[$cp] ?? ord('?'));
            $i += 2;
        } elseif (($b & 0xF0) === 0xE0) {
            $cp = (($b & 0x0F) << 12) | ((ord($text[$i+1]) & 0x3F) << 6) | (ord($text[$i+2]) & 0x3F);
            $out .= chr($map[$cp] ?? ord('?'));
            $i += 3;
        } elseif (($b & 0xF8) === 0xF0) {
            $cp = (($b & 0x07) << 18) | ((ord($text[$i+1]) & 0x3F) << 12) | ((ord($text[$i+2]) & 0x3F) << 6) | (ord($text[$i+3]) & 0x3F);
            $out .= chr($map[$cp] ?? ord('?'));
            $i += 4;
        } else { $i++; }
    }
    return $out;
}

function shape_ar($text) {
    static $iso = null, $fin = null, $ini = null, $med = null;
    static $formToByte = null;
    static $rightJoin = null;
    static $leftJoin = null;
    static $mapBuilt = false;

    if (!$mapBuilt) {
        $mapBuilt = true;
        $lines = file(__DIR__ . '/fpdf/makefont/arabic_shaped.map');

        // Build form→byte and char→forms tables
        $formToByte = [];

        // Standard Arabic letter to presentation forms mapping
        // Format: standard_unicode => [isolated, final, initial, medial]
        $charToForms = [
            0x0627 => [0xFE8D, 0xFE8E, 0, 0], // alef
            0x0628 => [0xFE90, 0xFE92, 0xFE91, 0xFE91], // beh (medial=initial)
            0x062A => [0xFE95, 0xFE97, 0xFE96, 0xFE98], // teh
            0x062B => [0xFE99, 0xFE9B, 0xFE9A, 0xFE9C], // theh
            0x062C => [0xFE9D, 0xFE9F, 0xFE9E, 0xFEA0], // jeem
            0x062D => [0xFEA1, 0xFEA3, 0xFEA2, 0xFEA4], // hah
            0x062E => [0xFEA5, 0xFEA7, 0xFEA6, 0xFEA8], // khah
            0x062F => [0xFEA9, 0xFEAA, 0, 0], // dal
            0x0630 => [0xFEAB, 0xFEAC, 0, 0], // dhal
            0x0631 => [0xFEAD, 0xFEAE, 0, 0], // reh
            0x0632 => [0xFEAF, 0xFEB0, 0, 0], // zain
            0x0633 => [0xFEB1, 0xFEB3, 0xFEB2, 0xFEB4], // seen
            0x0634 => [0xFEB5, 0xFEB7, 0xFEB6, 0xFEB8], // sheen
            0x0635 => [0xFEB9, 0xFEBB, 0xFEBA, 0xFEBC], // sad
            0x0636 => [0xFEBD, 0xFEBF, 0xFEBE, 0xFEC0], // dad
            0x0637 => [0xFEC1, 0xFEC3, 0xFEC2, 0xFEC4], // tah
            0x0638 => [0xFEC5, 0xFEC7, 0xFEC6, 0xFEC8], // zah
            0x0639 => [0xFEC9, 0xFECB, 0xFECA, 0xFECC], // ain
            0x063A => [0xFECD, 0xFECF, 0xFECE, 0xFED0], // ghain
            0x0641 => [0xFED1, 0xFED3, 0xFED2, 0xFED4], // feh
            0x0642 => [0xFED5, 0xFED7, 0xFED6, 0xFED8], // qaf
            0x0643 => [0xFED9, 0xFEDB, 0xFEDA, 0xFEDC], // kaf
            0x0644 => [0xFEDD, 0xFEDF, 0xFEDE, 0xFEE0], // lam
            0x0645 => [0xFEE1, 0xFEE3, 0xFEE2, 0xFEE4], // meem
            0x0646 => [0xFEE5, 0xFEE7, 0xFEE6, 0xFEE8], // noon
            0x0647 => [0xFEE9, 0xFEEB, 0xFEEA, 0xFEEC], // heh
            0x0648 => [0xFEED, 0xFEEE, 0, 0], // waw
            0x0649 => [0xFEEF, 0xFEF0, 0, 0], // alef maksura
            0x064A => [0xFEF1, 0xFEF3, 0xFEF2, 0xFEF4], // yeh
            0x0629 => [0xFE93, 0xFE94, 0, 0], // teh marbuta
            0x0621 => [0xFE80, 0, 0, 0], // hamza
            0x0640 => [0x0640, 0, 0, 0], // tatweel
        ];

        // Lam-Alef ligatures (special case)
        $lamAlef = [0xFEFB, 0xFEFC, 0, 0]; // isolated, final forms

        // Non-connecting characters (R-type: only join from previous, not to next)
        $rightJoin = []; // D-type: join to next
        foreach ([0x0628,0x062A,0x062B,0x062C,0x062D,0x062E,0x0633,0x0634,0x0635,0x0636,0x0637,0x0638,0x0639,0x063A,0x0641,0x0642,0x0643,0x0644,0x0645,0x0646,0x0647,0x064A] as $c) {
            $rightJoin[$c] = true;
        }
        $leftJoin = []; // R-type + D-type: accept joining from previous
        foreach ([0x0627,0x0628,0x062A,0x062B,0x062C,0x062D,0x062E,0x062F,0x0630,0x0631,0x0632,0x0633,0x0634,0x0635,0x0636,0x0637,0x0638,0x0639,0x063A,0x0641,0x0642,0x0643,0x0644,0x0645,0x0646,0x0647,0x0648,0x0649,0x064A,0x0629] as $c) {
            $leftJoin[$c] = true;
        }

        // Build form→byte mapping from the map file
        $iso = []; $fin = []; $ini = []; $med = [];
        foreach ($charToForms as $std => $forms) {
            $iso[$std] = $forms[0];
            $fin[$std] = $forms[1];
            $ini[$std] = $forms[2];
            $med[$std] = $forms[3];
        }
        // Also store lam-alef
        $iso[0xFEFB] = 0xFEFB; // special - we handle lam-alef by codepoint
        $iso[0xFEFC] = 0xFEFC;
        $fin[0xFEFB] = 0xFEFC;
        $fin[0xFEFC] = 0;

        foreach ($lines as $line) {
            if (preg_match('/^!([0-9A-F]{2}) (U\+[0-9A-F]+)/i', $line, $m)) {
                $formToByte[hexdec(substr($m[2],2))] = hexdec($m[1]);
            }
        }
    }

    // Get UTF-8 chars
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $n = count($chars);

    // Get Unicode code points
    $cps = [];
    foreach ($chars as $ch) {
        $bytes = strlen($ch);
        $b = ord($ch[0]);
        if ($b < 0x80) { $cps[] = $b; }
        elseif (($b & 0xE0) === 0xC0) { $cps[] = (($b & 0x1F) << 6) | (ord($ch[1]) & 0x3F); }
        elseif (($b & 0xF0) === 0xE0) { $cps[] = (($b & 0x0F) << 12) | ((ord($ch[1]) & 0x3F) << 6) | (ord($ch[2]) & 0x3F); }
        elseif (($b & 0xF8) === 0xF0) { $cps[] = (($b & 0x07) << 18) | ((ord($ch[1]) & 0x3F) << 12) | ((ord($ch[2]) & 0x3F) << 6) | (ord($ch[3]) & 0x3F); }
        else { $cps[] = 0; }
    }

    // Use code points directly (no lam-alef merging — shaping handles ل and ا separately)
    $merged = $cps;

    // Now shape each "word": contiguous Arabic chars
    $m = count($merged);
    $outputCps = [];
    for ($i = 0; $i < $m; $i++) {
        $cp = $merged[$i];
        // ASCII chars pass through unchanged
        if ($cp < 0x80) {
            $outputCps[] = $cp;
            continue;
        }
        // Non-Arabic chars (not in our shaping table) pass through
        if (!isset($iso[$cp]) && $cp !== 0xFEFB && $cp !== 0xFEFC) {
            $outputCps[] = $cp;
            continue;
        }

        // Determine position in word
        $prevCp = $i > 0 ? $merged[$i-1] : 0;
        $nextCp = $i < $m-1 ? $merged[$i+1] : 0;

        $isPrevArabic = ($prevCp >= 0x0600 && $prevCp <= 0x06FF) || $prevCp === 0xFEFB || $prevCp === 0xFEFC;
        $isNextArabic = ($nextCp >= 0x0600 && $nextCp <= 0x06FF) || $nextCp === 0xFEFB || $nextCp === 0xFEFC;

        // Select form based on position in the word connection group
        if ($isPrevArabic && $isNextArabic) {
            $formCp = ($med[$cp] ?? 0) ?: ($ini[$cp] ?? 0) ?: ($fin[$cp] ?? 0) ?: ($iso[$cp] ?? 0);
        } elseif (!$isPrevArabic && $isNextArabic) {
            $formCp = ($ini[$cp] ?? 0) ?: ($iso[$cp] ?? 0);
        } elseif ($isPrevArabic && !$isNextArabic) {
            $formCp = ($fin[$cp] ?? 0) ?: ($iso[$cp] ?? 0);
        } else {
            $formCp = ($iso[$cp] ?? 0) ?: ($fin[$cp] ?? 0);
        }

        $outputCps[] = $formCp;
    }

    // Reverse for RTL only if text contains Arabic characters
    $hasArabic = false;
    foreach ($cps as $cp) {
        if ($cp >= 0x0600 && $cp <= 0x06FF) { $hasArabic = true; break; }
    }
    if ($hasArabic) {
        $outputCps = array_reverse($outputCps);
    }

    // Convert to bytes using custom encoding
    $out = '';
    foreach ($outputCps as $cp) {
        $byte = $formToByte[$cp] ?? null;
        if ($byte !== null) {
            $out .= chr($byte);
        } elseif ($cp < 0x80) {
            $out .= chr($cp);
        } else {
            $out .= '?';
        }
    }
    return $out;
}

function drawThankYouCard($pdf, $token, $name, $password, $x, $y, $cw, $ch) {
    $bgFile = __DIR__ . '/thankyou-bg.png';
    if (file_exists($bgFile)) {
        $pdf->Image($bgFile, $x, $y, $cw, $ch);
    } else {
        $pdf->SetDrawColor(0, 0, 0, 100);
        $pdf->Rect($x, $y, $cw, $ch, 'D');
    }
    
    $innerMargin = $cw * 0.12;
    $boxW = $cw - ($innerMargin * 2);
    $boxH = $ch * 0.28;
    $boxX = $x + $innerMargin;
    $boxY = $y + 75;

    $magicLink = BASE_URL . "/portal-login?token=" . urlencode($token) . "&pass=" . urlencode($password);
    $tmpQrFile = __DIR__ . "/uploads/profiles/tmp_qr_" . $token . ".png";
    if (!file_exists(dirname($tmpQrFile))) mkdir(dirname($tmpQrFile), 0755, true);
    QRcode::png($magicLink, $tmpQrFile, QR_ECLEVEL_L, 4);

    $qrSize = min($boxH * 0.7, $boxW * 0.4);
    if (file_exists($tmpQrFile)) {
        $pdf->Image($tmpQrFile, $boxX + 5, $boxY + ($boxH - $qrSize) / 2, $qrSize, $qrSize);
        @unlink($tmpQrFile);
    }

    $textAreaW = $boxW - $qrSize - 15 - 20;
    $textAreaX = $boxX + $qrSize + 10;

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0, 0, 0, 100);
    $pdf->SetXY($textAreaX, $boxY + 9);
    $pdf->Cell($textAreaW, 7, $name, 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetXY($textAreaX, $boxY + 13.625);
    $pdf->Cell($textAreaW, 7, $password, 0, 1, 'R');
}

function addThankYouPage($pdf, $token, $name, $password, $layout) {
    $pdf->AddPage();
    $pageWidth = 210;
    $pageHeight = 297;
    $cw = 105; // A6 width
    $ch = 148; // A6 height
    $x = ($pageWidth - $cw) / 2;
    $y = ($pageHeight - $ch) / 2;
    drawThankYouCard($pdf, $token, $name, $password, $x, $y, $cw, $ch);
}
require_once 'auth.php';

// Auth Check
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Increase limits for heavy PDF/CMYK processing
set_time_limit(1200); // 20 minutes
ini_set('memory_limit', '1024M'); // 1GB RAM if available


$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$db = Database::getInstance()->getConnection();

if ($action === 'download_zip' || $action === 'download_pdf') {
    $tokens = $input['tokens'] ?? [];
    debug_log("Action: $action. Tokens count: " . count($tokens));
    if (empty($tokens)) {
        debug_log("Error: No tokens selected");
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No tokens selected']);
        exit;
    }

    $downloadMode = $input['download_mode'] ?? 'qr_only';
    $cardType = $input['card_type'] ?? 'all_links';
    debug_log("Download Mode: $downloadMode, Card Type: $cardType");

    // Determine which parts to include
    $includeThankyou  = in_array($cardType, ['all_links_thankyou', 'thankyou_only', 'print_all']);
    $includeMainCard  = ($cardType !== 'thankyou_only');
    $typesToProcess   = $includeMainCard ? ['all_links'] : [];

    // Removal of old regeneration block. 
    // Regeneration is now handled just-in-time during PDF/ZIP creation to ensure latest config is used.


    // Prepare results - intelligently filter based on what links exist on the link page
    $finalResults = [];
    
    if ($downloadMode === 'qr_only') {
        // For QR-only mode, each token has only ONE QR file (token.png)
        foreach ($tokens as $token) {
            $filename = $token . '.png';
            $filePath = QR_RAW_DIR . $filename;
            
            if (file_exists($filePath)) {
                $finalResults[] = [
                    'token' => $token,
                    'card_type' => $typesToProcess[0] ?? 'all_links'
                ];
                debug_log("Including QR for token $token (file exists)");
            } else {
                debug_log("Skipping token $token (QR file not found at $filePath)");
            }
        }
    } else {
        // For whole card mode, check what links exist on the link page
        foreach ($tokens as $token) {
            // Get the link page for this token
            // First get the token_id from tokens table
            $stmt = $db->prepare("SELECT id FROM tokens WHERE token = ?");
            $stmt->execute([$token]);
            $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenRow) {
                debug_log("Skipping token $token (token not found in database)");
                continue;
            }
            
            // Now get the link page using token_id
            $stmt = $db->prepare("SELECT id FROM link_pages WHERE token_id = ?");
            $stmt->execute([$tokenRow['id']]);
            $linkPage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$linkPage) {
                debug_log("Skipping token $token (no link page found)");
                continue;
            }
            
            // Get all active links for this link page
            $stmt = $db->prepare("SELECT url FROM links WHERE link_page_id = ? AND is_active = 1");
            $stmt->execute([$linkPage['id']]);
            $links = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Determine which card types to include based on the links
            foreach ($typesToProcess as $type) {
                $shouldInclude = false;
                
                if ($type === 'all_links') {
                    // Always include all_links if it exists
                    $shouldInclude = true;
                } elseif ($type === 'review') {
                    // Include review card if there's a Google review/maps link
                    foreach ($links as $link) {
                        if (stripos($link, 'google.com/maps') !== false || 
                            stripos($link, 'google.com/local/writereview') !== false ||
                            stripos($link, 'search.google.com/local') !== false) {
                            $shouldInclude = true;
                            break;
                        }
                    }
                } else {
                    // For social media cards (facebook, instagram, tiktok)
                    // Check if there's a link for that platform
                    $platformPatterns = [
                        'facebook' => ['facebook.com', 'fb.com', 'fb.me'],
                        'instagram' => ['instagram.com', 'instagr.am'],
                        'tiktok' => ['tiktok.com', 'tiktok.com/@']
                    ];
                    
                    if (isset($platformPatterns[$type])) {
                        foreach ($links as $link) {
                            foreach ($platformPatterns[$type] as $pattern) {
                                if (stripos($link, $pattern) !== false) {
                                    $shouldInclude = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Check if the card file exists AND the link exists
                if ($shouldInclude) {
                    $filename = 'card_' . $token . ($type === 'all_links' ? '' : '_' . $type) . '.png';
                    $filePath = QR_CARD_DIR . $filename;
                    
                    // We include it if it should exist. 
                    // If it's physically missing, JIT regeneration below will create it.
                    $finalResults[] = [
                        'token' => $token,
                        'card_type' => $type,
                        'file_path' => $filePath,
                        'card_image_path' => $filename // For ZIP loop consistency
                    ];
                    debug_log("Including $type card for token $token");
                }
            }
        }
    }
    
    // For 'thankyou_only' mode, populate results with token stubs so the flow continues
    // The main card loop will skip missing files; addThankYouPage fires at the end.
    if ($cardType === 'thankyou_only' && $downloadMode === 'whole_card') {
        foreach ($tokens as $token) {
            $finalResults[] = [
                'token'     => $token,
                'card_type' => 'all_links', // unused for TY-only
                'file_path' => '',
                'card_image_path' => ''
            ];
        }
        debug_log("Thank-You Only mode: built " . count($finalResults) . " token stubs.");
    }

    $results = $finalResults;

    if (empty($results)) {
        debug_log("Error: No items to process");
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No items to process']);
        exit;
    }

    try {
        require_once 'qr-generator.php';
        $qrGen = new QRGenerator();

        // Just-in-time regeneration for whole cards to apply latest config
        // MUST execute before ZIP or PDF generation
        if ($downloadMode === 'whole_card') {
            $templateMap = [
                'facebook' => 'facebookcard.png',
                'instagram' => 'instagramcard.png',
                'tiktok' => 'tiktokcard.png',
                'review' => 'reviewusgoogle.png',
                'all_links' => 'all links.png'
            ];
            
            foreach ($results as $item) {
                $t = $item['token'];
                $ct = $item['card_type'];
                $templatePath = __DIR__ . '/' . ($templateMap[$ct] ?? 'all links.png');
                
                if (file_exists($templatePath)) {
                    $qrFilename = $qrGen->generateQRCode($t, 300, $ct);
                    // Match the filename pattern: card_TOKEN.png for all_links, card_TOKEN_type.png for others
                    $fullCardFilename = 'card_' . $t . ($ct === 'all_links' ? '' : '_' . $ct) . '.png';
                    $qrGen->overlayQROnTemplate($qrFilename, $templatePath, $fullCardFilename, $ct, $t);
                    debug_log("Regenerated $ct card for $t to apply latest config. File: $fullCardFilename");
                }
            }
        }

        if ($action === 'download_zip') {
            $zip = new ZipArchive();
            $zipName = "lokpix-tokens-" . time() . ".zip";
            $zipPath = sys_get_temp_dir() . '/' . $zipName;

            if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Cannot create zip file");
            }

            foreach ($results as $item) {
                if ($downloadMode === 'whole_card') {
                    $fileName = 'card_' . $item['token'] . ($item['card_type'] === 'all_links' ? '' : '_' . $item['card_type']) . '.png';
                    $filePath = QR_CARD_DIR . $fileName;
                } else {
                    $fileName = $item['token'] . '.png';
                    $filePath = QR_RAW_DIR . $fileName;
                }

                if ($fileName && file_exists($filePath)) {
                    $zip->addFile($filePath, $fileName);
                } else {
                    debug_log("ZIP: File missing: $filePath");
                }
            }
            $zip->close();

            if (ob_get_length()) ob_end_clean();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipName . '"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
            exit;
        }


        debug_log("Initializing FPDF (CMYK Mode)");
        require_once 'fpdf/fpdf.php';

        // Extension to support CMYK colors in FPDF
        if (!class_exists('PDF_CMYK')) {
            class PDF_CMYK extends FPDF {
                function SetDrawColor($c, $m=-1, $y=-1, $k=-1) {
                    if($m!=-1) {
                        $this->DrawColor=sprintf('%.3F %.3F %.3F %.3F K', $c/100, $m/100, $y/100, $k/100);
                        if($this->page>0) $this->_out($this->DrawColor);
                    } else {
                        parent::SetDrawColor($c);
                    }
                }
                function SetFillColor($c, $m=-1, $y=-1, $k=-1) {
                    if($m!=-1) {
                        $this->FillColor=sprintf('%.3F %.3F %.3F %.3F k', $c/100, $m/100, $y/100, $k/100);
                        $this->ColorFlag=($this->FillColor!=$this->TextColor);
                        if($this->page>0) $this->_out($this->FillColor);
                    } else {
                        parent::SetFillColor($c);
                    }
                }
                function SetTextColor($c, $m=-1, $y=-1, $k=-1) {
                    if($m!=-1) {
                        $this->TextColor=sprintf('%.3F %.3F %.3F %.3F k', $c/100, $m/100, $y/100, $k/100);
                        $this->ColorFlag=($this->FillColor!=$this->TextColor);
                    } else {
                        parent::SetTextColor($c);
                    }
                }
            }
        }

        $pdf = new PDF_CMYK('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false);
        // Use CMYK Black (0,0,0,100)
        $pdf->SetTextColor(0, 0, 0, 100);
        $pdf->SetFont('Arial', 'B', 12);

        $xStart = 10;
        $yStart = 15;
        $cellWidth = 63;
        $cellHeight = 65;
        $col = 0;
        $row = 0;

        $layout = $input['layout'] ?? 'grid';
        debug_log("Layout selected: $layout");

        // Layout Configuration
        $colsLimit = 3;
        $rowsLimit = 4;
        $cellWidth = 63;
        $cellHeight = 65;
        $imgSize = 42;
        $imgOffset = 10.5;
        $textOffset = 50;

        if ($layout === 'compact') {
            $colsLimit = 4;
            $rowsLimit = 5;
            $cellWidth = 47.5;
            $cellHeight = 54;
            $imgSize = 32;
            $imgOffset = 7.75;
            $textOffset = 40;
        } elseif ($layout === 'a4_single') {
            // One card per A4 page, centered
            // Card dimensions based on card type
            $cardWidth = ($cardType === 'all_links') ? 130 : 150; // 13cm or 15cm in mm
            $cardHeight = ($cardType === 'all_links') ? 180 : 150; // 18cm or 15cm in mm
        } elseif ($layout === 'roll_1m') {
            // 1 meter (1000mm) width roll
            // Calculate optimal grid for 1000mm width
            $cardWidth = ($cardType === 'all_links') ? 130 : 150;
            $cardHeight = ($cardType === 'all_links') ? 180 : 150;
            $colsLimit = floor(1000 / $cardWidth); // How many cards fit in 1000mm width
            $rowsLimit = 999; // Unlimited height
        }

        foreach ($results as $item) {
            $currentType = $item['card_type'] ?? $cardType;
            
            // If in whole_card mode, use the newly generated "photo" design card
            if ($downloadMode === 'whole_card') {
                $fileName = 'card_' . $item['token'] . ($currentType === 'all_links' ? '' : '_' . $currentType) . '.png';
                $dir = QR_CARD_DIR;
            } else {
                $fileName = $item['token'] . '.png';
                $dir = QR_RAW_DIR;
            }
            
            $imagePath = $dir . $fileName;

            debug_log("Processing token: " . $item['token'] . " | path: " . ($fileName ?? 'NULL'));
            
            if ($layout === 'a4_single') {
                // thankyou_only: skip main card — TY pages are generated below
                if ($cardType === 'thankyou_only') {
                    continue;
                }
                // One card per A4 page, centered
                $pdf->AddPage();
                $pageWidth = 210;
                $pageHeight = 297;
                
                $cw = ($currentType === 'all_links') ? 130 : 150;
                $ch = ($currentType === 'all_links') ? 180 : 150;
                
                $x = ($pageWidth - $cw) / 2;
                $y = ($pageHeight - $ch) / 2;
                
                if (file_exists($imagePath)) {
                    $pdf->Image($imagePath, $x, $y, $cw, $ch);
                }
                
                continue;
            }
            
            if ($layout === 'roll_1m') {
                // For thankyou_only, skip the roll block — thank-you pages are appended after the main loop
                if ($cardType === 'thankyou_only') {
                    continue; // no main card to draw; TY page added in the block below
                }
                // 1 meter (1000mm) width roll
                // Each token occupies: [main card] + [thank you card] side by side
                // Separator line is drawn between each row

                $spacing     = 5;   // mm gap between cards in a row
                $rowSpacing  = 8;   // mm gap between rows
                $lineThick   = 0.3; // mm dashed line thickness
                $maxW        = 1000;
                $cardW       = 130; // all_links card width
                $cardH       = 180; // all_links card height
                $tyW         = 105; // A6 width
                $tyH         = 148; // A6 height
                $pairW       = $cardW + ($includeThankyou ? $spacing + $tyW : 0);

                // Pack tokens into rows that fit within 1000mm
                $rows = [];
                $currentRow = [];
                $currentRowW = 0;

                foreach ($results as $ri) {
                    if ($currentRowW + $pairW + $spacing > $maxW && !empty($currentRow)) {
                        $rows[] = $currentRow;
                        $currentRow = [];
                        $currentRowW = 0;
                    }
                    $currentRow[] = $ri;
                    $currentRowW += $pairW + $spacing;
                }
                if (!empty($currentRow)) $rows[] = $currentRow;

                // Calculate total PDF height
                $totalH = 10;
                foreach ($rows as $r) {
                    $totalH += $cardH + $rowSpacing;
                }
                $totalH += 10; // bottom margin

                $pdf->SetMargins(0, 0, 0);
                if (1000 > $totalH) {
                    $pdf->AddPage('L', [$totalH, 1000]);
                } else {
                    $pdf->AddPage('P', [1000, $totalH]);
                }

                // Fetch thank-you data for all tokens at once
                $thankYouData = [];
                if ($includeThankyou) {
                    foreach ($results as $ri) {
                        $stmtTY = $db->prepare("SELECT t.token, t.password_plain, lp.profile_name FROM tokens t LEFT JOIN link_pages lp ON t.id = lp.token_id WHERE t.token = ?");
                        $stmtTY->execute([$ri['token']]);
                        $row = $stmtTY->fetch(PDO::FETCH_ASSOC);
                        $thankYouData[$ri['token']] = [
                            'name' => $row['profile_name'] ?? $ri['token'],
                            'pass' => $row['password_plain'] ?? '--------',
                        ];
                    }
                }

                $yPos = 10;
                $isFirstRow = true;
                foreach ($rows as $pairRow) {
                    // Draw dashed separator line between rows (not before first row)
                    if (!$isFirstRow) {
                        $pdf->SetDrawColor(0, 0, 0, 30); // light grey
                        $pdf->SetLineWidth($lineThick);
                        // Dashed line: draw as a series of short segments
                        $dashLen = 8; $gapLen = 4;
                        $lineY = $yPos - $rowSpacing / 2;
                        $xd = 0;
                        while ($xd < 1000) {
                            $pdf->Line($xd, $lineY, min($xd + $dashLen, 1000), $lineY);
                            $xd += $dashLen + $gapLen;
                        }
                        $pdf->SetDrawColor(0, 0, 0, 100);
                        $pdf->SetLineWidth(0.2);
                    }
                    $isFirstRow = false;

                    $xPos = 10;
                    foreach ($pairRow as $pairItem) {
                        $ct = $pairItem['card_type'] ?? 'all_links';
                        $fName = 'card_' . $pairItem['token'] . ($ct === 'all_links' ? '' : '_' . $ct) . '.png';
                        $iPath = QR_CARD_DIR . $fName;

                        // Draw main card
                        if (file_exists($iPath)) {
                            $pdf->Image($iPath, $xPos, $yPos, $cardW, $cardH);
                        } else {
                            // Placeholder box if card is missing
                            $pdf->SetDrawColor(0, 0, 0, 20);
                            $pdf->Rect($xPos, $yPos, $cardW, $cardH, 'D');
                        }

                        // Draw Thank You card beside it (same row) — uses shared drawThankYouCard
                        if ($includeThankyou) {
                            $tyX = $xPos + $cardW + $spacing;
                            $ty  = $thankYouData[$pairItem['token']] ?? ['name' => $pairItem['token'], 'pass' => ''];
                            drawThankYouCard($pdf, $pairItem['token'], $pairItem['token'], $ty['pass'], $tyX, $yPos, $tyW, $tyH);
                        }

                        $xPos += $pairW + $spacing;
                    }

                    $yPos += $cardH + $rowSpacing;
                }

                // Done — break out so the generic loop doesn't run
                break;
            }
            
            if ($layout === 'single') {
                // Dynamic page size based on type to avoid squashing
                $iw = ($currentType === 'all_links') ? 130 : 150;
                $ih = ($currentType === 'all_links') ? 180 : 150;
                
                // Add page with margins
                $pdf->AddPage('P', [$iw + 20, $ih + 20]);
                
                if (file_exists($imagePath)) {
                    $pdf->Image($imagePath, 10, 10, $iw, $ih);
                }
                
                if ($downloadMode !== 'whole_card') {
                    $pdfFontSizeSingle = $qrGen->tokenFontSize * 0.46; // scale for single page
                    $pdfYAdjSingle = $qrGen->tokenYOffset * 0.25;
                    $pdfXAdjSingle = $qrGen->tokenXOffset * 0.25;

                    $pdf->SetFont('Arial', 'B', $pdfFontSizeSingle);
                    $pdf->SetXY(5 + $pdfXAdjSingle, $ih + 12 + $pdfYAdjSingle);
                    $pdf->Cell($iw + 10, 10, $item['token'], 0, 0, 'C');
                }
                continue;
            }

            if ($col === 0 && $row === 0) {
                $pdf->AddPage();
            }

            if ($col >= $colsLimit) {
                $col = 0;
                $row++;
            }
            if ($row >= $rowsLimit) {
                $pdf->AddPage();
                $col = 0;
                $row = 0;
            }

            $x = $xStart + ($col * $cellWidth);
            $y = $yStart + ($row * $cellHeight);

            if (file_exists($imagePath)) {
                // Respect aspect ratio even in grid
                $ratio = ($currentType === 'all_links') ? (18/13) : 1;
                $iw = $imgSize;
                $ih = $imgSize;
                
                if ($ratio > 1) { // Tall card - preserve height, shrink width
                    $iw = $ih / $ratio;
                }
                
                $ioff = $imgOffset + ($imgSize - $iw) / 2;
                $pdf->Image($imagePath, $x + $ioff, $y + 5, $iw, $ih);
            }

            // Only draw extra text if NOT in whole_card mode
            if ($downloadMode !== 'whole_card') {
                // Approximate conversion: 1 GD px ≈ 0.264 mm for offsets, and font sizes are in pt
                // Base grid font is 12pt (roughly 16px). If user sets 30px, we scale.
                $pdfFontSize = $qrGen->tokenFontSize * 0.4; // 30px -> 12pt
                $pdfYAdjustment = $qrGen->tokenYOffset * 0.25; // 6px -> 1.5mm
                $pdfXAdjustment = $qrGen->tokenXOffset * 0.25;

                $pdf->SetXY($x + $pdfXAdjustment, $y + $textOffset + $pdfYAdjustment);
                $pdf->SetFont('Arial', 'B', $layout === 'compact' ? ($pdfFontSize * 0.8) : $pdfFontSize);
                $pdf->Cell($cellWidth, 10, $item['token'], 0, 0, 'C');
                
                $pdf->SetFont('Arial', '', $layout === 'compact' ? 7 : 8);
                $pdf->SetXY($x + $pdfXAdjustment, $y + $textOffset + $pdfYAdjustment + 6);
                $pdf->Cell($cellWidth, 5, 'linkydz.com', 0, 0, 'C');
            }
            $pdf->SetFont('Arial', 'B', 12);

            $col++;
        }

        debug_log("PDF Output generation");
        if (ob_get_length()) {
            $buffer_content = ob_get_contents();
            if (!empty($buffer_content)) {
                debug_log("Warning: Buffer was not empty before PDF output. Length: " . strlen($buffer_content));
                debug_log("Buffer start: " . substr($buffer_content, 0, 500));
            }
            ob_end_clean();
        }

        // ── Thank-You Only on 1 Meter Roll ──────────────────────────────────────
        // Pack TY cards (A6) side-by-side in rows within 1000mm width
        if ($cardType === 'thankyou_only' && $layout === 'roll_1m') {
            $tyW       = 105;  // A6 width
            $tyH       = 148;  // A6 height
            $spacing   = 5;    // gap between cards in same row
            $rowSpacing= 8;    // gap between rows
            $lineThick = 0.3;
            $maxW      = 1000;
            $margin    = 10;

            // Fetch all TY data first
            $tyDataMap = [];
            foreach ($results as $ri) {
                $stmtTY = $db->prepare("SELECT t.password_plain, lp.profile_name FROM tokens t LEFT JOIN link_pages lp ON t.id = lp.token_id WHERE t.token = ?");
                $stmtTY->execute([$ri['token']]);
                $row = $stmtTY->fetch(PDO::FETCH_ASSOC);
                $tyDataMap[$ri['token']] = [
                    'name' => $row['profile_name'] ?? $ri['token'],
                    'pass' => $row['password_plain'] ?? '--------',
                ];
            }

            // Pack tokens into rows
            $pairW    = $tyW + $spacing;
            $perRow   = max(1, floor(($maxW - $margin * 2) / $pairW));
            $rowsArr  = array_chunk($results, $perRow);

            // Calculate total PDF height
            $totalH = $margin * 2 + count($rowsArr) * ($tyH + $rowSpacing);

            $pdf->SetMargins(0, 0, 0);
            if (1000 > $totalH) {
                $pdf->AddPage('L', [$totalH, 1000]);
            } else {
                $pdf->AddPage('P', [1000, $totalH]);
            }

            $yPos      = $margin;
            $isFirstRow = true;
            foreach ($rowsArr as $rowGroup) {
                // Dashed separator between rows
                if (!$isFirstRow) {
                    $pdf->SetDrawColor(0, 0, 0, 30);
                    $pdf->SetLineWidth($lineThick);
                    $dashLen = 8; $gapLen = 4;
                    $lineY = $yPos - $rowSpacing / 2;
                    $xd = 0;
                    while ($xd < 1000) {
                        $pdf->Line($xd, $lineY, min($xd + $dashLen, 1000), $lineY);
                        $xd += $dashLen + $gapLen;
                    }
                    $pdf->SetDrawColor(0, 0, 0, 100);
                    $pdf->SetLineWidth(0.2);
                }
                $isFirstRow = false;

                $xPos = $margin;
                foreach ($rowGroup as $pairItem) {
                    $ty = $tyDataMap[$pairItem['token']] ?? ['name' => $pairItem['token'], 'pass' => ''];
                    drawThankYouCard($pdf, $pairItem['token'], $pairItem['token'], $ty['pass'], $xPos, $yPos, $tyW, $tyH);
                    $xPos += $pairW;
                }
                $yPos += $tyH + $rowSpacing;
            }
        }

        // ── Thank-You pages for non-roll layouts (all_links_thankyou or thankyou_only + a4_single) ──
        if ($includeThankyou && $layout !== 'roll_1m') {
            $tyCount = 0;
            $tw = 105; // A6 width
            $th = 148; // A6 height
            $pageW = 210;
            $pageH = 297;
            
            foreach ($results as $item) {
                $stmtTC = $db->prepare("SELECT t.password_plain, lp.profile_name FROM tokens t LEFT JOIN link_pages lp ON t.id = lp.token_id WHERE t.token = ?");
                $stmtTC->execute([$item['token']]);
                $tcData = $stmtTC->fetch(PDO::FETCH_ASSOC);
                $tcName = $tcData['profile_name'] ?? $item['token'];
                $tcPass = $tcData['password_plain'] ?? '--------';
                
                // 4 cards per A4 page (2x2 grid)
                if ($tyCount % 4 === 0) {
                    $pdf->AddPage();
                }
                
                $col = $tyCount % 2;
                $row = floor(($tyCount % 4) / 2);
                
                // Calculate position to center in quadrant
                $qx = ($pageW / 2) * $col;
                $qy = ($pageH / 2) * $row;
                $x = $qx + (($pageW / 2) - $tw) / 2;
                $y = $qy + (($pageH / 2) - $th) / 2;
                
                drawThankYouCard($pdf, $item['token'], $item['token'], $tcPass, $x, $y, $tw, $th);
                $tyCount++;
            }
        }
        
        debug_log("PDF Output generation (CMYK via Python rendering)");
        if (ob_get_length()) ob_end_clean();
        
        $temp_rgb_pdf = __DIR__ . '/temp_rgb_' . time() . '.pdf';
        $output_cmyk_pdf = __DIR__ . '/lokpix-labels-cmyk-' . time() . '.pdf';
        
        try {
            // 1. Generate standard PDF to temporary file
            $pdf->Output('F', $temp_rgb_pdf);
            debug_log("Temporary RGB PDF created: " . basename($temp_rgb_pdf));

            // 2. Call Python to convert to CMYK
            // Override with PYTHON_BIN in .env when needed.
            $pythonPath = getenv('PYTHON_BIN') ?: "python";

            if (!getenv('PYTHON_BIN') && strpos(__DIR__, '/home/') === 0) {
                // Auto-detect a cPanel virtualenv next to the account home.
                $parts = explode('/', __DIR__);
                $user = $parts[2] ?? '';
                $possibleVenv = "/home/$user/virtualenv/pdf/3.12/bin/python";
                if (file_exists($possibleVenv)) {
                    $pythonPath = $possibleVenv;
                    debug_log("Auto-detected local venv python: $pythonPath");
                }
            }

            $scriptPath = __DIR__ . "/convert_pdf_cmyk.py";
            $command = "$pythonPath " . escapeshellarg($scriptPath) . " " . escapeshellarg($temp_rgb_pdf) . " " . escapeshellarg($output_cmyk_pdf) . " --dpi 300 2>&1";
            
            debug_log("Executing command: $command");
            $command_output = [];
            $return_var = -1;
            if (function_exists('exec')) {
                exec($command, $command_output, $return_var);
            } elseif (function_exists('proc_open')) {
                $descriptors = [['pipe','r'], ['pipe','w'], ['pipe','w']];
                $proc = @proc_open($command, $descriptors, $pipes);
                if (is_resource($proc)) {
                    $command_output = explode("\n", stream_get_contents($pipes[1]));
                    $return_var = proc_close($proc);
                } else {
                    debug_log("ERROR: proc_open also unavailable");
                    $command_output = ["Command execution disabled"];
                    $return_var = -1;
                }
            } else {
                debug_log("ERROR: exec and proc_open both disabled on this server");
                $command_output = ["Command execution disabled"];
                $return_var = -1;
            }
            
            debug_log("Python Return Var: $return_var");
            if ($return_var !== 0) {
                debug_log("Python Error Output: " . implode("\n", $command_output));
                // Check if PyMuPDF is installed if we get a core error
                if (stripos(implode(" ", $command_output), 'ModuleNotFoundError') !== false) {
                    debug_log("CRITICAL: Python modules missing. Please run 'pip install -r requirements.txt' in your Python app.");
                }
            }

            if ($return_var === 0 && file_exists($output_cmyk_pdf)) {
                debug_log("Serving CMYK PDF");
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="lokpix-labels-cmyk.pdf"');
                header('Content-Length: ' . filesize($output_cmyk_pdf));
                readfile($output_cmyk_pdf);
                
                // Cleanup
                @unlink($temp_rgb_pdf);
                @unlink($output_cmyk_pdf);
                debug_log("PDF Output Success (CMYK)");
                exit;
            } else {
                throw new Exception("CMYK Conversion failed or output file missing.");
            }
        } catch (Exception $e) {
            debug_log("CMYK Conversion Error: " . $e->getMessage());
            // Fallback to RGB if Python fails
            debug_log("Falling back to RGB PDF");
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="lokpix-labels.pdf"');
            $pdf->Output('I', 'lokpix-labels.pdf');
            if (file_exists($temp_rgb_pdf)) @unlink($temp_rgb_pdf);
            exit;
        }
    } catch (Exception $e) {
        debug_log("PDF Error: " . $e->getMessage());
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'PDF Generation error: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'update_batch') {
    $ids = $input['token_ids'] ?? [];
    $reference = $input['reference'] ?? null;
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'No IDs provided']);
        exit;
    }
    
    $ids = array_filter($ids, fn($id) => is_numeric($id));
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'Invalid IDs']);
        exit;
    }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $params = array_merge([$reference], $ids);
    
    try {
        $stmt = $db->prepare("UPDATE tokens SET reference = ? WHERE id IN ($placeholders)");
        $success = $stmt->execute($params);
        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_batch') {
    $ids = $input['token_ids'] ?? [];
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'No IDs provided']);
        exit;
    }
    
    // Filter out invalid/empty IDs
    $ids = array_filter($ids, fn($id) => is_numeric($id));
    
    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'Invalid IDs']);
        exit;
    }

    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    try {
        // 1. Get file paths to delete from disk
        $stmt = $db->prepare("SELECT qr_code_path, card_image_path FROM qr_cards WHERE token_id IN ($placeholders)");
        $stmt->execute($ids);
        $files = $stmt->fetchAll();

        // 2. Delete TOKENS (cascades to pages/cards in DB)
        $stmt = $db->prepare("DELETE FROM tokens WHERE id IN ($placeholders)");
        $success = $stmt->execute($ids);
        
        // 3. Physical file cleanup (after successful DB deletion)
        if ($success) {
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
        }
        
        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
