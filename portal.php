<?php
session_start();
require_once 'config.php';
require_once 'database.php';

// Auth Check
if (!isset($_SESSION['portal_token_id'])) {
    header('Location: portal-login');
    exit;
}

$tokenId = $_SESSION['portal_token_id'];
$tokenStr = $_SESSION['portal_token'];

$db = Database::getInstance()->getConnection();

// Get or Create Link Page for this Token
$stmt = $db->prepare("SELECT * FROM link_pages WHERE token_id = ?");
$stmt->execute([$tokenId]);
$linkPage = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$linkPage) {
    // Edge case: card was activated but page doesn't exist? Create it.
    $stmt = $db->prepare("INSERT INTO link_pages (token_id, profile_name) VALUES (?, ?)");
    $stmt->execute([$tokenId, $tokenStr]);
    $linkPageId = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM link_pages WHERE id = ?");
    $stmt->execute([$linkPageId]);
    $linkPage = $stmt->fetch(PDO::FETCH_ASSOC);
}

$linkPageId = $linkPage['id'];

// Handling Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: portal-login');
    exit;
}

// Fetch Links
$stmt = $db->prepare("SELECT * FROM links WHERE link_page_id = ? ORDER BY id DESC");
$stmt->execute([$linkPageId]);
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Design Settings
$stmt = $db->prepare("SELECT * FROM design_settings WHERE link_page_id = ?");
$stmt->execute([$linkPageId]);
$designSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Apply defaults
$designSettings['template_id'] = current(explode(',', $designSettings['template_id'] ?? 'simple'));
$designSettings['font_family'] = current(explode(',', $designSettings['font_family'] ?? 'Inter'));
$bgImage = $designSettings['bg_image'] ?? '';

// Analytics: Overall Page Views
$stmt = $db->prepare("SELECT COUNT(*) FROM page_analytics WHERE link_page_id = ?");
$stmt->execute([$linkPageId]);
$totalViews = $stmt->fetchColumn();

// Analytics: Views over time (7 days)
$chartDatesStmt = $db->prepare("
    SELECT DATE(viewed_at) as view_date, COUNT(*) as views 
    FROM page_analytics 
    WHERE link_page_id = ? AND viewed_at >= DATE(NOW()) - INTERVAL 6 DAY
    GROUP BY DATE(viewed_at)
    ORDER BY view_date ASC
");
$chartDatesStmt->execute([$linkPageId]);
$viewsOverTime = $chartDatesStmt->fetchAll(PDO::FETCH_ASSOC);

// Chart Data: Device Type Breakdown
$deviceStmt = $db->prepare("
    SELECT IFNULL(NULLIF(device_type, ''), 'Unknown') as label, COUNT(*) as value 
    FROM page_analytics
    WHERE link_page_id = ?
    GROUP BY label
");
$deviceStmt->execute([$linkPageId]);
$deviceData = $deviceStmt->fetchAll(PDO::FETCH_ASSOC);

// Analytics: Link Clicks
$stmt = $db->prepare("
    SELECT l.title, l.url, COUNT(la.id) as clicks 
    FROM links l 
    LEFT JOIN link_analytics la ON l.id = la.link_id 
    WHERE l.link_page_id = ? 
    GROUP BY l.id 
    ORDER BY clicks DESC
");
$stmt->execute([$linkPageId]);
$linkStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage My Card | <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;600;800&family=Playfair+Display:ital,wght@0,600;1,600&family=Poppins:wght@400;600&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="portal-style.css">
</head>
<body>
    <div id="mainAlert" class="alert alert-success" style="display: none;"></div>

    <div class="portal-header">
        <div class="portal-logo-group">
            <img src="logolinky.png" alt="Linky" style="height: 32px;">
            <div class="divider"></div>
            <span>CLIENT PORTAL</span>
        </div>
        <div class="portal-actions">
            <a href="<?php echo BASE_URL . '/u/' . $tokenStr; ?>" target="_blank" class="btn btn-live-view"><i class="fas fa-external-link-alt"></i> <span>View Live</span></a>
            <a href="?logout=1" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <div class="portal-body">
        <div class="editor-container" style="gap: 2rem;">
            <!-- Left Column: Analytics + Editor -->
            <div class="editor-main">

                <!-- Analytics Section -->
                <div class="analytics-top-grid">
                    <!-- Traffic Over Time -->
                    <div class="analytics-card traffic-chart-card">
                        <h3 class="card-title"><i class="fas fa-chart-line"></i> Visits (7 Days)</h3>
                        <div class="chart-wrapper">
                            <canvas id="portalTrafficChart"></canvas>
                        </div>
                    </div>

                    <!-- Devices -->
                    <div class="analytics-card device-chart-card">
                        <h3 class="card-title"><i class="fas fa-mobile-alt"></i> Devices</h3>
                        <div class="chart-wrapper">
                            <canvas id="portalDeviceChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-grid">
                        <div class="stat-box">
                            <div class="stat-value"><?php echo number_format($totalViews); ?></div>
                            <div class="stat-label">Total Profile Views</div>
                        </div>
                        <div style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 1rem; overflow: hidden;">
                            <h4 style="font-size: 0.85rem; color: var(--text-dim); text-transform: uppercase; margin-bottom: 1rem;">Link Clicks breakdown</h4>
                            <?php if (empty($linkStats)): ?>
                                <p style="color: var(--text-dim); font-size: 0.9rem;">No data yet.</p>
                            <?php else: ?>
                                <div style="max-height: 150px; overflow-y: auto; padding-right: 1rem;">
                                    <?php foreach ($linkStats as $stat): ?>
                                    <div class="link-stat-row">
                                        <div style="font-size: 0.9rem; color: #fff; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px;"><?php echo htmlspecialchars($stat['title']); ?></div>
                                        <div style="color: var(--primary); font-weight: 700;"><?php echo number_format($stat['clicks']); ?> <span style="font-size: 0.7rem; color: var(--text-dim); font-weight: 400;">clicks</span></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Asset Details -->
                <div class="glass-card">
                    <div class="studio-header">
                        <div>
                            <h3>Profile Details</h3>
                            <p>Update your public display name</p>
                        </div>
                        <button class="btn btn-primary" onclick="saveDesign()"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block;">Profile Name</label>
                        <input type="text" id="profileName" value="<?php echo htmlspecialchars($linkPage['profile_name']); ?>" placeholder="e.g. My Profile" style="height: 54px; background: rgba(0,0,0,0.3); width: 100%; border: 1px solid var(--glass-border); border-radius: 1rem; padding: 0 1.25rem; color: #fff; font-size: 1rem; transition: all 0.3s;" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(0,133,63,0.2)';" onblur="this.style.borderColor='var(--glass-border)'; this.style.boxShadow='none';" oninput="document.getElementById('previewName').innerText = this.value;">
                    </div>
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block;">Phone Number</label>
                        <input type="tel" id="phoneNumber" value="<?php echo htmlspecialchars($linkPage['phone_number'] ?? ''); ?>" placeholder="+213 XXX XXX XXX" style="height: 54px; background: rgba(0,0,0,0.3); width: 100%; border: 1px solid var(--glass-border); border-radius: 1rem; padding: 0 1.25rem; color: #fff; font-size: 1rem; transition: all 0.3s;" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(0,133,63,0.2)';" onblur="this.style.borderColor='var(--glass-border)'; this.style.boxShadow='none';" oninput="document.getElementById('previewPhone').innerText = this.value;">
                    </div>
                </div>

                <!-- Link List -->
                <div class="glass-card" style="margin-top: 2rem;">
                    <div class="studio-header">
                        <div>
                            <h3>My Links</h3>
                            <p>Manage your social presence and custom links</p>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="showAddLinkForm()" style="padding: 0.6rem 1.2rem; border-radius: 0.8rem;"><i class="fas fa-plus"></i> Add New Link</button>
                    </div>
                    
                    <div id="addLinkForm" style="display: none; background: rgba(255,255,255,0.02); padding: 2rem; border-radius: 1.5rem; margin-bottom: 2rem; border: 1px solid var(--glass-border); box-shadow: inset 0 0 40px rgba(0,0,0,0.2);">
                        <h4 id="addLinkTitle" style="margin-bottom: 1.5rem; font-weight: 800; font-size: 1.1rem;">Customize Link</h4>
                        
                        <!-- Social Templates -->
                        <div class="social-templates">
                            <button type="button" class="social-template-btn" onclick="applyTemplate('Instagram', 'https://instagram.com/')" title="Instagram"><i class="fab fa-instagram"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('TikTok', 'https://tiktok.com/@')" title="TikTok"><i class="fab fa-tiktok"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('YouTube', 'https://youtube.com/@')" title="YouTube"><i class="fab fa-youtube"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('Twitter', 'https://twitter.com/')" title="Twitter"><i class="fab fa-twitter"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('WhatsApp', 'https://wa.me/')" title="WhatsApp"><i class="fab fa-whatsapp"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('Facebook', 'https://facebook.com/')" title="Facebook"><i class="fab fa-facebook"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('LinkedIn', 'https://linkedin.com/in/')" title="LinkedIn"><i class="fab fa-linkedin"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('Telegram', 'https://t.me/')" title="Telegram"><i class="fab fa-telegram"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('Spotify', 'https://open.spotify.com/user/')" title="Spotify"><i class="fab fa-spotify"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyTemplate('Snapchat', 'https://snapchat.com/add/')" title="Snapchat"><i class="fab fa-snapchat"></i></button>
                            <button type="button" class="social-template-btn" onclick="applyGoogleReviewTemplate()" title="Rate on Google"><i class="fab fa-google"></i></button>
                        </div>

                        <div class="link-form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Button Title</label>
                                <input type="text" id="linkTitle" placeholder="e.g. Follow on Instagram" style="height: 48px; background: rgba(0,0,0,0.2); width: 100%; border: 1px solid var(--glass-border); border-radius: 0.75rem; padding: 0 1rem; color: #fff;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Target URL</label>
                                <input type="url" id="linkUrl" placeholder="https://..." style="height: 48px; background: rgba(0,0,0,0.2); width: 100%; border: 1px solid var(--glass-border); border-radius: 0.75rem; padding: 0 1rem; color: #fff;" oninput="handleUrlInput()">
                            </div>
                        </div>

                        <div id="googleReviewHelper" style="display: none; background: rgba(29, 74, 139, 0.1); border: 1px solid rgba(29, 74, 139, 0.3); border-radius: 1rem; padding: 1.25rem; margin-bottom: 2rem;">
                            <div style="display: flex; align-items: start; gap: 0.75rem;">
                                <i class="fas fa-info-circle" style="color: var(--primary); margin-top: 0.2rem;"></i>
                                <div style="flex: 1;">
                                    <p style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary);">Google Review Link</p>
                                    <p style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; font-weight: 500;">Paste your Google Maps business link and we'll automatically convert it to a review link!</p>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <button class="btn btn-primary" onclick="addLink()" style="flex: 1; height: 48px;">Save Link</button>
                            <button class="btn btn-secondary" onclick="hideAddLinkForm()" style="height: 48px;">Cancel</button>
                        </div>
                    </div>

                    <div id="linksList">
                        <?php if (empty($links)): ?>
                            <div style="text-align: center; color: var(--text-dim); padding: 4rem 2rem; background: rgba(255,255,255,0.01); border-radius: 1.5rem; border: 2px dashed var(--glass-border);">
                                <i class="fas fa-link" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.2;"></i>
                                <p style="font-weight: 500;">Your links will appear here.</p>
                                <p style="font-size: 0.8rem; opacity: 0.7;">Click "Add New Link" to get started.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($links as $link): 
                                $iconClass = 'fas fa-link';
                                $lowerStr = strtolower($link['title'] . ' ' . $link['url']);
                                if (strpos($lowerStr, 'instagram') !== false) $iconClass = 'fab fa-instagram';
                                elseif (strpos($lowerStr, 'tiktok') !== false) $iconClass = 'fab fa-tiktok';
                                elseif (strpos($lowerStr, 'facebook') !== false) $iconClass = 'fab fa-facebook';
                                elseif (strpos($lowerStr, 'youtube') !== false) $iconClass = 'fab fa-youtube';
                                elseif (strpos($lowerStr, 'whatsapp') !== false || strpos($lowerStr, 'wa.me') !== false) $iconClass = 'fab fa-whatsapp';
                                elseif (strpos($lowerStr, 'twitter') !== false || strpos($lowerStr, 'x.com') !== false) $iconClass = 'fab fa-twitter';
                                elseif (strpos($lowerStr, 'linkedin') !== false) $iconClass = 'fab fa-linkedin';
                                elseif (strpos($lowerStr, 'snapchat') !== false) $iconClass = 'fab fa-snapchat';
                                elseif (strpos($lowerStr, 'telegram') !== false || strpos($lowerStr, 't.me') !== false) $iconClass = 'fab fa-telegram';
                            ?>
                                <div class="link-item" data-id="<?php echo $link['id']; ?>">
                                    <div class="link-icon-v2">
                                        <i class="<?php echo $iconClass; ?>"></i>
                                    </div>
                                    <div class="link-info" style="flex: 1; min-width: 0;">
                                        <h4 style="font-weight: 700; font-size: 1rem; color: #fff; letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($link['title']); ?></h4>
                                        <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.15rem; opacity: 0.6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($link['url']); ?></p>
                                    </div>
                                    <div style="display: flex; gap: 0.4rem;">
                                        <button onclick="editLink(<?php echo $link['id']; ?>)" class="btn-action" style="padding: 0.6rem;" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button onclick="toggleLink(<?php echo $link['id']; ?>)" class="btn-action" style="padding: 0.6rem;" title="<?php echo $link['is_active'] ? 'Disable' : 'Enable'; ?>">
                                            <i class="fas <?php echo $link['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                        <button onclick="deleteLink(<?php echo $link['id']; ?>)" class="btn-action" style="padding: 0.6rem; color: #ef4444;" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Style Studio -->
                <div class="glass-card" style="margin-top: 2rem;">
                    <div class="studio-header">
                        <div>
                            <h3>Style Studio</h3>
                            <p>Fine-tune your page's visual identity</p>
                        </div>
                        <button class="btn btn-primary" onclick="saveDesign()"><i class="fas fa-save"></i> Save Design</button>
                    </div>
                    
                    <div class="pfp-upload-row" style="display: flex; align-items: center; gap: 2rem; margin-bottom: 3rem; padding: 2rem; background: rgba(0,0,0,0.3); border-radius: 2rem; border: 1px solid var(--glass-border); position: relative; overflow: hidden;">
                        <div id="pfpDisplay" style="width: 100px; height: 100px; background: #000; border-radius: 2.5rem; overflow: hidden; border: 3px solid rgba(255,255,255,0.1); flex-shrink: 0;">
                            <?php if ($linkPage['profile_image']): ?>
                                <img src="<?php echo PFP_URL . $linkPage['profile_image']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: var(--primary); background: rgba(255,255,255,0.02);"><?php echo mb_substr($linkPage['profile_name'], 0, 1); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem;">Profile Avatar</h4>
                            <p style="font-size: 0.75rem; color: var(--text-dim); margin-bottom: 1.25rem;">Upload a high-quality image (JPG, PNG, WebP, GIF, BMP, TIFF, SVG).</p>
                            <input type="file" id="pfpInput" accept="image/*" style="display: none;" onchange="uploadProfileImage()">
                            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('pfpInput').click()" style="padding: 0.5rem 1rem; border-radius: 0.75rem;"><i class="fas fa-camera"></i> Change Photo</button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 2.5rem;">
                        <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;">Design Template</label>
                        <div class="templates-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem;">
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'simple') == 'simple' ? 'active-template' : ''; ?>" onclick="setTemplate('simple')" data-template="simple">Simple</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'image' ? 'active-template' : ''; ?>" onclick="setTemplate('image')" data-template="image">Image</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'particles' ? 'active-template' : ''; ?>" onclick="setTemplate('particles')" data-template="particles">Flow</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'glass' ? 'active-template' : ''; ?>" onclick="setTemplate('glass')" data-template="glass">Glass</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'social' ? 'active-template' : ''; ?>" onclick="setTemplate('social')" data-template="social">Social</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'mesh' ? 'active-template' : ''; ?>" onclick="setTemplate('mesh')" data-template="mesh">Mesh</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'aurora' ? 'active-template' : ''; ?>" onclick="setTemplate('aurora')" data-template="aurora">Aurora</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'sunset' ? 'active-template' : ''; ?>" onclick="setTemplate('sunset')" data-template="sunset">Sunset</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'neon' ? 'active-template' : ''; ?>" onclick="setTemplate('neon')" data-template="neon">Neon</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'tropical' ? 'active-template' : ''; ?>" onclick="setTemplate('tropical')" data-template="tropical">Tropical</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'minimal' ? 'active-template' : ''; ?>" onclick="setTemplate('minimal')" data-template="minimal">Dark</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'galaxy' ? 'active-template' : ''; ?>" onclick="setTemplate('galaxy')" data-template="galaxy">Galaxy</button>
                            <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'') == 'geometry' ? 'active-template' : ''; ?>" onclick="setTemplate('geometry')" data-template="geometry">Shapes</button>
                        </div>
                        <input type="hidden" id="templateId" value="<?php echo $designSettings['template_id'] ?? 'simple'; ?>">
                    </div>

                    <div id="colorControls" class="color-controls" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; padding: 2rem; background: rgba(0,0,0,0.2); border-radius: 1.5rem; border: 1px solid var(--glass-border); margin-bottom: 2rem;">
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block;">Page Background</label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="color" id="backgroundColor" value="<?php echo $designSettings['background_color'] ?? '#0F172A'; ?>" oninput="document.getElementById('backgroundColorText').value = this.value; updatePreview()">
                                <input type="text" id="backgroundColorText" value="<?php echo $designSettings['background_color'] ?? '#0F172A'; ?>" style="width: 80px; height: 44px; padding: 0 0.5rem; font-size: 0.8rem;" oninput="syncColorTextToPicker(this, 'backgroundColor')">
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block;">Button Color</label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="color" id="buttonColor" value="<?php echo $designSettings['button_color'] ?? '#6366f1'; ?>" oninput="document.getElementById('buttonColorText').value = this.value; updatePreview()">
                                <input type="text" id="buttonColorText" value="<?php echo $designSettings['button_color'] ?? '#6366f1'; ?>" style="width: 80px; height: 44px; padding: 0 0.5rem; font-size: 0.8rem;" oninput="syncColorTextToPicker(this, 'buttonColor')">
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 0.75rem; display: block;">Text Color</label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="color" id="textColor" value="<?php echo $designSettings['text_color'] ?? '#ffffff'; ?>" oninput="document.getElementById('textColorText').value = this.value; updatePreview()">
                                <input type="text" id="textColorText" value="<?php echo $designSettings['text_color'] ?? '#ffffff'; ?>" style="width: 80px; height: 44px; padding: 0 0.5rem; font-size: 0.8rem;" oninput="syncColorTextToPicker(this, 'textColor')">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;">Brand Typography</label>
                        <select id="fontFamily" style="height: 54px; font-size: 1rem; border-radius: 1rem; border: 2px solid var(--glass-border); background: rgba(0,0,0,0.2); color: white; padding: 0 1.25rem; width: 100%;" onchange="updatePreview()">
                            <option value="Inter" <?php echo ($designSettings['font_family']??'') == 'Inter' ? 'selected' : ''; ?>>Inter (Modern Sans)</option>
                            <option value="Roboto" <?php echo ($designSettings['font_family']??'') == 'Roboto' ? 'selected' : ''; ?>>Roboto (Industrial)</option>
                            <option value="Poppins" <?php echo ($designSettings['font_family']??'') == 'Poppins' ? 'selected' : ''; ?>>Poppins (Geometric)</option>
                            <option value="Outfit" <?php echo ($designSettings['font_family']??'') == 'Outfit' ? 'selected' : ''; ?>>Outfit (High-End)</option>
                            <option value="Playfair Display" <?php echo ($designSettings['font_family']??'') == 'Playfair Display' ? 'selected' : ''; ?>>Playfair Display (Premium Serif)</option>
                        </select>
                    </div>

                    <div id="bgImageGroup" style="display: <?php echo ($designSettings['template_id']??'') == 'image' ? 'block' : 'none'; ?>; margin-top: 2rem;">
                        <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: block;">Background Backdrop</label>
                        <div style="display: flex; gap: 1.5rem; align-items: center; padding: 1.5rem; background: rgba(0,0,0,0.2); border-radius: 1.5rem; border: 1px solid var(--glass-border);">
                            <input type="file" id="bgImageInput" accept="image/*" style="display: none;" onchange="uploadBackgroundImage()">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('bgImageInput').click()" style="border-radius: 0.75rem;"><i class="fas fa-upload"></i> Upload Image</button>
                            <div id="bgImagePreview" style="font-size: 0.8rem; font-weight: 600; color: var(--accent);">
                                <?php echo $designSettings['bg_image'] ? 'Background set ✨' : 'No image chosen'; ?>
                            </div>
                            <input type="hidden" id="bgImage" value="<?php echo $designSettings['bg_image'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
            </div><!-- /.editor-main -->

            <!-- Right Column: Live Preview Mockup -->
            <div class="editor-side">
                <div class="preview-pane">
                    <div class="iphone-notch"></div>
                    <div class="preview-content" id="previewContent">
                        <div class="preview-blob" id="previewBlob1" style="width: 280px; height: 280px; top: -10%; left: -10%;"></div>
                        <div class="preview-blob" id="previewBlob2" style="width: 250px; height: 250px; bottom: -5%; right: -5%; opacity: 0.2;"></div>
                        <div class="preview-blob" id="previewBlob3" style="width: 200px; height: 200px; top: 40%; right: -10%; opacity: 0.1;"></div>
                        
                        <div class="preview-pane-inner" id="previewInner">
                            <div class="preview-avatar-frame">
                                <div id="previewAvatar" class="preview-pfp">
                                    <?php if ($linkPage['profile_image']): ?>
                                        <img src="<?php echo PFP_URL . $linkPage['profile_image']; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($linkPage['profile_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3 id="previewName" style="margin-bottom: 0.5rem; font-weight: 800; color: #fff; letter-spacing: -0.04em; font-size: 1.6rem;"><?php echo htmlspecialchars($linkPage['profile_name']); ?></h3>
                            <p id="previewPhone" style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 500;"><?php echo htmlspecialchars($linkPage['phone_number'] ?? ''); ?></p>
                            <p id="previewTagline" style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-bottom: 3rem;">Connect with me</p>
                            
                            <div id="previewButtons">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>
                    </div>
                </div><!-- /.preview-pane -->
            </div><!-- /.editor-side -->
        </div><!-- /.editor-container -->
    </div><!-- /.portal-body -->

    <!-- Loading overlay -->
    <div id="loadingOverlay"><span class="loading"></span></div>

    <!-- Upload Progress Overlay -->
    <style>
        /* ── Upload Progress Overlay: self-contained so it always works ── */
        #uploadProgressOverlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background: rgba(0, 0, 0, 0.55) !important;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center !important;
            justify-content: center !important;
            z-index: 99999 !important;
            opacity: 0;
            transition: opacity 0.3s ease;
            margin: 0 !important;
            padding: 1rem !important;
            box-sizing: border-box;
        }
        #uploadProgressOverlay.visible {
            opacity: 1;
        }
        .upload-progress-modal {
            background: #fff;
            width: 90%;
            max-width: 360px;
            padding: 2.5rem 2rem;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            flex-shrink: 0;
        }
        #uploadProgressOverlay.visible .upload-progress-modal {
            transform: scale(1);
        }
        .upload-modal-icon {
            font-size: 2.5rem;
            color: #1d4a8b;
            margin-bottom: 1rem;
            display: inline-block;
        }
        .upload-modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #262626;
            margin-bottom: 0.5rem;
        }
        .upload-modal-desc {
            font-size: 0.85rem;
            color: #737373;
            margin-bottom: 1.5rem;
        }
        .upload-progress-track {
            width: 100%;
            height: 8px;
            background: #f5f5f5;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }
        .upload-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #1d4a8b, #00B8DB);
            border-radius: 4px;
            transition: width 0.1s linear;
        }
        .upload-progress-text {
            font-size: 0.8rem;
            font-weight: 700;
            color: #262626;
        }
        .upload-success-state .upload-modal-icon { color: #00C951; }
        .upload-success-state .upload-progress-bar { background: #00C951; }
    </style>

    <!-- Upload Progress Overlay -->
    <div id="uploadProgressOverlay">
        <div class="upload-progress-modal" id="uploadModalInner">
            <i class="fas fa-cloud-upload-alt upload-modal-icon" id="uploadModalIcon"></i>
            <h3 class="upload-modal-title" id="uploadModalTitle">Uploading...</h3>
            <p class="upload-modal-desc">Please wait while your file is uploaded.</p>
            <div class="upload-progress-track">
                <div class="upload-progress-bar" id="uploadProgressBar"></div>
            </div>
            <div class="upload-progress-text" id="uploadProgressText">0%</div>
        </div>
    </div>

    <script>
        const API_URL = '<?php echo BASE_URL; ?>';
        const PFP_URL = '<?php echo PFP_URL; ?>';

        function syncColorTextToPicker(textInput, pickerId) {
            let val = textInput.value.trim();
            if (val && !val.startsWith('#')) {
                val = '#' + val;
            }
            if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
                document.getElementById(pickerId).value = val;
                updatePreview();
            } else if (/^#[0-9A-Fa-f]{3}$/.test(val)) {
                const expanded = '#' + val[1] + val[1] + val[2] + val[2] + val[3] + val[3];
                document.getElementById(pickerId).value = expanded;
                updatePreview();
            }
        }

        function showAlert(msg, type='success') {
            const alertEl = document.getElementById('mainAlert');
            alertEl.className = 'alert alert-' + type;
            alertEl.innerHTML = '<i class="fas fa-info-circle"></i> ' + msg;
            alertEl.style.display = 'block';
            setTimeout(() => alertEl.style.display = 'none', 3000);
        }
        function showLoading(state) {
            document.getElementById('loadingOverlay').style.display = state ? 'flex' : 'none';
        }

        function applyTemplate(title, baseUrl) {
            document.getElementById('linkTitle').value = title;
            document.getElementById('linkUrl').value = baseUrl;
            handleUrlInput();
            document.getElementById('linkUrl').focus();
        }

        function applyGoogleReviewTemplate() {
            document.getElementById('linkTitle').value = 'Rate us on Google';
            document.getElementById('linkUrl').value = '';
            document.getElementById('googleReviewHelper').style.display = 'block';
            document.getElementById('linkUrl').focus();
        }

        function handleUrlInput() {
            const url = document.getElementById('linkUrl').value.toLowerCase();
            const helper = document.getElementById('googleReviewHelper');
            if (url.includes('google.com/maps') || url.includes('goo.gl/maps')) {
                helper.style.display = 'block';
            } else {
                helper.style.display = 'none';
            }
        }

        let currentEditLinkId = null;

        function showAddLinkForm() {
            currentEditLinkId = null;
            document.getElementById('linkTitle').value = '';
            document.getElementById('linkUrl').value = '';
            document.getElementById('addLinkTitle').textContent = 'Add New Link';
            document.getElementById('addLinkForm').style.display = 'block';
        }

        function hideAddLinkForm() { document.getElementById('addLinkForm').style.display = 'none'; }

        function editLink(id) {
            currentEditLinkId = id;
            const item = document.querySelector(`.link-item[data-id="${id}"]`);
            const title = item.querySelector('h4').textContent;
            const url = item.querySelector('p').textContent;
            
            document.getElementById('linkTitle').value = title;
            document.getElementById('linkUrl').value = url;
            document.getElementById('addLinkTitle').textContent = 'Edit Link';
            document.getElementById('addLinkForm').style.display = 'block';
            document.getElementById('addLinkForm').scrollIntoView({behavior: 'smooth'});
        }

        async function addLink() {
            const title = document.getElementById('linkTitle').value;
            const url = document.getElementById('linkUrl').value;
            if(!title || !url) return alert('Fill all fields');

            showLoading(true);
            try {
                const method = currentEditLinkId ? 'PUT' : 'POST';
                const body = { title, url };
                if(currentEditLinkId) {
                    body.id = currentEditLinkId;
                    const item = document.querySelector(`.link-item[data-id="${currentEditLinkId}"]`);
                    body.is_active = item.querySelector('.fa-eye') !== null;
                }

                const res = await fetch(`${API_URL}/api-portal-links.php`, {
                    method: method,
                    headers: {
                        'Content-Type':'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    },
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if(data.success) location.reload();
                else alert(data.error);
            } catch(e) { alert('Error saving link'); }
            showLoading(false);
        }

        async function toggleLink(id) {
            showLoading(true);
            try {
                const item = document.querySelector(`.link-item[data-id="${id}"]`);
                const title = item.querySelector('h4').textContent;
                const url = item.querySelector('p').textContent;
                const currentStatus = item.querySelector('.fa-eye') !== null;
                
                const res = await fetch(`${API_URL}/api-portal-links.php`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type':'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    },
                    body: JSON.stringify({id, title, url, is_active: !currentStatus})
                });
                const data = await res.json();
                if(data.success) location.reload();
                else alert(data.error);
            } catch(e) { alert('Error toggling link'); }
            showLoading(false);
        }

        async function deleteLink(id) {
            if(!confirm('Delete this link?')) return;
            const res = await fetch(`${API_URL}/api-portal-links.php`, {
                method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                    'Content-Type':'application/json',
                    'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                },
                body: JSON.stringify({id})
            });
            location.reload();
        }

        async function saveDesign() {
            const profile_name = document.getElementById('profileName').value;
            const phone_number = document.getElementById('phoneNumber').value;

            const settings = {
                template_id:      document.getElementById('templateId').value,
                bg_image:         document.getElementById('bgImage').value,
                font_family:      document.getElementById('fontFamily').value,
                button_style:     'rounded',
                background_color: document.getElementById('backgroundColor').value,
                button_color:     document.getElementById('buttonColor').value,
                text_color:       document.getElementById('textColor').value
            };
            
            showLoading(true);
            try {
                const res = await fetch(`${API_URL}/api-portal-design.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    },
                    body: JSON.stringify({
                        profile_name: profile_name,
                        phone_number: phone_number,
                        settings: settings
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showAlert('Design saved! ✨');
                    updatePreview();
                } else {
                    alert(data.error || 'Failed to save design');
                }
            } catch(e) {
                alert('Connection error. Please try again.');
            }
            showLoading(false);
        }

        function showUploadProgress(show, title = 'Uploading...', isSuccess = false) {
            const overlay = document.getElementById('uploadProgressOverlay');
            const inner = document.getElementById('uploadModalInner');
            const titleEl = document.getElementById('uploadModalTitle');
            const iconEl = document.getElementById('uploadModalIcon');
            
            if (show && !isSuccess) {
                // Reset state first
                inner.classList.remove('upload-success-state');
                iconEl.className = 'fas fa-cloud-upload-alt upload-modal-icon';
                titleEl.innerText = title;
                document.getElementById('uploadProgressBar').style.width = '0%';
                document.getElementById('uploadProgressText').innerText = '0%';
                // Show: set display first, then trigger opacity transition on next frame
                overlay.style.display = 'flex';
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        overlay.classList.add('visible');
                    });
                });
            }

            if (isSuccess) {
                inner.classList.add('upload-success-state');
                iconEl.className = 'fas fa-check-circle upload-modal-icon';
                titleEl.innerText = 'Upload Complete!';
                document.getElementById('uploadProgressBar').style.width = '100%';
                document.getElementById('uploadProgressText').innerText = '100%';
            }

            if (!show) {
                overlay.classList.remove('visible');
                setTimeout(() => {
                    overlay.style.display = 'none';
                }, 350);
            }
        }

        function handleFileUpload(fileInputId, uploadType, successCallback) {
            const fileInput = document.getElementById(fileInputId);
            if (!fileInput.files.length) return;
            
            const formData = new FormData();
            formData.append('profile_image', fileInput.files[0]);
            formData.append('upload_type', uploadType);
            
            showUploadProgress(true, uploadType === 'profile' ? 'Uploading Avatar...' : 'Uploading Background...');
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `${API_URL}/api-portal-upload.php`, true);
            xhr.setRequestHeader('X-CSRF-TOKEN', '<?php echo Security::getCSRFToken(); ?>');
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.floor((e.loaded / e.total) * 100);
                    document.getElementById('uploadProgressBar').style.width = percentComplete + '%';
                    document.getElementById('uploadProgressText').innerText = percentComplete + '%';
                }
            };
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            showUploadProgress(true, '', true); // Show success state
                            setTimeout(() => {
                                successCallback(data);
                                showUploadProgress(false); // Hide after delay
                            }, 800);
                        } else {
                            showUploadProgress(false);
                            alert(data.error);
                        }
                    } catch (e) {
                        showUploadProgress(false);
                        alert('Upload failed: Invalid response');
                    }
                } else {
                    showUploadProgress(false);
                    alert('Upload failed with status ' + xhr.status);
                }
            };
            
            xhr.onerror = function() {
                showUploadProgress(false);
                alert('Upload failed due to a network error');
            };
            
            xhr.send(formData);
        }

        function uploadProfileImage() {
            handleFileUpload('pfpInput', 'profile', function(data) {
                const imgHtml = `<img src="${data.url}" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">`;
                document.getElementById('pfpDisplay').innerHTML = imgHtml;
                document.getElementById('previewAvatar').innerHTML = imgHtml;
                showAlert('Profile picture updated!');
            });
        }

        function uploadBackgroundImage() {
            handleFileUpload('bgImageInput', 'background', function(data) {
                document.getElementById('bgImage').value = data.filename;
                document.getElementById('bgImagePreview').innerText = 'Background set ✨';
                updatePreview();
                showAlert('Background image uploaded!');
            });
        }

        function setTemplate(id) {
            document.getElementById('templateId').value = id;
            document.querySelectorAll('[data-template]').forEach(btn => {
                btn.classList.remove('active-template');
                if(btn.dataset.template === id) btn.classList.add('active-template');
            });
            
            const bgGroup = document.getElementById('bgImageGroup');
            if(id === 'image') bgGroup.style.display = 'block';
            else bgGroup.style.display = 'none';
            
            updatePreview();
        }

        function updatePreview() {
            const template = document.getElementById('templateId')?.value || 'simple';
            const bgImg = document.getElementById('bgImage')?.value;
            const font = document.getElementById('fontFamily')?.value || 'Inter';
            
            const bgColor = document.getElementById('backgroundColor')?.value || '#0F172A';
            const btnColor = document.getElementById('buttonColor')?.value || '#6366f1';
            const textColor = document.getElementById('textColor')?.value || '#ffffff';

            const content = document.getElementById('previewContent');
            if(!content) return;
            
            content.style.fontFamily = font;
            content.style.color = textColor;
            
            // Apply template styles to preview
            const b1 = document.getElementById('previewBlob1');
            const b2 = document.getElementById('previewBlob2');
            const b3 = document.getElementById('previewBlob3');
            
            // --- Reset all dynamic state ---
            content.style.backgroundSize = 'unset';
            content.style.backgroundPosition = 'unset';
            content.style.animation = 'none';
            content.querySelectorAll('.galaxy-overlay, .geometry-overlay').forEach(el => el.remove());
            
            const crystalCard = content.querySelector('.preview-pane-inner');
            if(crystalCard) {
                crystalCard.style.backdropFilter = 'blur(40px) saturate(200%)';
                crystalCard.style.border = '';
                crystalCard.style.boxShadow = '';
                crystalCard.style.background = '';
            }
            
            if(b1) { b1.style.display = 'none'; b1.style.filter = ''; }
            if(b2) { b2.style.display = 'none'; b2.style.filter = ''; }
            if(b3) { b3.style.display = 'none'; b3.style.filter = ''; }
            
            let finalBtnColor = btnColor;
            let finalTextColor = '#ffffff';

            switch(template) {
                case 'simple':
                    content.style.background = bgColor;
                    finalTextColor = textColor;
                    break;

                case 'social':
                    content.style.background = '#fafafa';
                    finalTextColor = '#262626';
                    if(crystalCard) {
                        crystalCard.style.background = '#ffffff';
                        crystalCard.style.border = '1px solid #dbdbdb';
                        crystalCard.style.boxShadow = 'none';
                    }
                    break;

                case 'image':
                    finalTextColor = textColor;
                    if(bgImg) {
                        content.style.background = `linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('${PFP_URL}${bgImg}')`;
                        content.style.backgroundSize = 'cover';
                        content.style.backgroundPosition = 'center';
                        if(crystalCard) crystalCard.style.backdropFilter = 'blur(10px) saturate(180%)';
                    } else {
                        content.style.background = `linear-gradient(135deg, ${bgColor} 0%, ${bgColor}e6 100%)`;
                    }
                    break;

                case 'particles':
                    content.style.background = `linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%)`;
                    if(b1) { b1.style.display = 'block'; b1.style.background = 'radial-gradient(circle, #22d3ee 0%, transparent 70%)'; b1.style.opacity = '0.4'; }
                    if(b2) { b2.style.display = 'block'; b2.style.background = 'radial-gradient(circle, #818cf8 0%, transparent 70%)'; b2.style.opacity = '0.4'; }
                    if(b3) { b3.style.display = 'block'; b3.style.background = 'radial-gradient(circle, #c084fc 0%, transparent 70%)'; b3.style.opacity = '0.3'; }
                    break;

                case 'glass':
                    content.style.background = `linear-gradient(135deg, ${bgColor} 0%, ${bgColor}cc 100%)`;
                    if(b1) { b1.style.display = 'block'; b1.style.background = 'radial-gradient(circle, #6366f1 0%, transparent 70%)'; b1.style.opacity = '0.6'; }
                    if(b2) { b2.style.display = 'block'; b2.style.background = 'radial-gradient(circle, #f43f5e 0%, transparent 70%)'; b2.style.opacity = '0.6'; }
                    if(b3) { b3.style.display = 'block'; b3.style.background = 'radial-gradient(circle, #a855f7 0%, transparent 70%)'; b3.style.opacity = '0.6'; }
                    finalTextColor = textColor;
                    break;

                case 'mesh':
                    content.style.background = `linear-gradient(45deg, #f43f5e, #fb923c, #3b82f6, #8b5cf6)`;
                    content.style.backgroundSize = '400% 400%';
                    content.style.animation = 'gradientMesh 15s ease infinite';
                    break;

                case 'aurora':
                    content.style.background = `linear-gradient(to bottom, #000428, #004e92)`;
                    if(b1) { b1.style.display = 'block'; b1.style.background = 'radial-gradient(circle, #00ff87 0%, transparent 70%)'; b1.style.opacity = '0.3'; b1.style.filter = 'blur(60px)'; }
                    if(b2) { b2.style.display = 'block'; b2.style.background = 'radial-gradient(circle, #60efff 0%, transparent 70%)'; b2.style.opacity = '0.2'; }
                    break;

                case 'sunset':
                    content.style.background = `linear-gradient(to top, #41295a, #2f0743)`;
                    if(b1) { b1.style.display = 'block'; b1.style.background = 'radial-gradient(circle, #ff5e62 0%, transparent 70%)'; b1.style.opacity = '0.4'; }
                    if(b2) { b2.style.display = 'block'; b2.style.background = 'radial-gradient(circle, #ff9966 0%, transparent 70%)'; b2.style.opacity = '0.3'; }
                    if(b3) { b3.style.display = 'block'; b3.style.background = 'radial-gradient(circle, #833ab4 0%, transparent 70%)'; b3.style.opacity = '0.2'; }
                    break;

                case 'neon':
                    content.style.background = bgColor;
                    if(crystalCard) {
                        crystalCard.style.border = `2px solid ${btnColor}`;
                        crystalCard.style.boxShadow = `0 0 20px ${btnColor}33`;
                    }
                    finalTextColor = textColor;
                    // Profile name gets neon glow
                    const neonName = document.getElementById('previewName');
                    if(neonName) neonName.style.textShadow = `0 0 10px ${btnColor}80`;
                    break;

                case 'tropical':
                    content.style.background = `linear-gradient(135deg, #02aab0 0%, #00cdac 100%)`;
                    break;

                case 'minimal':
                    content.style.background = '#09090b';
                    finalTextColor = '#a1a1aa';
                    if(crystalCard) {
                        crystalCard.style.background = 'rgba(255,255,255,0.01)';
                        crystalCard.style.border = '1px solid rgba(255,255,255,0.05)';
                    }
                    break;

                case 'galaxy':
                    content.style.background = '#000000';
                    const galaxyOverlay = document.createElement('div');
                    galaxyOverlay.className = 'galaxy-overlay';
                    Object.assign(galaxyOverlay.style, {
                        position: 'absolute', inset: '0', pointerEvents: 'none', zIndex: '0',
                        background: 'radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%)'
                    });
                    for(let i = 0; i < 60; i++) {
                        const star = document.createElement('div');
                        const sz = (Math.random() * 1.5 + 0.5).toFixed(1);
                        Object.assign(star.style, {
                            position: 'absolute',
                            width: sz + 'px', height: sz + 'px',
                            background: '#fff', borderRadius: '50%',
                            top: (Math.random() * 100).toFixed(1) + '%',
                            left: (Math.random() * 100).toFixed(1) + '%',
                            opacity: (Math.random() * 0.7 + 0.3).toFixed(2)
                        });
                        galaxyOverlay.appendChild(star);
                    }
                    content.appendChild(galaxyOverlay);
                    if(crystalCard) {
                        crystalCard.style.background = 'rgba(255,255,255,0.05)';
                        crystalCard.style.border = '1px solid rgba(255,255,255,0.1)';
                        crystalCard.style.boxShadow = '0 0 40px rgba(0,0,0,0.5)';
                    }
                    break;

                case 'geometry':
                    content.style.background = `linear-gradient(135deg, #1e1b4b 0%, #312e81 100%)`;
                    const geomOverlay = document.createElement('div');
                    geomOverlay.className = 'geometry-overlay';
                    Object.assign(geomOverlay.style, {
                        position: 'absolute', inset: '0', pointerEvents: 'none', overflow: 'hidden', zIndex: '0'
                    });
                    for(let i = 0; i < 10; i++) {
                        const shape = document.createElement('div');
                        const sz = (20 + Math.random() * 50).toFixed(0);
                        Object.assign(shape.style, {
                            position: 'absolute',
                            width: sz + 'px', height: sz + 'px',
                            background: 'rgba(255,255,255,0.03)',
                            border: '1px solid rgba(255,255,255,0.08)',
                            borderRadius: Math.random() > 0.5 ? '50%' : '20%',
                            top: (Math.random() * 110 - 10).toFixed(0) + '%',
                            left: (Math.random() * 110 - 10).toFixed(0) + '%',
                            transform: `rotate(${(Math.random() * 360).toFixed(0)}deg)`,
                            backdropFilter: 'blur(5px)'
                        });
                        geomOverlay.appendChild(shape);
                    }
                    content.appendChild(geomOverlay);
                    if(crystalCard) {
                        crystalCard.style.background = 'rgba(255,255,255,0.03)';
                        crystalCard.style.border = '1px solid rgba(255,255,255,0.1)';
                        crystalCard.style.boxShadow = '0 20px 50px rgba(0,0,0,0.3)';
                    }
                    break;
            }

            // Reset neon glow if not neon
            if(template !== 'neon') {
                const nName = document.getElementById('previewName');
                if(nName) nName.style.textShadow = '';
            }

            const nameEl = document.getElementById('previewName');
            const taglineEl = document.getElementById('previewTagline');
            if(nameEl && template !== 'neon') nameEl.style.color = finalTextColor;
            if(taglineEl) taglineEl.style.color = finalTextColor + '99';

            refreshPreviewButtons(template, finalBtnColor, finalTextColor);
        }

        function refreshPreviewButtons(template, btnColor, textColor) {
            const container = document.getElementById('previewButtons');
            if(!container) return;
            
            const items = document.querySelectorAll('.link-item');
            if(items.length === 0) {
                container.innerHTML = `<div class="preview-btn" style="justify-content:center; opacity:0.5; border-style:dashed;">Your links will appear here</div>`;
                return;
            }

            // Per-template button overrides (matching linkpage.php)
            let btnExtraStyle = '';
            let iconColor = btnColor;

            switch(template) {
                case 'social':
                    btnExtraStyle = `background:#efefef;border:none;color:#262626;border-radius:8px;justify-content:flex-start;`;
                    iconColor = btnColor;
                    textColor = '#262626';
                    break;
                case 'neon':
                    btnExtraStyle = `background:transparent;border-color:${btnColor};color:${btnColor};text-shadow:0 0 5px ${btnColor}80;`;
                    iconColor = btnColor;
                    break;
                case 'minimal':
                    btnExtraStyle = `background:transparent;border-color:rgba(255,255,255,0.1);color:#a1a1aa;`;
                    iconColor = btnColor;
                    textColor = '#a1a1aa';
                    break;
                case 'geometry':
                    btnExtraStyle = `background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);`;
                    break;
                case 'galaxy':
                    btnExtraStyle = `background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);`;
                    break;
                case 'tropical':
                    btnExtraStyle = `background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);`;
                    break;
                default:
                    break;
            }

            let html = '';
            items.forEach(item => {
                const title = item.querySelector('h4').innerText;
                const url = item.querySelector('p').innerText;
                const isActive = item.querySelector('.fa-eye') !== null;
                if(!isActive) return;

                let iconClass = 'fas fa-link';
                const lt = title.toLowerCase(), lu = url.toLowerCase();
                if (lu.includes('instagram') || lt.includes('instagram')) iconClass = 'fab fa-instagram';
                else if (lu.includes('facebook') || lt.includes('facebook')) iconClass = 'fab fa-facebook';
                else if (lu.includes('twitter') || lu.includes('x.com') || lt.includes('twitter')) iconClass = 'fab fa-twitter';
                else if (lu.includes('youtube') || lt.includes('youtube')) iconClass = 'fab fa-youtube';
                else if (lu.includes('tiktok') || lt.includes('tiktok')) iconClass = 'fab fa-tiktok';
                else if (lu.includes('linkedin') || lt.includes('linkedin')) iconClass = 'fab fa-linkedin';
                else if (lu.includes('github') || lt.includes('github')) iconClass = 'fab fa-github';
                else if (lu.includes('telegram') || lu.includes('t.me') || lt.includes('telegram')) iconClass = 'fab fa-telegram';
                else if (lu.includes('spotify') || lt.includes('spotify')) iconClass = 'fab fa-spotify';
                else if (lu.includes('twitch') || lt.includes('twitch')) iconClass = 'fab fa-twitch';
                else if (lu.includes('discord') || lt.includes('discord')) iconClass = 'fab fa-discord';
                else if (lu.includes('snapchat') || lt.includes('snapchat')) iconClass = 'fab fa-snapchat';
                else if (lu.includes('pinterest') || lt.includes('pinterest')) iconClass = 'fab fa-pinterest';
                else if (lu.includes('whatsapp') || lu.includes('wa.me') || lt.includes('whatsapp')) iconClass = 'fab fa-whatsapp';

                html += `
                    <div class="preview-btn" style="color:${textColor};border-color:${textColor}20;${btnExtraStyle}">
                        <i class="${iconClass}" style="width:20px;text-align:center;opacity:0.9;color:${iconColor};"></i>
                        ${title}
                    </div>
                `;
            });

            container.innerHTML = html || `<div class="preview-btn" style="justify-content:center; opacity:0.5; border-style:dashed;">No active links</div>`;
        }

        // Initialize preview
        updatePreview();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart Config defaults
        Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
        Chart.defaults.font.family = "'Outfit', sans-serif";
        
        // --- Traffic Over Time ---
        const trafficData = <?php echo json_encode($viewsOverTime); ?>;
        new Chart(document.getElementById('portalTrafficChart'), {
            type: 'line',
            data: {
                labels: trafficData.map(d => d.view_date),
                datasets: [{
                    label: 'Page Views',
                    data: trafficData.map(d => d.views),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // --- Device Breakdown ---
        const deviceData = <?php echo json_encode($deviceData); ?>;
        new Chart(document.getElementById('portalDeviceChart'), {
            type: 'doughnut',
            data: {
                labels: deviceData.map(d => d.label),
                datasets: [{
                    data: deviceData.map(d => d.value),
                    backgroundColor: ['#667eea', '#764ba2', '#ff6b6b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right' } }
            }
        });
    </script>
</body>
</html>
