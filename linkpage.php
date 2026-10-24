<?php
require_once 'config.php';
require_once 'database.php';
require_once 'link-manager.php';
require_once 'design-manager.php';

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(404);
    include '404.php';
    exit;
}

// Check for sub-token format: TOKEN.SUBTYPE (e.g., ABC123.facebook)
$subType = null;
$baseToken = $token;

if (strpos($token, '.') !== false) {
    $parts = explode('.', $token);
    $baseToken = $parts[0];
    $subType = isset($parts[1]) ? rtrim($parts[1], '#') : null;
}

// Validate base token length
if (strlen($baseToken) !== 6) {
    http_response_code(404);
    include '404.php';
    exit;
}

// Get link page by token
$linkManager = new LinkManager();
$linkPage = $linkManager->getLinkPageByToken($baseToken);

if (!$linkPage) {
    http_response_code(404);
    include '404.php';
    exit;
}

// If this is a sub-token request (e.g., token.facebook), handle redirect
if ($subType) {
    // Get all links for this page
    $links = $linkManager->getActiveLinks($linkPage['id']);
    $redirectUrl = null;

    foreach ($links as $l) {
        $url = strtolower($l['url']);
        $title = strtolower($l['title']);
        
        $match = false;
        switch ($subType) {
            case 'facebook':
                if (strpos($url, 'facebook.com') !== false || strpos($title, 'facebook') !== false) $match = true;
                break;
            case 'instagram':
                if (strpos($url, 'instagram.com') !== false || strpos($title, 'instagram') !== false) $match = true;
                break;
            case 'tiktok':
                if (strpos($url, 'tiktok.com') !== false || strpos($title, 'tiktok') !== false) $match = true;
                break;
            case 'review':
                // For review, look for google or review in URL/title
                if (strpos($url, 'google.com') !== false || strpos($title, 'review') !== false || strpos($url, 'search?q=') !== false) $match = true;
                break;
        }

        if ($match) {
            $redirectUrl = $l['url'];
            break;
        }
    }

    if ($redirectUrl) {
        // Log click for analytics? (Optional for now)
        header("Location: " . $redirectUrl);
        exit;
    } else {
        // Redirect to main page if specific link not found
        header("Location: " . BASE_URL . "/u/" . $baseToken);
        exit;
    }
}

// Get active links
$links = $linkManager->getActiveLinks($linkPage['id']);

// Get design settings
$designManager = new DesignManager();
$design = $designManager->getDesignSettings($linkPage['id']);

// Extract design values
$bgColor = $design['background_color'] ?? '#0F172A';
$btnColor = $design['button_color'] ?? '#3B82F6';
$textColor = $design['text_color'] ?? '#FFFFFF';
$fontFamily = $design['font_family'] ?? 'Inter';
$buttonStyle = $design['button_style'] ?? 'rounded';
$profileName = htmlspecialchars($linkPage['profile_name']);
$phoneNumber = htmlspecialchars($linkPage['phone_number'] ?? '');

// Helper to ensure URLs have a protocol
function normalizeUrl($url) {
    if (!$url) return '#';
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        return "https://" . $url;
    }
    return $url;
}

// Helper to get social icon based on URL or Title
function getSocialIcon($url, $title) {
    $url = strtolower($url);
    $title = strtolower($title);
    
    if (strpos($url, 'instagram.com') !== false || strpos($title, 'instagram') !== false) return 'fab fa-instagram';
    if (strpos($url, 'facebook.com') !== false || strpos($title, 'facebook') !== false) return 'fab fa-facebook';
    if (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false || strpos($title, 'twitter') !== false || strpos($title, 'x.com') !== false) return 'fab fa-twitter';
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false || strpos($title, 'youtube') !== false) return 'fab fa-youtube';
    if (strpos($url, 'tiktok.com') !== false || strpos($title, 'tiktok') !== false) return 'fab fa-tiktok';
    if (strpos($url, 'linkedin.com') !== false || strpos($title, 'linkedin') !== false) return 'fab fa-linkedin';
    if (strpos($url, 'github.com') !== false || strpos($title, 'github') !== false) return 'fab fa-github';
    if (strpos($url, 'wa.me') !== false || strpos($url, 'whatsapp.com') !== false || strpos($title, 'whatsapp') !== false) return 'fab fa-whatsapp';
    if (strpos($url, 't.me') !== false || strpos($title, 'telegram') !== false) return 'fab fa-telegram';
    if (strpos($url, 'spotify.com') !== false || strpos($title, 'spotify') !== false) return 'fab fa-spotify';
    if (strpos($url, 'twitch.tv') !== false || strpos($title, 'twitch') !== false) return 'fab fa-twitch';
    if (strpos($url, 'discord') !== false || strpos($title, 'discord') !== false) return 'fab fa-discord';
    if (strpos($url, 'snapchat.com') !== false || strpos($title, 'snapchat') !== false) return 'fab fa-snapchat';
    if (strpos($url, 'pinterest.com') !== false || strpos($title, 'pinterest') !== false) return 'fab fa-pinterest';
    
    return 'fas fa-link'; // Default icon
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profileName; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <meta name="description" content="<?php echo $profileName; ?>'s links">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Roboto:wght@400;700&family=Poppins:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        :root {
            --primary: <?php echo $btnColor; ?>;
            --bg-start: <?php echo $bgColor; ?>;
            --bg-end: <?php echo ($bgColor === '#ffffff' || $bgColor === '#FFFFFF') ? '#f3f4f6' : $bgColor . 'e6'; ?>;
            --text-primary: <?php echo $textColor; ?>;
            --text-secondary: <?php echo ($textColor === '#000000' || $textColor === '#000') ? 'rgba(0,0,0,0.6)' : 'rgba(255,255,255,0.7)'; ?>;
            --glass-bg: <?php echo ($design['template_id']??'') === 'simple' ? 'rgba(0, 0, 0, 0.02)' : 'rgba(255, 255, 255, 0.03)'; ?>;
            --glass-border: <?php echo ($design['template_id']??'') === 'simple' ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)'; ?>;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.3);
            --blur-amount: <?php echo (($design['template_id']??'') === 'image') ? '10px' : '40px'; ?>;
        }
        
        body {
            font-family: '<?php echo $fontFamily; ?>', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: clamp(1.5rem, 5vw, 3rem) clamp(1rem, 4vw, 1.5rem);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* --- TEMPLATE SPECIALIZATIONS --- */

        <?php if (($design['template_id']??'') === 'simple'): ?>
            body { background: <?php echo $bgColor; ?> !important; color: <?php echo $textColor; ?> !important; }
            .link-button { background: rgba(255,255,255,0.07) !important; border: 1px solid rgba(255,255,255,0.12) !important; color: <?php echo $textColor; ?> !important; }
            .link-button:hover { background: rgba(255,255,255,0.14) !important; transform: translateY(-2px) !important; }
            .avatar { background: linear-gradient(135deg, <?php echo $btnColor; ?>, #5E5CE6) !important; color: #ffffff !important; }
            .profile-name { color: <?php echo $textColor; ?> !important; }
            .profile-bio { color: <?php echo ($textColor === '#000000' || $textColor === '#000') ? 'rgba(0,0,0,0.55)' : 'rgba(255,255,255,0.65)'; ?> !important; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'image' && !empty($design['bg_image'])): ?>
            body {
                background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('<?php echo BG_URL . $design['bg_image']; ?>') !important;
                background-size: cover !important;
                background-position: center !important;
                background-attachment: fixed !important;
            }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'particles'): ?>
            body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%) !important; }
            .blob-1 { background: radial-gradient(circle, #22d3ee 0%, transparent 70%) !important; }
            .blob-2 { background: radial-gradient(circle, #818cf8 0%, transparent 70%) !important; }
            .blob-3 { background: radial-gradient(circle, #c084fc 0%, transparent 70%) !important; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'mesh'): ?>
            body {
                background: linear-gradient(45deg, #f43f5e, #fb923c, #3b82f6, #8b5cf6) !important;
                background-size: 400% 400% !important;
                animation: meshGradient 15s ease infinite !important;
            }
            @keyframes meshGradient {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'glass'): ?>
            /* Enhanced blobs for glass template */
            .blob { opacity: 0.6 !important; filter: blur(120px) !important; }
            .blob-1 { background: radial-gradient(circle, #6366f1 0%, transparent 70%) !important; }
            .blob-2 { background: radial-gradient(circle, #f43f5e 0%, transparent 70%) !important; }
            .blob-3 { background: radial-gradient(circle, #a855f7 0%, transparent 70%) !important; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'aurora'): ?>
            body { background: linear-gradient(to bottom, #000428, #004e92) !important; }
            .blob-1 { background: radial-gradient(circle, #00ff87 0%, transparent 70%) !important; opacity: 0.3 !important; }
            .blob-2 { background: radial-gradient(circle, #60efff 0%, transparent 70%) !important; opacity: 0.2 !important; }
            .blob-3 { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'sunset'): ?>
            body { background: linear-gradient(to top, #41295a, #2f0743) !important; }
            .blob-1 { background: radial-gradient(circle, #ff5e62 0%, transparent 70%) !important; opacity: 0.4 !important; }
            .blob-2 { background: radial-gradient(circle, #ff9966 0%, transparent 70%) !important; opacity: 0.3 !important; }
            .blob-3 { background: radial-gradient(circle, #833ab4 0%, transparent 70%) !important; opacity: 0.2 !important; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'social'): ?>
            body { background: #fafafa !important; color: #262626 !important; }
            .glass-card { background: #ffffff !important; border: 1px solid #dbdbdb !important; border-radius: 4px !important; box-shadow: none !important; }
            .link-button { background: #efefef !important; border: none !important; color: #262626 !important; border-radius: 8px !important; justify-content: flex-start !important; padding: 1rem 1.5rem !important; }
            .link-button:hover { background: #dbdbdb !important; transform: none !important; }
            .link-icon { color: <?php echo $btnColor; ?> !important; margin-right: 1.5rem !important; font-size: 1.5rem !important; }
            .link-button i { margin-left: auto !important; margin-right: 0 !important; color: #262626 !important; }
            .avatar { border: 2px solid <?php echo $btnColor; ?> !important; padding: 3px !important; background: transparent !important; color: #262626 !important; }
            .profile-name { color: #262626 !important; font-weight: 600 !important; font-size: 1.25rem !important; }
            .profile-bio { color: #8e8e8e !important; font-weight: 400 !important; }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'neon'): ?>
            body { background: <?php echo $bgColor; ?> !important; }
            .glass-card { border: 2px solid <?php echo $btnColor; ?> !important; box-shadow: 0 0 20px <?php echo $btnColor; ?>33 !important; }
            .link-button { border-color: <?php echo $btnColor; ?> !important; color: <?php echo $btnColor; ?> !important; text-shadow: 0 0 5px <?php echo $btnColor; ?>80 !important; }
            .link-button:hover { background: <?php echo $btnColor; ?>1a !important; box-shadow: 0 0 15px <?php echo $btnColor; ?>4d !important; }
            .profile-name { color: <?php echo $btnColor; ?> !important; text-shadow: 0 0 10px <?php echo $btnColor; ?>80 !important; }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'tropical'): ?>
            body { background: linear-gradient(135deg, #02aab0 0%, #00cdac 100%) !important; }
            .link-button { background: rgba(255,255,255,0.1) !important; border-color: rgba(255,255,255,0.2) !important; }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'minimal'): ?>
            body { background: #09090b !important; }
            .glass-card { background: rgba(255,255,255,0.01) !important; border: 1px solid rgba(255,255,255,0.05) !important; }
            .link-button { background: transparent !important; border-color: rgba(255,255,255,0.1) !important; color: #a1a1aa !important; }
            .link-button:hover { color: #fff !important; border-color: #fff !important; }
            .avatar { border-radius: 50% !important; }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'geometry'): ?>
            body { 
                background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%) !important;
                overflow-x: hidden;
            }
            .geometry-bg {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
                pointer-events: none;
            }
            .shape {
                position: absolute;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(5px);
                animation: floatShape var(--duration) infinite linear;
            }
            .shape.square { border-radius: 20%; }
            .shape.circle { border-radius: 50%; }
            
            @keyframes floatShape {
                0% { transform: translateY(110vh) rotate(0deg); opacity: 0; }
                10% { opacity: 1; }
                90% { opacity: 1; }
                100% { transform: translateY(-20vh) rotate(360deg); opacity: 0; }
            }
            .glass-card {
                background: rgba(255, 255, 255, 0.03) !important;
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3) !important;
            }
            .link-button {
                background: rgba(255, 255, 255, 0.05) !important;
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
            }
            .liquid-bg { display: none; }
        <?php endif; ?>

        <?php if (($design['template_id']??'') === 'galaxy'): ?>
            body { 
                background: #000000 !important; 
                margin: 0;
                overflow-x: hidden;
            }
            .galaxy-bg {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 0;
                background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%);
            }
            .star {
                position: absolute;
                background: white;
                border-radius: 50%;
                opacity: 0.5;
                animation: twinkle var(--duration) infinite ease-in-out;
            }
            @keyframes twinkle {
                0%, 100% { opacity: 0.3; transform: scale(1); }
                50% { opacity: 1; transform: scale(1.2); }
            }
            .glass-card {
                background: rgba(255, 255, 255, 0.05) !important;
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.5) !important;
            }
            .liquid-bg { display: none; }
        <?php endif; ?>

        /* --- END TEMPLATE SPECIALIZATIONS --- */
        
        /* Enhanced Liquid Blobs */
        .liquid-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.4;
            mix-blend-mode: screen;
            will-change: transform;
        }
        
        .blob-1 { 
            width: min(70vw, 700px);
            height: min(70vw, 700px);
            top: -20%;
            left: -15%;
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            animation: blob1 30s infinite alternate ease-in-out;
        }
        
        .blob-2 { 
            width: min(60vw, 600px);
            height: min(60vw, 600px);
            bottom: -25%;
            right: -20%;
            background: radial-gradient(circle, #FF2D55 0%, transparent 70%);
            animation: blob2 35s infinite alternate ease-in-out;
            animation-delay: -5s;
        }
        
        .blob-3 { 
            width: min(50vw, 500px);
            height: min(50vw, 500px);
            top: 30%;
            right: -15%;
            background: radial-gradient(circle, #5E5CE6 0%, transparent 70%);
            animation: blob3 40s infinite alternate ease-in-out;
            animation-delay: -10s;
        }
        
        @keyframes blob1 {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(15%, 10%) scale(1.15) rotate(90deg); }
            50% { transform: translate(-10%, 20%) scale(0.95) rotate(180deg); }
            75% { transform: translate(10%, -15%) scale(1.1) rotate(270deg); }
        }
        
        @keyframes blob2 {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            33% { transform: translate(-20%, -15%) scale(1.2) rotate(120deg); }
            66% { transform: translate(15%, 10%) scale(0.9) rotate(240deg); }
        }
        
        @keyframes blob3 {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            40% { transform: translate(-15%, -20%) scale(1.1) rotate(160deg); }
            80% { transform: translate(10%, 15%) scale(0.95) rotate(320deg); }
        }
        
        /* Container */
        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: min(420px, 100%);
            animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Premium Glass Card */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur-amount)) saturate(200%);
            -webkit-backdrop-filter: blur(var(--blur-amount)) saturate(200%);
            border: 1px solid var(--glass-border);
            border-radius: clamp(2rem, 5vw, 2.5rem);
            padding: clamp(2rem, 6vw, 3rem) clamp(1.5rem, 5vw, 2rem);
            box-shadow: 
                var(--shadow-lg),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 0 rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        /* Dynamic Shine */
        .glass-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                60deg,
                transparent 30%,
                rgba(255, 255, 255, 0.04) 50%,
                transparent 70%
            );
            animation: shine 15s infinite linear;
            pointer-events: none;
        }
        
        @keyframes shine {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Profile Section */
        .profile {
            text-align: center;
            margin-bottom: clamp(2rem, 5vw, 3rem);
            position: relative;
        }
        
        .avatar-container {
            position: relative;
            width: clamp(100px, 25vw, 120px);
            height: clamp(100px, 25vw, 120px);
            margin: 0 auto clamp(1rem, 3vw, 1.5rem);
        }
        
        .avatar {
            width: 100%;
            height: 100%;
            border-radius: clamp(1.5rem, 4vw, 2rem);
            background: linear-gradient(135deg, var(--primary) 0%, #5E5CE6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(2.5rem, 8vw, 3.5rem);
            font-weight: 900;
            color: white;
            box-shadow: 
                var(--shadow-md),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            animation: avatarFloat 8s ease-in-out infinite;
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 100%);
            pointer-events: none;
        }
        
        @keyframes avatarFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-8px) scale(1.02); }
        }
        
        .profile-name {
            font-size: clamp(1.5rem, 5vw, 1.875rem);
            font-weight: 800;
            margin-bottom: clamp(0.375rem, 1.5vw, 0.5rem);
            letter-spacing: -0.03em;
            line-height: 1.2;
        }
        
        .profile-bio {
            font-size: clamp(0.875rem, 3.5vw, 1rem);
            color: var(--text-secondary);
            font-weight: 500;
            line-height: 1.5;
            max-width: 90%;
            margin: 0 auto;
        }
        
        /* Links Section */
        .links {
            display: flex;
            flex-direction: column;
            gap: clamp(0.75rem, 3vw, 1rem);
        }
        
        .link-item {
            position: relative;
            animation: slideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards;
        }
        
        .link-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(0.5rem, 2vw, 0.75rem);
            width: 100%;
            padding: clamp(1rem, 4vw, 1.25rem) clamp(1.25rem, 4vw, 1.5rem);
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: clamp(1rem, 3vw, 1.25rem);
            font-weight: 600;
            font-size: clamp(0.9375rem, 3.5vw, 1.0625rem);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            touch-action: manipulation;
        }
        
        .link-button::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--primary) 0%, #5E5CE6 100%);
            opacity: 0;
            transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .link-button:hover::before,
        .link-button:active::before {
            opacity: 0.15;
        }
        
        .link-button:hover {
            transform: translateY(-3px) scale(1.01);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 
                0 12px 32px rgba(99, 102, 241, 0.2),
                0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .link-button:active {
            transform: scale(0.98);
        }
        
        .link-icon {
            font-size: clamp(1.125rem, 4vw, 1.25rem);
            position: relative;
            z-index: 1;
        }
        
        .link-text {
            position: relative;
            z-index: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Footer */
        .footer {
            margin-top: clamp(2rem, 5vw, 3rem);
            text-align: center;
            font-size: clamp(0.75rem, 2.5vw, 0.8125rem);
            color: rgba(255, 255, 255, 0.4);
            font-weight: 500;
            letter-spacing: 0.02em;
        }
        
        .footer a {
            color: var(--primary);
            text-decoration: none;
            transition: opacity 0.2s;
        }
        
        .footer a:hover {
            opacity: 0.8;
        }
        
        /* Stagger animations */
        <?php foreach ($links as $index => $link): ?>
        .link-item:nth-child(<?php echo $index + 1; ?>) {
            animation-delay: <?php echo ($index + 1) * 0.1; ?>s;
        }
        <?php endforeach; ?>
        
        /* Safe Area for iOS */
        @supports (padding: max(0px)) {
            body {
                padding-top: max(clamp(1.5rem, 5vw, 3rem), env(safe-area-inset-top));
                padding-bottom: max(clamp(1.5rem, 5vw, 3rem), env(safe-area-inset-bottom));
                padding-left: max(clamp(1rem, 4vw, 1.5rem), env(safe-area-inset-left));
                padding-right: max(clamp(1rem, 4vw, 1.5rem), env(safe-area-inset-right));
            }
        }
        
        /* Reduce motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="liquid-bg">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>

    <?php if (($design['template_id']??'') === 'galaxy'): ?>
        <div class="galaxy-bg" id="galaxyBg"></div>
    <?php endif; ?>

    <?php if (($design['template_id']??'') === 'geometry'): ?>
        <div class="geometry-bg" id="geometryBg"></div>
    <?php endif; ?>

    <div class="container">
        <div class="glass-card">
            <!-- Profile Section -->
            <div class="profile">
                <div class="avatar-container">
                    <div class="avatar">
                        <?php if ($linkPage['profile_image']): ?>
                            <img src="<?php echo PFP_URL . $linkPage['profile_image']; ?>" alt="<?php echo $profileName; ?>">
                        <?php else: ?>
                            <?php echo mb_substr($profileName, 0, 1); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <h1 class="profile-name"><?php echo $profileName; ?></h1>
                <?php if ($phoneNumber): ?>
                    <p class="profile-bio" style="margin-bottom: 0.5rem;"><?php echo $phoneNumber; ?></p>
                    <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phoneNumber); ?>" 
                       class="link-button" 
                       style="margin-bottom: 1rem; background: rgba(0, 255, 136, 0.15); border-color: rgba(0, 255, 136, 0.3); color: #00ff88;">
                        <i class="fas fa-phone link-icon"></i>
                        <span class="link-text">Call</span>
                    </a>
                <?php endif; ?>
                <p class="profile-bio">Connect with me</p>
            </div>
            
            <!-- Links Section -->
            <div class="links">
                <?php if (empty($links)): ?>
                    <p style="text-align: center; opacity: 0.5;">No links available yet</p>
                <?php else: ?>
                    <?php foreach ($links as $link): 
                        $rawUrl = $link['url'];
                        $finalUrl = normalizeUrl($rawUrl);
                        $iconClass = getSocialIcon($rawUrl, $link['title']);
                    ?>
                        <div class="link-item">
                            <a href="<?php echo htmlspecialchars($finalUrl); ?>" 
                               class="link-button" 
                               data-link-id="<?php echo $link['id']; ?>"
                               target="_blank" 
                               rel="noopener noreferrer">
                                <i class="<?php echo $iconClass; ?> link-icon"></i>
                                <span class="link-text"><?php echo htmlspecialchars($link['title']); ?></span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p>Powered by <a href="<?php echo BASE_URL; ?>"><?php echo SITE_NAME; ?></a></p>
    </div>
    
    <script>
        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Add click tracking with analytics
        document.querySelectorAll('.link-button').forEach(button => {
            button.addEventListener('click', function(e) {
                const linkId = this.dataset.linkId;
                if (linkId) {
                    // Send analytics in background (don't wait for response)
                    fetch('<?php echo BASE_URL; ?>/api-track-click', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ link_id: linkId })
                    }).catch(() => {}); // Silently fail if analytics fails
                }
            });
        });
        
        // Add subtle parallax effect
        document.addEventListener('mousemove', (e) => {
            const moveX = (e.clientX - window.innerWidth / 2) * 0.005;
            const moveY = (e.clientY - window.innerHeight / 2) * 0.005;
            const container = document.querySelector('.container');
            if(container) container.style.transform = `translate(${moveX}px, ${moveY}px)`;
        });

        // Galaxy Particle Generation
        <?php if (($design['template_id']??'') === 'galaxy'): ?>
        (function() {
            const bg = document.getElementById('galaxyBg');
            const starCount = 150;
            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star';
                const size = Math.random() * 2 + 1;
                star.style.width = size + 'px';
                star.style.height = size + 'px';
                star.style.top = Math.random() * 100 + '%';
                star.style.left = Math.random() * 100 + '%';
                star.style.setProperty('--duration', (Math.random() * 3 + 2) + 's');
                star.style.animationDelay = (Math.random() * 5) + 's';
                bg.appendChild(star);
            }
        })();
        <?php endif; ?>

        // Geometry Shape Generation
        <?php if (($design['template_id']??'') === 'geometry'): ?>
        (function() {
            const bg = document.getElementById('geometryBg');
            const shapeCount = 15;
            for (let i = 0; i < shapeCount; i++) {
                const shape = document.createElement('div');
                const isSquare = Math.random() > 0.5;
                shape.className = 'shape ' + (isSquare ? 'square' : 'circle');
                
                const size = Math.random() * 60 + 20;
                shape.style.width = size + 'px';
                shape.style.height = size + 'px';
                shape.style.left = Math.random() * 100 + '%';
                
                shape.style.setProperty('--duration', (Math.random() * 10 + 10) + 's');
                shape.style.animationDelay = (Math.random() * 15) + 's';
                
                bg.appendChild(shape);
            }
        })();
        <?php endif; ?>

        // Track page view
        fetch('<?php echo BASE_URL; ?>/api-track-pageview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                link_page_id: <?php echo $linkPage['id']; ?>,
                referrer: document.referrer
            })
        })
        .then(response => response.json())
        .catch(error => console.error('Page view tracking failed:', error));
    </script>
</body>
</html>
