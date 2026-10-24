<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'link-manager.php';
require_once 'design-manager.php';

$auth = new Auth();
$auth->requireAuth();

$user = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();
$linkManager = new LinkManager();
$designManager = new DesignManager();

// Partition constants (Legacy check/Map to new ones if defined in config)
$rawUrl = defined('QR_RAW_URL') ? QR_RAW_URL : BASE_URL . '/qr-codes/';
$cardUrl = defined('QR_CARD_URL') ? QR_CARD_URL : BASE_URL . '/qr-codes/';

// Get view mode from URL
$view = $_GET['view'] ?? 'overview';
$editId = $_GET['edit_id'] ?? null;

// Fetch all tokens and their associated pages for the overview
$stmt = $db->prepare("SELECT t.id as token_id, t.token, t.reference, lp.id, lp.profile_name, lp.created_at as page_created, t.created_at as token_created, qc.qr_code_path, qc.card_image_path,
                      (SELECT COUNT(*) FROM links WHERE link_page_id = lp.id) as link_count 
                      FROM tokens t
                      LEFT JOIN link_pages lp ON t.id = lp.token_id
                      LEFT JOIN qr_cards qc ON t.id = qc.token_id
                      ORDER BY COALESCE(lp.created_at, t.created_at) DESC");
$stmt->execute();
$allPages = $stmt->fetchAll();

// Pagination settings
$itemsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalItems = count($allPages);
$totalPages = max(1, ceil($totalItems / $itemsPerPage));
$currentPage = min($currentPage, $totalPages); // Ensure current page doesn't exceed total pages
$offset = ($currentPage - 1) * $itemsPerPage;
$paginatedPages = array_slice($allPages, $offset, $itemsPerPage);

// Calculate Stats for Overview (using all pages, not just paginated)
$statTotalCards = count($allPages);
$statActivePages = 0;
$statTotalLinks = 0;
foreach ($allPages as $p) {
    if (!empty($p['id'])) {
        $statActivePages++;
        $statTotalLinks += (int)($p['link_count'] ?? 0);
    }
}

// Fetch global analytics for stats
$stmtViews = $db->query("SELECT COUNT(*) FROM page_analytics");
$statTotalViews = $stmtViews->fetchColumn();

$stmtClicks = $db->query("SELECT COUNT(*) FROM link_analytics");
$statTotalClicks = $stmtClicks->fetchColumn();

// If in edit mode, fetch specific page data
$currentLinkPage = null;
$links = [];
$designSettings = [];

if ($view === 'edit' && $editId) {
    $stmt = $db->prepare("SELECT lp.*, t.token, t.reference, qc.card_image_path 
                          FROM link_pages lp 
                          JOIN tokens t ON lp.token_id = t.id 
                          LEFT JOIN qr_cards qc ON t.id = qc.token_id
                          WHERE lp.id = ?");
    $stmt->execute([$editId]);
    $currentLinkPage = $stmt->fetch();
    
    if ($currentLinkPage) {
        $links = $linkManager->getLinks($editId);
        $designSettings = $designManager->getDesignSettings($editId);
    } else {
        $view = 'overview'; // Fallback if ID not found
    }
}

// Fetch current Pixel ID
$stmtSettings = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'fb_pixel_id'");
$fbPixelId = $stmtSettings->fetchColumn() ?: '';

// Handle Pixel ID Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_pixel') {
    $newPixelId = trim($_POST['fb_pixel_id'] ?? '');
    $stmtUpdate = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'fb_pixel_id'");
    $stmtUpdate->execute([$newPixelId]);
    header("Location: dashboard?view=overview&msg=Pixel+ID+Updated");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Roboto:wght@400;700&family=Poppins:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="dashboard-style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="dash-liquid">
        <div class="blob-dash" style="top: -10%; left: -10%;"></div>
        <div class="dash-blob" style="bottom: -10%; right: -10%;"></div>
    </div>

    <!-- Mobile Menu Toggle -->
    <div class="mobile-toggle" id="mobileToggle" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeMobileMenu()"></div>

    <?php include 'sidebar.php'; ?>

    <main class="main">
        <!-- TOP BAR (Inlined for Cache Bypass) -->
        <div class="top-bar">
            <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                <div class="search-container" style="flex: 1; max-width: 500px;">
                    <i class="fas fa-magnifying-glass" style="color: var(--text-dim); font-size: 0.9rem;"></i>
                    <input type="text" class="search-input" placeholder="Search assets, references, or tags..." onkeyup="searchTable(this.value)" style="width: 100%; background: transparent; border: none; color: var(--text-main); outline: none;">
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Action Buttons -->
                <div style="display: flex; gap: 0.6rem;">
                    <button onclick="showBatchModal()" class="btn-action" title="Batch Generation">
                        <i class="fa-solid fa-layer-group"></i>
                    </button>
                    <button onclick="showNewPageModal()" class="btn btn-primary" style="padding: 0.6rem 1.25rem; border-radius: 12px; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; color: #fff; background: var(--primary); border: none; box-shadow: 0 4px 12px var(--primary-glow);">
                        <i class="fa-solid fa-plus-circle"></i> New Page
                    </button>
                </div>

                <div style="text-align: right; line-height: 1.2; margin-left: 0.5rem;">
                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($user['username']); ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Super Admin</div>
                </div>
                <div style="width: 45px; height: 45px; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--primary)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; box-shadow: 0 4px 15px var(--primary-glow);">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
            </div>
        </div>
        <div id="mainAlert" class="alert"></div>

        <?php if ($view === 'overview'): ?>
            <!-- OVERVIEW: List all generated link pages -->
            <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 class="section-title" style="margin-bottom: 0.5rem;">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
                    <p style="color: var(--text-dim); font-size: 0.9rem;">Managing your premium digital identity assets.</p>
                </div>
                <button onclick="showBatchModal()" class="btn btn-primary" style="padding: 0.8rem 1.75rem; border-radius: 1rem; font-weight: 700; background: var(--primary); border: none; box-shadow: 0 4px 15px var(--primary-glow); color: #fff; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-plus-circle"></i> Create Batch
                </button>
            </div>

            <!-- Dashboard Stats -->
            <!-- Dashboard Stats -->
            <div class="stats-grid">
                <div class="stat-card card-total">
                    <div class="stat-icon icon-blue">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" style="font-size: 1.75rem;"><?php echo $statTotalCards; ?></span>
                        <span class="stat-label">Total Assets</span>
                    </div>
                </div>
                <div class="stat-card card-reach">
                    <div class="stat-icon icon-rose">
                        <i class="fas fa-earth-africa"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" style="font-size: 1.75rem;"><?php echo number_format($statTotalViews); ?></span>
                        <span class="stat-label">Global Reach</span>
                    </div>
                </div>
                <div class="stat-card card-inter">
                    <div class="stat-icon icon-emerald">
                        <i class="fas fa-mouse-pointer"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" style="font-size: 1.75rem;"><?php echo number_format($statTotalClicks); ?></span>
                        <span class="stat-label">Interactions</span>
                    </div>
                </div>
                <div class="stat-card card-time">
                    <div class="stat-icon icon-amber">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value" id="liveClock" style="font-size: 1.75rem;">00:00:00</span>
                        <span class="stat-label"><?php echo strtoupper(date('D, M d')); ?></span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Highlights -->
            
            <div class="glass-card" style="padding: 1.5rem;">
                <div class="table-container">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                 <th width="90" style="text-align: center; vertical-align: middle;">
                                    <div style="display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 5px 10px; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid var(--glass-border);">
                                        <div style="display: flex; align-items: center; gap: 10px;" title="Select items on this page">
                                            <label class="chk-container" style="margin-bottom: 0;">
                                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                                <span class="checkmark"></span>
                                            </label>
                                            <span style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">Page</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;" title="Select all items across all pages">
                                            <label class="chk-container" style="margin-bottom: 0;">
                                                <input type="checkbox" id="selectAllTotal" onclick="toggleSelectAllTotal()">
                                                <span class="checkmark"></span>
                                            </label>
                                            <span style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 0.05em;">Table</span>
                                        </div>
                                    </div>
                                </th>
                                 <th class="sortable" onclick="sortTable(1)">Identity & QR <i class="fas fa-sort"></i></th>
                                <th class="sortable" onclick="sortTable(2)">Profile Details <i class="fas fa-sort"></i></th>
                                <th class="sortable" onclick="sortTable(3)">Metrics <i class="fas fa-sort"></i></th>
                                <th class="sortable" onclick="sortTable(4)">Acquired On <i class="fas fa-sort"></i></th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                    <tbody id="overviewBody">
                        <?php if (empty($paginatedPages)): ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-dim); padding: 5rem;">No glass pages active. Generate one to see the magic! ✨</td></tr>
                        <?php else: ?>
                            <?php foreach ($paginatedPages as $page): 
                                $isAssigned = !empty($page['id']);
                                $profileName = $isAssigned ? $page['profile_name'] : 'Not Assigned';
                                $createdAt = $isAssigned ? $page['page_created'] : $page['token_created'];
                                $linkCount = $isAssigned ? $page['link_count'] : 0;
                            ?>
                                <tr class="page-row">
                                    <td style="text-align: center;">
                                        <label class="chk-container">
                                            <input type="checkbox" class="bulk-select" value="<?php echo $page['token_id']; ?>" data-token="<?php echo htmlspecialchars($page['token']); ?>" onchange="updateBulkBar()">
                                            <span class="checkmark"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--accent);">
                                                <i class="fas fa-qrcode"></i>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <span class="badge" style="letter-spacing: 0.1em; width: fit-content;"><?php echo htmlspecialchars($page['token']); ?></span>
                                                <?php if (!empty($page['reference'])): ?>
                                                    <span class="batch-tag" style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; background: rgba(168, 85, 247, 0.1); border-radius: 4px; padding: 2px 6px; width: fit-content;">
                                                        <i class="fas fa-layer-group" style="font-size: 0.55rem; margin-right: 3px; opacity: 0.7;"></i>
                                                        <?php echo htmlspecialchars($page['reference']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: <?php echo $isAssigned ? 'var(--text-main)' : 'var(--text-dim)'; ?>; font-size: 1rem;"><?php echo htmlspecialchars($profileName); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 2px;">linkydz.com/u/<?php echo $page['token']; ?></div>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span style="font-weight: 800; color: <?php echo $isAssigned ? 'var(--accent)' : 'var(--text-dim)'; ?>; font-size: 1.1rem;"><?php echo $linkCount; ?></span> 
                                            <div style="height: 4px; width: 4px; background: var(--text-dim); border-radius: 50%; opacity: 0.3;"></div>
                                            <span style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Active Links</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="color: var(--text-dim); font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="far fa-calendar-alt" style="opacity: 0.5;"></i>
                                            <?php echo date('M d, Y', strtotime($createdAt)); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <?php if ($isAssigned): ?>
                                                <a href="?view=edit&edit_id=<?php echo $page['id']; ?>" class="btn-action" title="Configure Design"><i class="fas fa-magic"></i></a>
                                                <a href="analytics?id=<?php echo $page['id']; ?>" class="btn-action" style="color: var(--accent);" title="View Analytics"><i class="fas fa-chart-line"></i></a>
                                            <?php else: ?>
                                                <!-- ACTIVATE BUTTON -->
                                                <button onclick="showActivationModal(<?php echo (int)($page['token_id'] ?? 0); ?>, '<?php echo htmlspecialchars($page['token']); ?>')" class="btn-action" style="color: var(--accent); background: rgba(34, 211, 238, 0.1); border-color: rgba(34, 211, 238, 0.2);" title="Activate This Card">
                                                    <i class="fas fa-magic"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                             <a href="<?php echo BASE_URL . '/u/' . $page['token']; ?>" target="_blank" class="btn-action" title="View Public Page"><i class="fas fa-external-link-alt"></i></a>
                                             
                                             <button onclick="copyLink('<?php echo BASE_URL . '/u/' . $page['token']; ?>')" class="btn-action" style="color: var(--accent);" title="Copy Link"><i class="fas fa-copy"></i></button>

                                             <?php if ($page['qr_code_path']): 
                                                $finalDownloadUrl = $rawUrl . $page['qr_code_path'];
                                            ?>
                                                <button onclick="viewQR('<?php echo $finalDownloadUrl; ?>')" class="btn-action" style="color: var(--primary);" title="View QR"><i class="fas fa-eye"></i></button>
                                                <a href="<?php echo $finalDownloadUrl; ?>" download="qr-<?php echo $page['token']; ?>.png" class="btn-action" style="color: var(--success);" title="Download QR Code"><i class="fas fa-download"></i></a>
                                                <button onclick="showPrintModal('<?php echo htmlspecialchars($page['token']); ?>', <?php echo $page['token_id']; ?>)" class="btn-action" style="color: #a855f7;" title="Print Card"><i class="fas fa-print"></i></button>
                                            <?php endif; ?>
                                            
                                            <?php if ($isAssigned): ?>
                                                <button onclick="changeSingleBatch(<?php echo $page['token_id']; ?>, '<?php echo htmlspecialchars($page['token']); ?>', '<?php echo htmlspecialchars($page['reference']??''); ?>')" class="btn-action" style="color: var(--secondary);" title="Change Batch"><i class="fas fa-layer-group"></i></button>
                                                <button onclick="deletePage(<?php echo $page['id']; ?>)" class="btn-action" style="color: var(--error);" title="Permanently Delete"><i class="fas fa-trash-alt"></i></button>
                                            <?php else: ?>
                                                <button onclick="changeSingleBatch(<?php echo $page['token_id']; ?>, '<?php echo htmlspecialchars($page['token']); ?>', '<?php echo htmlspecialchars($page['reference']??''); ?>')" class="btn-action" style="color: var(--secondary);" title="Assign Batch"><i class="fas fa-layer-group"></i></button>
                                                <button class="btn-action" style="opacity: 0.2; cursor: not-allowed;"><i class="fas fa-trash-alt"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
            <div id="overviewPagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; padding: 0 0.5rem;">
                <div style="font-size: 0.85rem; color: var(--text-dim);">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> items
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <?php if ($currentPage > 1): ?>
                        <a href="?view=overview&page=<?php echo $currentPage - 1; ?>" 
                           style="padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 0.5rem; color: var(--text-main); text-decoration: none; font-size: 0.85rem; transition: all 0.3s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.06)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    // Calculate page range to show
                    $range = 2; // Show 2 pages on each side of current page
                    $startPage = max(1, $currentPage - $range);
                    $endPage = min($totalPages, $currentPage + $range);
                    
                    // Show first page if not in range
                    if ($startPage > 1): ?>
                        <a href="?view=overview&page=1" 
                           style="padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 0.5rem; color: var(--text-main); text-decoration: none; font-size: 0.85rem; min-width: 40px; text-align: center; transition: all 0.3s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.06)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                            1
                        </a>
                        <?php if ($startPage > 2): ?>
                            <span style="color: var(--text-dim); padding: 0 0.25rem;">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?view=overview&page=<?php echo $i; ?>" 
                           style="padding: 0.5rem 0.75rem; background: <?php echo $i == $currentPage ? 'linear-gradient(135deg, var(--primary), var(--secondary))' : 'rgba(255,255,255,0.03)'; ?>; border: 1px solid <?php echo $i == $currentPage ? 'transparent' : 'var(--glass-border)'; ?>; border-radius: 0.5rem; color: var(--text-main); text-decoration: none; font-size: 0.85rem; min-width: 40px; text-align: center; font-weight: <?php echo $i == $currentPage ? '700' : '400'; ?>; transition: all 0.3s; <?php echo $i == $currentPage ? 'box-shadow: 0 4px 15px var(--primary-glow);' : ''; ?>"
                           <?php if ($i != $currentPage): ?>
                           onmouseover="this.style.background='rgba(255,255,255,0.06)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.03)'"
                           <?php endif; ?>>
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php
                    // Show last page if not in range
                    if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span style="color: var(--text-dim); padding: 0 0.25rem;">...</span>
                        <?php endif; ?>
                        <a href="?view=overview&page=<?php echo $totalPages; ?>" 
                           style="padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 0.5rem; color: var(--text-main); text-decoration: none; font-size: 0.85rem; min-width: 40px; text-align: center; transition: all 0.3s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.06)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                            <?php echo $totalPages; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?view=overview&page=<?php echo $currentPage + 1; ?>" 
                           style="padding: 0.5rem 0.75rem; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 0.5rem; color: var(--text-main); text-decoration: none; font-size: 0.85rem; transition: all 0.3s;"
                           onmouseover="this.style.background='rgba(255,255,255,0.06)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.03)'">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            </div>

            <!-- Floating Bulk Action Bar -->
            <div class="bulk-bar" id="bulkBar">
                <div class="bulk-selection-info">
                    <span id="selectedCount">0</span>
                    <span style="font-size: 0.8rem; font-weight: 600; color: #94a3b8;">Items Selected</span>
                </div>
                <div class="bulk-inner">
                    <select id="downloadMode" class="select-glass" style="background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #fff; padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                        <option value="qr_only" style="background: #1e293b;">QR Codes Hub</option>
                        <option value="whole_card" style="background: #1e293b;" selected>Whole Card PDF</option>
                    </select>
                    
                    <select id="pdfLayout" class="select-glass" style="background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #fff; padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                        <option value="roll_1m" style="background: #1e293b;" selected>1 Meter Roll</option>
                        <option value="a4_single" style="background: #1e293b;">A4 Single</option>
                    </select>
                    
                    <select id="bulkCardType" class="select-glass" style="background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: #fff; padding: 0.4rem 0.75rem; font-size: 0.8rem;">
                        <option value="all_links" style="background: #1e293b;">All Links Only</option>
                        <option value="all_links_thankyou" style="background: #1e293b;" selected>All Links + Thank You</option>
                        <option value="thankyou_only" style="background: #1e293b;">Thank You Only</option>
                    </select>

                    <button onclick="bulkDownload()" class="bulk-btn">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button onclick="bulkDownloadZip()" class="bulk-btn">
                        <i class="fas fa-file-zipper"></i> ZIP
                    </button>
                    <button onclick="showChangeBatchModal()" class="bulk-btn">
                        <i class="fas fa-layer-group"></i> Batch
                    </button>
                    <button onclick="bulkDelete()" class="bulk-btn bulk-btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
        <?php elseif ($view === 'edit' && $currentLinkPage): ?>
            <!-- Hyper-Premium EDITOR Header -->
            <div class="top-bar-editor">
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <a href="?view=overview" class="btn-action" style="padding: 0.75rem; background: rgba(255,255,255,0.05); border-radius: 1rem;" title="Exit Editor">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); line-height: 1;">Editor Studio</h2>
                        <div style="display: flex; gap: 1rem; margin-top: 0.25rem;">
                            <a href="<?php echo BASE_URL . '/u/' . $currentLinkPage['token']; ?>" target="_blank" style="font-size: 0.7rem; color: var(--text-dim); text-decoration: none; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fas fa-external-link-alt"></i> linkydz.com/u/<?php echo $currentLinkPage['token']; ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button class="btn-premium" onclick="window.open('<?php echo BASE_URL . '/u/' . $currentLinkPage['token']; ?>', '_blank')"><i class="fas fa-eye"></i> View Live</button>
                    <button class="btn-premium" onclick="saveDesign()" style="background: var(--primary); color: #fff; border: none; box-shadow: 0 4px 15px var(--primary-glow);"><i class="fas fa-save"></i> Publish Changes</button>
                </div>
            </div>

            <div class="editor-container" style="display: flex; gap: 2rem;">
                <!-- Left: Link & Design Management -->
                <div class="editor-main">
                    
                    <!-- Asset Details -->
                    <div class="glass-card" style="padding: 2.5rem;">
                        <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; margin-bottom: 0.5rem;">Asset Details</h3>
                        <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 1.5rem;">Refine your identity and batch classification.</p>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div class="form-group">
                                <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; display: block;">Profile Name</label>
                                <input type="text" id="profileName" value="<?php echo htmlspecialchars($currentLinkPage['profile_name']); ?>" placeholder="e.g. My Premium Page" class="input-glass" oninput="document.getElementById('previewName').innerText = this.value;">
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; display: block;">Phone Number</label>
                                <input type="tel" id="phoneNumber" value="<?php echo htmlspecialchars($currentLinkPage['phone_number'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars(PHONE_COUNTRY_CODE !== '' ? '+' . PHONE_COUNTRY_CODE . ' XXX XXX XXX' : 'Phone number'); ?>" class="input-glass" oninput="document.getElementById('previewPhone').innerText = this.value;">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; display: block;">Activity Name (Batch Ref)</label>
                            <input type="text" id="tokenReference" value="<?php echo htmlspecialchars($currentLinkPage['reference'] ?? ''); ?>" placeholder="e.g. Batch July" class="input-glass">
                        </div>

                        <?php
                        // Fetch portal credentials for this token
                        $stmtCred = $db->prepare("SELECT password_plain FROM tokens WHERE id = ?");
                        $stmtCred->execute([$currentLinkPage['token_id']]);
                        $portalPass = $stmtCred->fetchColumn();
                        ?>
                        <div class="credentials-box">
                            <div class="credential-item">
                                <div class="label">Portal Token</div>
                                <div class="value credential-token"><?php echo htmlspecialchars($currentLinkPage['token']); ?></div>
                            </div>
                            <div class="credential-item">
                                <div class="label">Portal Password</div>
                                <div class="value credential-pass">
                                    <?php echo $portalPass ? htmlspecialchars($portalPass) : '<span style="opacity:0.4;font-size:0.85rem;">Not set</span>'; ?>
                                </div>
                            </div>
                            <div style="margin-left: auto; display: flex; gap: 0.5rem;">
                                <button type="button" onclick="copyLink('<?php echo htmlspecialchars($currentLinkPage['token']); ?>:<?php echo htmlspecialchars($portalPass ?? ''); ?>')" class="btn-action" style="padding: 0.6rem 1rem; border-radius: 0.75rem;" title="Copy Credentials"><i class="fas fa-copy"></i></button>
                                <a href="portal-login" target="_blank" class="btn-action" style="padding: 0.6rem 1rem; border-radius: 0.75rem;" title="Open Portal"><i class="fas fa-external-link-alt"></i></a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Link List -->
                    <div class="glass-card" style="padding: 2.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <div>
                                <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem;">Link Management</h3>
                                <p style="font-size: 0.85rem; color: var(--text-dim);">Curate your landing page experience.</p>
                            </div>
                            <button class="btn-premium" onclick="showAddLinkForm()" style="background: var(--primary); color: #fff; border: none;"><i class="fas fa-plus"></i> Add Link</button>
                        </div>
                        
                        <div id="addLinkForm" style="display: none; background: rgba(255,255,255,0.03); padding: 2rem; border-radius: 1.5rem; margin-bottom: 2rem; border: 1px solid var(--glass-border);">
                            <h4 id="addLinkTitle" style="margin-bottom: 1.5rem; font-weight: 800; font-size: 1.1rem;">Customize Link</h4>
                            
                            <label style="display: block; margin-bottom: 0.75rem; font-size: 0.7rem; text-transform: uppercase; color: var(--text-dim); font-weight: 700;">Quick Presets</label>
                            <div class="social-templates">
                                <button type="button" class="template-btn" onclick="applyTemplate('Instagram', 'https://instagram.com/')" title="Instagram"><i class="fab fa-instagram"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('TikTok', 'https://tiktok.com/@')" title="TikTok"><i class="fab fa-tiktok"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('YouTube', 'https://youtube.com/@')" title="YouTube"><i class="fab fa-youtube"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('Twitter', 'https://twitter.com/')" title="Twitter"><i class="fab fa-twitter"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('WhatsApp', 'https://wa.me/')" title="WhatsApp"><i class="fab fa-whatsapp"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('Facebook', 'https://facebook.com/')" title="Facebook"><i class="fab fa-facebook"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('LinkedIn', 'https://linkedin.com/in/')" title="LinkedIn"><i class="fab fa-linkedin"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('Telegram', 'https://t.me/')" title="Telegram"><i class="fab fa-telegram"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('Spotify', 'https://open.spotify.com/user/')" title="Spotify"><i class="fab fa-spotify"></i></button>
                                <button type="button" class="template-btn" onclick="applyTemplate('Snapchat', 'https://snapchat.com/add/')" title="Snapchat"><i class="fab fa-snapchat"></i></button>
                                <button type="button" class="template-btn" onclick="applyGoogleReviewTemplate()" title="Rate on Google"><i class="fab fa-google"></i></button>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                                <div class="form-group">
                                    <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Button Title</label>
                                    <input type="text" id="linkTitle" placeholder="e.g. Follow on Instagram" class="input-glass">
                                </div>
                                <div class="form-group">
                                    <label style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700;">Target URL</label>
                                    <input type="url" id="linkUrl" placeholder="https://..." class="input-glass" oninput="handleUrlInput()">
                                </div>
                            </div>
                            <div id="googleReviewHelper" style="display: none; background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 1rem; padding: 1.25rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; align-items: start; gap: 0.75rem;">
                                    <i class="fas fa-info-circle" style="color: var(--primary); margin-top: 0.2rem;"></i>
                                    <div style="flex: 1;">
                                        <p style="font-size: 0.85rem; font-weight: 700; margin-bottom: 0.3rem; color: var(--primary);">Google Review Link</p>
                                        <p style="font-size: 0.75rem; color: var(--text-dim); line-height: 1.5;">Paste your Google Maps business link and we'll automatically convert it to a review link!</p>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 1rem;">
                                <button class="btn-premium" onclick="addLink()" style="flex: 2; background: var(--primary); color: #fff; border: none;">Save Changes</button>
                                <button class="btn-premium" onclick="hideAddLinkForm()" style="flex: 1;">Cancel</button>
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
                                <?php foreach ($links as $link): ?>
                                    <div class="link-item" data-id="<?php echo $link['id']; ?>">
                                        <div class="link-icon-v2">
                                            <?php 
                                            // Simple icon logic for dashboard list
                                            $icon = 'fa-link';
                                            $url = strtolower($link['url']);
                                            if(strpos($url, 'instagram.com') !== false) $icon = 'fa-instagram fab';
                                            else if(strpos($url, 'tiktok.com') !== false) $icon = 'fa-tiktok fab';
                                            else if(strpos($url, 'youtube.com') !== false) $icon = 'fa-youtube fab';
                                            else if(strpos($url, 'twitter.com') !== false) $icon = 'fa-twitter fab';
                                            else if(strpos($url, 'wa.me') !== false) $icon = 'fa-whatsapp fab';
                                            else if(strpos($url, 'facebook.com') !== false) $icon = 'fa-facebook fab';
                                            else $icon = 'fa-link fas';
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="link-info" style="flex: 1;">
                                            <h4 style="font-weight: 700; font-size: 1rem; color: var(--text-main); letter-spacing: -0.01em;"><?php echo htmlspecialchars($link['title']); ?></h4>
                                            <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 0.15rem; opacity: 0.6;"><?php echo htmlspecialchars($link['url']); ?></p>
                                        </div>
                                        <div style="display: flex; gap: 0.4rem;">
                                            <button onclick="editLink(<?php echo $link['id']; ?>)" class="btn-action" style="padding: 0.6rem;" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button onclick="toggleLink(<?php echo $link['id']; ?>)" class="btn-action" style="padding: 0.6rem;" title="<?php echo $link['is_active'] ? 'Disable' : 'Enable'; ?>">
                                                <i class="fas <?php echo $link['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                            </button>
                                            <button onclick="deleteLink(<?php echo $link['id']; ?>)" class="btn-action" style="padding: 0.6rem; color: var(--error);" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Style Studio (Appearance) -->
                    <div class="glass-card" style="margin-top: 2.5rem;">
                        <div class="studio-header">
                            <div>
                                <h3>Style Studio</h3>
                                <p>Fine-tune your page's visual identity</p>
                            </div>
                        </div>
                        
                    <!-- Style Studio (Appearance) -->
                    <div class="glass-card" style="padding: 2.5rem; margin-top: 2rem;">
                        <h3 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.25rem; margin-bottom: 0.5rem;">Visual Identity</h3>
                        <p style="font-size: 0.85rem; color: var(--text-dim); margin-bottom: 2rem;">Configure your brand presence and color palette.</p>
                        
                        <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2.5rem; padding: 1.5rem; background: rgba(255,255,255,0.03); border-radius: 1.5rem; border: 1px solid var(--glass-border); position: relative; overflow: hidden;">
                            <div id="pfpDisplay" style="width: 80px; height: 80px; background: #000; border-radius: 1.5rem; overflow: hidden; border: 2px solid rgba(255,255,255,0.1); flex-shrink: 0;">
                                <?php if ($currentLinkPage['profile_image']): ?>
                                    <img src="<?php echo PFP_URL . $currentLinkPage['profile_image']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; color: var(--primary); background: rgba(255,255,255,0.02);"><?php echo substr($currentLinkPage['profile_name'], 0, 1); ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.25rem;">Profile Avatar</h4>
                                <input type="file" id="pfpInput" accept="image/*" style="display: none;" onchange="uploadProfileImage()">
                                <button class="btn-premium" onclick="document.getElementById('pfpInput').click()" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;"><i class="fas fa-camera"></i> Change Photo</button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 2.5rem;">
                            <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 1rem; display: block;">Design Template</label>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                                <?php 
                                $templates = [
                                    'simple' => 'Modern', 
                                    'glass' => 'Glass', 
                                    'aurora' => 'Aurora', 
                                    'neon' => 'Neon', 
                                    'social' => 'Social', 
                                    'mesh' => 'Mesh', 
                                    'minimal' => 'Dark', 
                                    'geometry' => 'Shapes',
                                    'sunset' => 'Sunset',
                                    'particles' => 'Flow',
                                    'image' => 'Image'
                                ];
                                foreach($templates as $tid => $tlabel): ?>
                                    <button type="button" class="template-btn <?php echo ($designSettings['template_id']??'simple') == $tid ? 'active-template' : ''; ?>" onclick="setTemplate('<?php echo $tid; ?>')" data-template="<?php echo $tid; ?>">
                                        <div class="theme-thumbnail <?php echo $tid; ?>"></div>
                                        <span><?php echo $tlabel; ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="templateId" value="<?php echo $designSettings['template_id'] ?? 'simple'; ?>">
                            <input type="hidden" id="bgImage" value="<?php echo htmlspecialchars($designSettings['bg_image'] ?? ''); ?>">
                        </div>

                        <!-- Background Customization (Visible for 'image' template) -->
                        <div id="bgImageGroup" style="display: <?php echo ($designSettings['template_id']??'') === 'image' ? 'block' : 'none'; ?>; margin-bottom: 2rem; padding: 1.5rem; background: rgba(99, 102, 241, 0.05); border-radius: 1.25rem; border: 1px dashed var(--primary);">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div id="bgImagePreview" style="font-size: 0.8rem; font-weight: 700; color: var(--primary);">
                                    <?php echo ($designSettings['bg_image']??'') ? 'Background set ✨' : 'No background set'; ?>
                                </div>
                                <input type="file" id="bgImageInput" accept="image/*" style="display: none;" onchange="uploadBackgroundImage()">
                                <button type="button" class="btn-premium" onclick="document.getElementById('bgImageInput').click()" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;"><i class="fas fa-image"></i> Import Image</button>
                            </div>
                        </div>

                        <!-- Color Customization -->
                        <div id="colorControls" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; padding: 1.5rem; background: rgba(255,255,255,0.02); border-radius: 1.25rem; border: 1px solid var(--glass-border); margin-bottom: 2rem;">
                            <div class="form-group">
                                <label style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; display: block;">Background</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="color" id="backgroundColor" value="<?php echo $designSettings['background_color'] ?? '#0F172A'; ?>" style="width: 32px; height: 32px; border: none; background: none; cursor: pointer;" oninput="document.getElementById('backgroundColorText').value = this.value; updatePreview()">
                                    <input type="text" id="backgroundColorText" value="<?php echo $designSettings['background_color'] ?? '#0F172A'; ?>" class="input-glass" style="height: 32px; padding: 0 0.5rem; font-size: 0.7rem; width: 65px;" oninput="syncColorTextToPicker(this, 'backgroundColor')">
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; display: block;">Accent</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="color" id="buttonColor" value="<?php echo $designSettings['button_color'] ?? '#6366f1'; ?>" style="width: 32px; height: 32px; border: none; background: none; cursor: pointer;" oninput="document.getElementById('buttonColorText').value = this.value; updatePreview()">
                                    <input type="text" id="buttonColorText" value="<?php echo $designSettings['button_color'] ?? '#6366f1'; ?>" class="input-glass" style="height: 32px; padding: 0 0.5rem; font-size: 0.7rem; width: 65px;" oninput="syncColorTextToPicker(this, 'buttonColor')">
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; display: block;">Text</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="color" id="textColor" value="<?php echo $designSettings['text_color'] ?? '#ffffff'; ?>" style="width: 32px; height: 32px; border: none; background: none; cursor: pointer;" oninput="document.getElementById('textColorText').value = this.value; updatePreview()">
                                    <input type="text" id="textColorText" value="<?php echo $designSettings['text_color'] ?? '#ffffff'; ?>" class="input-glass" style="height: 32px; padding: 0 0.5rem; font-size: 0.7rem; width: 65px;" oninput="syncColorTextToPicker(this, 'textColor')">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 0.75rem; display: block;">Brand Typography</label>
                            <select id="fontFamily" class="select-glass" style="height: 48px; font-size: 0.9rem;">
                                <option value="Inter" <?php echo ($designSettings['font_family']??'') == 'Inter' ? 'selected' : ''; ?>>Inter (Modern)</option>
                                <option value="Outfit" <?php echo ($designSettings['font_family']??'') == 'Outfit' ? 'selected' : ''; ?>>Outfit (High-End)</option>
                                <option value="Playfair Display" <?php echo ($designSettings['font_family']??'') == 'Playfair Display' ? 'selected' : ''; ?>>Playfair (Premium)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Right: iPhone Mockup Preview -->
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
                                        <?php if ($currentLinkPage['profile_image']): ?>
                                            <img src="<?php echo PFP_URL . $currentLinkPage['profile_image']; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($currentLinkPage['profile_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <h3 id="previewName" style="margin-bottom: 0.5rem; font-weight: 800; color: #fff; letter-spacing: -0.04em; font-size: 1.6rem;"><?php echo htmlspecialchars($currentLinkPage['profile_name']); ?></h3>
                                <p id="previewPhone" style="color: rgba(255,255,255,0.7); font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 500;"><?php echo htmlspecialchars($currentLinkPage['phone_number'] ?? ''); ?></p>
                                <div id="previewCallButton" style="display: none; margin-bottom: 1rem;">
                                    <a href="tel:" class="preview-btn" style="background: rgba(0, 255, 136, 0.15); border-color: rgba(0, 255, 136, 0.3); color: #00ff88;">
                                        <i class="fas fa-phone"></i> Call
                                    </a>
                                </div>
                                <p id="previewTagline" style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-bottom: 3rem;">Connect with me</p>
                                
                                <div id="previewButtons">
                                    <!-- Dynamic content here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    <!-- Batch Modals -->
    <div id="batchModal" class="modal">
        <div class="modal-content glass-card">
            <h3 class="section-title" style="margin-bottom: 1.5rem; font-size: 1.5rem;">Bulk Generation</h3>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.5rem;">Amount</label>
                <input type="number" id="batchCount" value="10" min="1" max="500" class="input-glass">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.75rem;">Pre-populate Links</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-dim); cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" class="social-template-chk" value="facebook"> Facebook
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-dim); cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" class="social-template-chk" value="instagram"> Instagram
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-dim); cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" class="social-template-chk" value="tiktok"> TikTok
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-dim); cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" class="social-template-chk" value="review"> Review us on Google
                    </label>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.5rem;">Batch Target</label>
                <select id="batchRefType" onchange="toggleRefFields()" class="select-glass" style="width: 100%;">
                    <option value="new">Generate New Alpha Reference</option>
                    <option value="existing">Add to Existing Batch</option>
                </select>
            </div>
            <div id="newRefGroup">
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.5rem;">New Label (Optional)</label>
                <input type="text" id="batchRefLabel" placeholder="e.g. 2026-X" class="input-glass">
            </div>
            <div id="existingRefGroup" style="display:none;">
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.5rem;">Select Batch</label>
                <select id="batchRefExisting" class="select-glass" style="width: 100%;">
                    <?php 
                    $refStmt = $db->query("SELECT DISTINCT reference FROM tokens WHERE reference IS NOT NULL AND reference != '' ORDER BY reference DESC");
                    $refs = $refStmt->fetchAll(PDO::FETCH_COLUMN);
                    if(empty($refs)) echo "<option value=''>No active batches</option>";
                    foreach($refs as $ref) {
                        echo "<option value=\"".htmlspecialchars($ref)."\">".htmlspecialchars($ref)."</option>";
                    }
                    ?>
                </select>
            </div>
            <div id="genConsole" style="display:none; margin-top: 1.5rem; padding: 1rem; background: #000; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.75rem; color: #0f0; max-height: 150px; overflow-y: auto; border: 1px solid #333;">
                <div id="consoleLog"></div>
            </div>
            <div id="genProgress" style="display:none; margin-top: 1rem; background: rgba(255,255,255,0.1); border-radius: 99px; height: 8px; overflow: hidden;">
                <div id="genProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, var(--accent), var(--secondary)); transition: width 0.3s ease;"></div>
            </div>
            <div class="modal-actions" style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button class="btn-premium" style="flex: 1;" onclick="performBatchGen()" id="btnDoBatch">Generate & Create</button>
                <button class="btn-glass" style="flex: 1;" onclick="hideBatchModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="changeBatchModal" class="modal">
        <div class="modal-content glass-card">
            <h3 class="section-title" style="margin-bottom: 1rem; font-size: 1.5rem;">Change Batch</h3>
            <p style="color: var(--text-dim); font-size: 0.85rem; margin-bottom: 1.5rem;">Update batch reference for <span id="changeBatchCount" class="badge">0</span> selected assets.</p>
            
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.5rem;">Reference Label</label>
                <input type="text" id="changeBatchRef" list="refList" placeholder="Enter batch label..." class="input-glass">
                <datalist id="refList">
                    <?php foreach($refs as $ref): ?>
                        <option value="<?php echo htmlspecialchars($ref); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="modal-actions" style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button class="btn-premium" style="flex: 1; background: var(--secondary);" onclick="performUpdateBatch()" id="btnUpdateBatch">Update All</button>
                <button class="btn-glass" style="flex: 1;" onclick="hideChangeBatchModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="newPageModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 1.5rem;">Generate New QR Card</h2>
            <div class="form-group">
                <label>Profile Name / Brand</label>
                <input type="text" id="newProfileName" placeholder="e.g. Your Profile Name" class="input-glass">
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="generateQRCard()">Generate & Create</button>
                <button class="btn btn-secondary" onclick="hideNewPageModal()">Cancel</button>
            </div>
            <div id="qrResult" style="margin-top: 2rem; display: none; text-align: center;">
                <p style="color: var(--success); font-weight: bold; margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> Card Generated!</p>
                <img id="qrResultImg" style="width: 100%; border-radius: 0.5rem; margin-bottom: 1rem;">
                <a id="qrDownloadLink" class="btn btn-primary" download>Download PNG</a>
            </div>
        </div>
    </div>

    <div id="activationModal" class="modal">
        <div class="modal-content">
            <h2 style="margin-bottom: 0.5rem;">Activate Card</h2>
            <p style="color: var(--text-dim); font-size: 0.85rem; margin-bottom: 2rem;">Claiming token: <span id="activeTokenDisplay" class="badge">XXXXXX</span></p>
            
            <input type="hidden" id="activeTokenId">
            <div class="form-group">
                <label>Profile Name / Title</label>
                <input type="text" id="activeProfileName" placeholder="e.g. My Premium Page" class="input-glass">
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="performActivation()"><i class="fas fa-bolt"></i> Activate Now</button>
                <button class="btn btn-secondary" onclick="hideActivationModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="loadingOverlay"><span class="loading"></span></div>

    <!-- QR View Modal -->
    <div id="qrViewModal" class="modal" onclick="closeQRModal()">
        <div class="modal-content" onclick="event.stopPropagation()" style="width: auto; max-width: 400px; text-align: center; padding: 2.5rem;">
            <h2 style="margin-bottom: 2rem;">Digital Identity QR</h2>
            <img id="qrViewImg" src="" style="width: 100%; border-radius: 1.5rem; box-shadow: 0 20px 50px rgba(0,0,0,0.5); margin-bottom: 2rem;">
            <div style="display: flex; gap: 1rem;">
                <a id="qrViewDownload" href="" download class="btn btn-primary" style="flex: 1;"><i class="fas fa-download"></i> Download</a>
                <button class="btn btn-secondary" onclick="closeQRModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Print Card Modal -->
    <div id="printModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <h2 style="margin-bottom: 0.5rem;">Print Card</h2>
            <p style="color: var(--text-dim); font-size: 0.85rem; margin-bottom: 2rem;">Configure print options for token: <span id="printTokenDisplay" class="badge">XXXXXX</span></p>
            
            <input type="hidden" id="printTokenId">
            <input type="hidden" id="printToken">
            
            <!-- Print Mode Selection -->
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.75rem; display: block;">Print Mode</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <button type="button" class="print-mode-btn active" data-mode="qr_only" onclick="selectPrintMode('qr_only')" style="padding: 1rem; background: rgba(99, 102, 241, 0.1); border: 2px solid var(--primary); border-radius: 1rem; color: #fff; cursor: pointer; transition: all 0.3s;">
                        <i class="fas fa-qrcode" style="display: block; font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                        <span style="font-size: 0.85rem; font-weight: 600;">QR Only</span>
                    </button>
                    <button type="button" class="print-mode-btn" data-mode="whole_card" onclick="selectPrintMode('whole_card')" style="padding: 1rem; background: rgba(255,255,255,0.03); border: 2px solid var(--glass-border); border-radius: 1rem; color: #fff; cursor: pointer; transition: all 0.3s;">
                        <i class="fas fa-id-card" style="display: block; font-size: 1.5rem; margin-bottom: 0.5rem;"></i>
                        <span style="font-size: 0.85rem; font-weight: 600;">Full Card</span>
                    </button>
                </div>
            </div>

            <div class="form-group" id="cardTypeGroup" style="margin-bottom: 1.5rem; display: none;">
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.75rem; display: block;">Card Type</label>
                <select id="printCardType" class="select-glass" style="width: 100%;">
                    <option value="all_links">All Links Card (13&times;18 cm)</option>
                    <option value="print_all">All Links + Thank You Card (2 pages)</option>
                </select>
            </div>

            <!-- Print Size Selection -->
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-dim); margin-bottom: 0.75rem; display: block;">Print Size</label>
                <select id="printSize" class="select-glass" style="width: 100%;">
                    <option value="a4">A4 Page (Single Card)</option>
                    <option value="custom">Custom QR Size</option>
                </select>
            </div>

            <!-- Card Preview -->
            <div id="cardPreviewWrap" style="display:flex;justify-content:center;margin-bottom:1.5rem;">
                <div id="cardPreview" style="width:210px;height:296px;background:#fff;border-radius:6px;box-shadow:0 4px 20px rgba(0,0,0,0.3);position:relative;overflow:hidden;font-family:Arial,sans-serif;" dir="ltr">
                    <div id="cpBg" style="position:absolute;inset:0;background:#fff;"></div>
                    <div id="cpName" style="position:absolute;top:34%;left:0;width:100%;text-align:center;font-size:18px;font-weight:700;color:#000;"></div>
                    <div id="cpUrl" style="position:absolute;top:43%;left:0;width:100%;text-align:center;font-size:13px;font-weight:700;color:#000;"></div>
                    <div id="cpBox" style="position:absolute;top:51%;left:6%;width:88%;height:28%;background:rgba(0,0,0,0.04);border:1px solid rgba(0,0,0,0.12);border-radius:4px;"></div>
                    <div id="cpQr" style="position:absolute;top:53%;left:8%;width:32%;height:24%;background:#eee;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;font-size:9px;color:#999;">QR</div>
                    <div id="cpCreds" style="position:absolute;top:53%;right:8%;width:52%;height:24%;display:flex;flex-direction:column;justify-content:center;align-items:flex-end;font-size:10px;color:#000;">
                        <div id="cpLabel" style="font-size:10px;margin-bottom:2px;"></div>
                        <div id="cpId" style="font-weight:700;font-size:11px;margin-bottom:2px;"></div>
                        <div id="cpPass" style="font-weight:700;font-size:11px;margin-bottom:2px;"></div>
                        <div id="cpScan" style="font-size:9px;"></div>
                    </div>
                    <div id="cpFooter" style="position:absolute;bottom:6%;left:0;width:100%;text-align:center;font-size:9px;color:#000;"></div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button class="btn-premium" style="flex: 1;" onclick="generatePrintPDF()"><i class="fas fa-print"></i> Generate PDF</button>
                <button class="btn-glass" style="flex: 1;" onclick="closePrintModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const API_URL = '<?php echo BASE_URL; ?>';
        const PFP_URL = '<?php echo PFP_URL; ?>';
        const EDIT_PAGE_ID = <?php echo $editId ?? 'null'; ?>;
        const ALL_TOKENS = <?php echo json_encode(array_column($allPages, 'token')); ?>;
        const ALL_TOKEN_IDS = <?php echo json_encode(array_column($allPages, 'token_id')); ?>;
        const TOTAL_ITEMS_COUNT = <?php echo count($allPages); ?>;
        const QR_RAW_URL = '<?php echo $rawUrl; ?>';
        const ALL_PAGES_DATA = <?php echo json_encode($allPages); ?>;

        function copyLink(url) {
            navigator.clipboard.writeText(url).then(() => {
                showAlert('Link copied to clipboard! 📋');
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }

        function viewQR(url) {
            document.getElementById('qrViewImg').src = url;
            document.getElementById('qrViewDownload').href = url;
            document.getElementById('qrViewModal').style.display = 'flex';
        }

        function closeQRModal() {
            document.getElementById('qrViewModal').style.display = 'none';
        }

        // Live Clock
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('en-US', { hour12: false });
            const el = document.getElementById('liveClock');
            if(el) el.innerText = time;
        }
        setInterval(updateClock, 1000);
        updateClock();

        function showLoading(show) { document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none'; }
        
        function showAlert(msg, type = 'success') {
            const el = document.getElementById('mainAlert');
            el.className = `alert alert-${type}`;
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            el.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
            el.style.display = 'block';
            setTimeout(() => el.style.display = 'none', 4000);
        }

        // NAVIGATION & MODALS
        function showNewPageModal() { document.getElementById('newPageModal').style.display = 'flex'; }
        function hideNewPageModal() { document.getElementById('newPageModal').style.display = 'none'; }

        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const icon = document.querySelector('.mobile-toggle i');
            sidebar.classList.toggle('mobile-active');
            if(sidebar.classList.contains('mobile-active')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        }

        // TOKEN ACTIVATION FLOW
        function showActivationModal(id, token) {
            document.getElementById('activeTokenId').value = id;
            document.getElementById('activeTokenDisplay').innerText = token;
            document.getElementById('activeProfileName').value = '';
            document.getElementById('activationModal').style.display = 'flex';
        }

        function hideActivationModal() {
            document.getElementById('activationModal').style.display = 'none';
        }

        async function performBatchGen() {
            const count = parseInt(document.getElementById('batchCount').value);
            const refType = document.getElementById('batchRefType').value;
            const reference = refType === 'new' ? document.getElementById('batchRefLabel').value : document.getElementById('batchRefExisting').value;
            
            // Collect selected social platforms
            const selectedTemplates = Array.from(document.querySelectorAll('.social-template-chk:checked')).map(cb => cb.value);
            
            const btn = document.getElementById('btnDoBatch');
            const consoleArea = document.getElementById('genConsole');
            const consoleLog = document.getElementById('consoleLog');
            const progressArea = document.getElementById('genProgress');
            const progressBar = document.getElementById('genProgressBar');

            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing...';
            btn.disabled = true;

            consoleArea.style.display = 'block';
            progressArea.style.display = 'block';
            consoleLog.innerHTML = '<div>> Starting batch generation process...</div>';
            progressBar.style.width = '0%';

            try {
                // Split large batches into chunks of 10 for better UI feedback and stability
                const chunkSize = 20;
                let generated = 0;
                const totalChunks = Math.ceil(count / chunkSize);
                let currentRef = reference;

                for (let i = 0; i < totalChunks; i++) {
                    const currentCount = Math.min(chunkSize, count - generated);
                    consoleLog.innerHTML += `<div>> Processing chunk ${i + 1}/${totalChunks} (${currentCount} items)...</div>`;
                    consoleArea.scrollTop = consoleArea.scrollHeight;
                    
                    const res = await fetch('api-generate-batch', {
                        method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type': 'application/json'},
                        body: JSON.stringify({ 
                            count: currentCount, 
                            ref_type: (i === 0 ? refType : 'existing'), 
                            reference: (i === 0 ? reference : currentRef),
                            templates: selectedTemplates 
                        })
                    });
                    
                    const data = await res.json();
                    if(data.success) {
                        generated += data.generated;
                        currentRef = data.reference;
                        const pct = Math.round((generated / count) * 100);
                        progressBar.style.width = pct + '%';
                        consoleLog.innerHTML += `<div style="color: #6ee7b7">> Success: +${data.generated} tokens [Ref: ${currentRef}]</div>`;
                    } else {
                        consoleLog.innerHTML += `<div style="color: #f87171">> Chunk Failed: ${data.error}</div>`;
                        if(data.errors) data.errors.forEach(err => consoleLog.innerHTML += `<div style="color: #f87171; opacity: 0.7">  - ${err}</div>`);
                    }
                    consoleArea.scrollTop = consoleArea.scrollHeight;
                }

                consoleLog.innerHTML += `<div style="color: var(--accent); font-weight: bold; margin-top: 5px;">> ALL DONE! Total generated: ${generated}</div>`;
                btn.innerHTML = '<i class="fas fa-check"></i> Refined!';
                setTimeout(() => location.reload(), 2000);

            } catch(e) { 
                consoleLog.innerHTML += `<div style="color: #f87171">> Critical Error: ${e.message}</div>`;
                btn.innerHTML = originalText; 
                btn.disabled = false; 
            }
        }



        async function performActivation() {
            const id = document.getElementById('activeTokenId').value;
            const name = document.getElementById('activeProfileName').value;
            
            if(!name) return alert('Please enter a profile name');
            
            showLoading(true);
            try {
                const response = await fetch('api-pages.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ token_id: id, profile_name: name })
                });
                
                const result = await response.json();
                if(result.success) {
                    window.location.href = `?view=edit&edit_id=${result.link_page_id}`;
                } else {
                    alert(result.error || 'Activation failed');
                    showLoading(false);
                }
            } catch (err) {
                console.error(err);
                alert('Connection error');
                showLoading(false);
            }
        }

        // QR GENERATION
        async function generateQRCard() {
            const name = document.getElementById('newProfileName').value;
            if(!name) return alert('Enter a profile name');
            
            showLoading(true);
            try {
                const res = await fetch('api-qr.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({profile_name: name})
                });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('qrResultImg').src = data.card_url;
                    document.getElementById('qrDownloadLink').href = data.card_url;
                    document.getElementById('qrResult').style.display = 'block';
                    showAlert('Page created successfully!');
                    setTimeout(() => location.href = `?view=overview`, 3000);
                } else {
                    alert(data.error);
                }
            } catch(e) { alert('Error generating card'); }
            showLoading(false);
        }

        // LINK MANAGEMENT (Editor mode)
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

        function applyTemplate(title, url) {
            document.getElementById('linkTitle').value = title;
            document.getElementById('linkUrl').value = url;
            handleUrlInput();
            document.getElementById('linkUrl').focus();
        }

        function applyGoogleReviewTemplate() {
            document.getElementById('linkTitle').value = 'Rate us on Google';
            document.getElementById('linkUrl').value = '';
            document.getElementById('linkUrl').focus();
            document.getElementById('googleReviewHelper').style.display = 'block';
        }

        function handleUrlInput() {
            const urlInput = document.getElementById('linkUrl');
            const url = urlInput.value;
            const helper = document.getElementById('googleReviewHelper');
            
            // Check if this looks like a Google Maps link and helper is visible
            if (helper.style.display === 'block' && (url.includes('google.com/maps') || url.includes('maps.app.goo.gl'))) {
                
                // If it's a shortened link, we need to expand it first
                if (url.includes('maps.app.goo.gl') || url.includes('goo.gl')) {
                    // Show processing message
                    helper.innerHTML = `
                        <div style="display: flex; align-items: start; gap: 0.75rem;">
                            <i class="fas fa-spinner fa-spin" style="color: var(--primary); margin-top: 0.2rem;"></i>
                            <div style="flex: 1;">
                                <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--primary);">Processing...</p>
                                <p style="font-size: 0.75rem; color: var(--text-dim); line-height: 1.5;">Expanding shortened link to extract Place ID...</p>
                            </div>
                        </div>
                    `;
                    
                    // Fetch the expanded URL
                    fetch(`api-expand-url?url=${encodeURIComponent(url)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.expanded_url) {
                                extractPlaceIdAndConvert(data.expanded_url, urlInput, helper);
                            } else {
                                showErrorHelper(helper);
                            }
                        })
                        .catch(() => {
                            // Fallback: try to extract from the original URL
                            extractPlaceIdAndConvert(url, urlInput, helper);
                        });
                } else {
                    // Direct extraction for full URLs
                    extractPlaceIdAndConvert(url, urlInput, helper);
                }
            }
        }
        
        function extractPlaceIdAndConvert(url, urlInput, helper) {
            // New approach: Use Google Maps URL with review action
            // This works more reliably than trying to extract Place IDs
            
            let reviewUrl = null;
            
            // Check if it's already a Google Maps URL
            if (url.includes('google.com/maps')) {
                // Add review action to the URL
                // Remove any existing query parameters that might conflict
                const baseUrl = url.split('?')[0].split('#')[0];
                
                // If the URL has place data, append review action
                if (url.includes('/place/') || url.includes('q=') || url.includes('data=')) {
                    // Use the original URL but ensure it opens the review dialog
                    reviewUrl = url.includes('?') ? url + '&action=review' : url + '?action=review';
                } else {
                    // Fallback to original URL
                    reviewUrl = url;
                }
            }
            
            if (reviewUrl) {
                urlInput.value = reviewUrl;
                
                // Show success feedback
                helper.innerHTML = `
                    <div style="display: flex; align-items: start; gap: 0.75rem;">
                        <i class="fas fa-check-circle" style="color: #10b981; margin-top: 0.2rem;"></i>
                        <div style="flex: 1;">
                            <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: #10b981;">✓ Review Link Ready!</p>
                            <p style="font-size: 0.75rem; color: var(--text-dim); line-height: 1.5;">Customers will be taken to your Google Maps page where they can leave a review.</p>
                        </div>
                    </div>
                `;
            } else {
                showErrorHelper(helper);
            }
        }
        
        function showErrorHelper(helper) {
            helper.innerHTML = `
                <div style="display: flex; align-items: start; gap: 0.75rem;">
                    <i class="fas fa-exclamation-triangle" style="color: #f59e0b; margin-top: 0.2rem;"></i>
                    <div style="flex: 1;">
                        <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: #f59e0b;">Couldn't Extract Place ID</p>
                        <p style="font-size: 0.75rem; color: var(--text-dim); line-height: 1.5;">Try using the full Google Maps URL from your browser's address bar.</p>
                    </div>
                </div>
            `;
        }

        async function addLink() {
            const title = document.getElementById('linkTitle').value;
            const url = document.getElementById('linkUrl').value;
            if(!title || !url) return alert('Fill all fields');

            showLoading(true);
            try {
                const method = currentEditLinkId ? 'PUT' : 'POST';
                const body = { link_page_id: EDIT_PAGE_ID, title, url };
                
                if(currentEditLinkId) {
                    body.id = currentEditLinkId;
                    const item = document.querySelector(`.link-item[data-id="${currentEditLinkId}"]`);
                    body.is_active = item.querySelector('.fa-eye') !== null;
                }

                const res = await fetch('api-links.php', {
                    method: method,
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type': 'application/json'
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
                
                const res = await fetch('api-links.php', {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type':'application/json'
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
            const res = await fetch(`${API_URL}/api-links.php`, {
                method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type':'application/json'},
                body: JSON.stringify({id})
            });
            location.reload();
        }

        // DESIGN MANAGEMENT — saveDesign() defined below

        async function uploadProfileImage() {
            const fileInput = document.getElementById('pfpInput');
            if (!fileInput.files.length) return;
            
            const formData = new FormData();
            formData.append('profile_image', fileInput.files[0]);
            formData.append('link_page_id', EDIT_PAGE_ID);
            formData.append('upload_type', 'profile');
            
            showLoading(true);
            try {
                const res = await fetch('api-upload.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    // Update both displays
                    const imgHtml = `<img src="${data.url}" style="width: 100%; height: 100%; object-fit: cover;">`;
                    document.getElementById('pfpDisplay').innerHTML = imgHtml;
                    document.getElementById('previewAvatar').innerHTML = imgHtml;
                    showAlert('Profile picture updated!');
                } else {
                    alert(data.error);
                }
            } catch (e) {
                alert('Upload failed');
            }
            showLoading(false);
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

        async function uploadBackgroundImage() {
            const fileInput = document.getElementById('bgImageInput');
            if (!fileInput.files.length) return;
            
            const formData = new FormData();
            formData.append('profile_image', fileInput.files[0]);
            formData.append('link_page_id', EDIT_PAGE_ID);
            formData.append('upload_type', 'background');
            
            showLoading(true);
            try {
                const res = await fetch('api-upload.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('bgImage').value = data.filename;
                    document.getElementById('bgImagePreview').innerText = 'Background set ✨';
                    updatePreview();
                    showAlert('Background image uploaded!');
                } else {
                    alert(data.error);
                }
            } catch (e) {
                alert('Upload failed');
            }
            showLoading(false);
        }

        async function saveDesign() {
            const template = document.getElementById('templateId').value;
            const profileName = document.getElementById('profileName').value;
            const phoneNumber = document.getElementById('phoneNumber').value;
            const reference = document.getElementById('tokenReference').value;

            const settings = {
                template_id: template,
                background_color: document.getElementById('backgroundColor').value,
                button_color: document.getElementById('buttonColor').value,
                text_color: document.getElementById('textColor').value,
                font_family: document.getElementById('fontFamily').value,
                bg_image: document.getElementById('bgImage').value
            };

            const btn = document.querySelector('button[onclick="saveDesign()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
            btn.disabled = true;

            try {
                const res = await fetch('api-design.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        link_page_id: EDIT_PAGE_ID,
                        profile_name: profileName,
                        phone_number: phoneNumber,
                        reference: reference,
                        settings: settings
                    })
                });
                const data = await res.json();
                if(data.success) {
                    showAlert('Design published successfully! 🚀');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert(data.error);
                }
            } catch(e) {
                alert('Connection error');
            }
            btn.innerHTML = originalText;
            btn.disabled = false;
        }

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

            container.innerHTML = html || `<div class="preview-btn" style="justify-content:center;opacity:0.5;border-style:dashed;">No active links</div>`;
        }

        function changeSingleBatch(tokenId, tokenLabel, currentRef) {
            document.getElementById('changeBatchCount').innerText = '1 (' + tokenLabel + ')';
            document.getElementById('changeBatchRef').value = currentRef;
            // Mark the single checkbox and unmark others
            document.querySelectorAll('.bulk-select').forEach(cb => cb.checked = false);
            const targetCb = document.querySelector(`.bulk-select[value="${tokenId}"]`);
            if(targetCb) targetCb.checked = true;
            updateBulkBar();
            document.getElementById('changeBatchModal').style.display = 'flex';
        }

        // OVERVIEW FILTERING
        function filterOverviewTable() {
            const input = document.getElementById('overviewSearch').value.toLowerCase();
            const terms = input.split(' ').filter(t => t.length > 0);
            const rows = document.querySelectorAll('.page-row');
            
            rows.forEach(row => {
                const content = row.innerText.toLowerCase();
                const match = terms.every(term => content.includes(term));
                row.style.display = match ? '' : 'none';
            });
            updateBulkBar();
        }

        function showBatchModal(count = 10) { 
            document.getElementById('batchCount').value = count;
            document.getElementById('batchModal').style.display = 'flex'; 
        }
        function hideBatchModal() { document.getElementById('batchModal').style.display = 'none'; }

        function toggleRefFields() {
            const type = document.getElementById('batchRefType').value;
            document.getElementById('newRefGroup').style.display = type === 'new' ? 'block' : 'none';
            document.getElementById('existingRefGroup').style.display = type === 'existing' ? 'block' : 'none';
        }

        function showChangeBatchModal() {
            const isAllTotal = document.getElementById('selectAllTotal')?.checked;
            const selected = document.querySelectorAll('.bulk-select:checked');
            
            if(!isAllTotal && selected.length === 0) return showAlert('Please select items first', 'error');
            
            const count = isAllTotal ? TOTAL_ITEMS_COUNT : selected.length;
            document.getElementById('changeBatchCount').innerText = count;
            document.getElementById('changeBatchModal').style.display = 'flex';
        }
        function hideChangeBatchModal() { document.getElementById('changeBatchModal').style.display = 'none'; }

        async function performUpdateBatch() {
            const isAllTotal = document.getElementById('selectAllTotal')?.checked;
            const selected = document.querySelectorAll('.bulk-select:checked');
            const ids = isAllTotal ? ALL_TOKEN_IDS : Array.from(selected).map(cb => cb.value);
            const reference = document.getElementById('changeBatchRef').value;
            
            if(!reference) return showAlert('Please enter a reference', 'error');
            
            const btn = document.getElementById('btnUpdateBatch');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            btn.disabled = true;

            try {
                const res = await fetch('api-bulk-action', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'update_batch', token_ids: ids, reference: reference })
                });
                const data = await res.json();
                if(data.success) {
                    showAlert(`Updated ${ids.length} items successfully`);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert('Error: ' + data.error);
                }
            } catch(e) { alert('Request failed'); }
            btn.innerHTML = originalText; btn.disabled = false;
        }

        // PAGE DELETION
        async function deletePage(id) {
            if(!confirm('This will delete the link page AND the associated token/QR record. Continue?')) return;
            
            showLoading(true);
            try {
                const res = await fetch('api-pages.php', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>',
                        'Content-Type':'application/json'
                    },
                    body: JSON.stringify({id})
                });
                const data = await res.json();
                if(data.success) {
                    showAlert('Page deleted successfully');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert(data.error || 'Failed to delete page');
                }
            } catch(e) {
                alert('Connection error');
            }
            showLoading(false);
        }

        // BULK ACTIONS LOGIC
        function toggleSelectAll() {
            const master = document.getElementById('selectAll');
            const masterTotal = document.getElementById('selectAllTotal');
            const checks = document.querySelectorAll('.bulk-select');
            
            // If selecting page, unselect "Total" to avoid confusion
            if (master.checked) {
                if (masterTotal) masterTotal.checked = false;
            }

            checks.forEach(c => {
                const row = c.closest('tr');
                if(row && row.style.display !== 'none') {
                    c.checked = master.checked;
                }
            });
            updateBulkBar();
        }

        function toggleSelectAllTotal() {
            const masterTotal = document.getElementById('selectAllTotal');
            const masterPage = document.getElementById('selectAll');
            const checks = document.querySelectorAll('.bulk-select');
            
            // If selecting total, also check all on current page for visual feedback
            if (masterTotal.checked) {
                if (masterPage) {
                    masterPage.checked = true;
                    masterPage.indeterminate = false;
                }
                checks.forEach(c => {
                    const row = c.closest('tr');
                    if(row && row.style.display !== 'none') {
                        c.checked = true;
                    }
                });
            } else {
                if (masterPage) masterPage.checked = false;
                checks.forEach(c => c.checked = false);
            }
            
            updateBulkBar();
        }

        function updateBulkBar() {
            const isAllTotal = document.getElementById('selectAllTotal')?.checked;
            const checks = document.querySelectorAll('.bulk-select:checked');
            const bar = document.getElementById('bulkBar');
            
            if (bar) {
                const count = isAllTotal ? TOTAL_ITEMS_COUNT : checks.length;
                document.getElementById('selectedCount').innerText = count;
                
                if (count > 0) {
                    bar.classList.add('active');
                } else {
                    bar.classList.remove('active');
                    // Ensure select all page is also unchecked if selecting total was off
                    if (!isAllTotal && checks.length === 0) {
                        const master = document.getElementById('selectAll');
                        if (master) master.checked = false;
                    }
                }
            }
            
            // Also update "Select Page" state if not in "Total" mode
            if (!isAllTotal) {
                const visibleRows = Array.from(document.querySelectorAll('.page-row')).filter(r => r.style.display !== 'none');
                const visibleChecks = visibleRows.map(r => r.querySelector('.bulk-select')).filter(c => c !== null);
                const master = document.getElementById('selectAll');
                if(master && visibleChecks.length > 0) {
                    master.checked = visibleChecks.every(c => c.checked);
                    master.indeterminate = visibleChecks.some(c => c.checked) && !master.checked;
                }
            }
        }

        async function bulkDownload() {
            const isAllTotal = document.getElementById('selectAllTotal')?.checked;
            const checks = document.querySelectorAll('.bulk-select:checked');
            const tokens = isAllTotal ? ALL_TOKENS : Array.from(checks).map(c => c.dataset.token);
            
            if (tokens.length === 0) {
                showToast('Please select at least one item.', 'info');
                return;
            }

            const btn = event?.currentTarget || document.querySelector('button[onclick="bulkDownload()"]');
            const originalText = btn ? btn.innerHTML : 'Download PDF';
            if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            try {
                const response = await fetch('api-bulk-action', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        action: 'download_pdf', 
                        tokens: tokens,
                        download_mode: document.getElementById('downloadMode').value,
                        layout: document.getElementById('pdfLayout').value,
                        card_type: document.getElementById('bulkCardType').value
                    })
                });

                if (response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const err = await response.json();
                        showToast('Error generating PDF: ' + (err.error || 'Unknown error'), 'error');
                        return;
                    }

                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = '<?php echo htmlspecialchars(SITE_SLUG, ENT_QUOTES); ?>-labels.pdf';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                } else {
                    const text = await response.text();
                    console.error('PDF Error:', text);
                    showToast('Failed to generate PDF. Server error.', 'error');
                }
            } catch (error) {
                console.error('Bulk download error:', error);
                showToast('Connection error or unexpected failure.', 'error');
            } finally {
                btn.innerHTML = originalText;
            }
        }

        async function bulkDelete() {
            const isAllTotal = document.getElementById('selectAllTotal')?.checked;
            const checks = document.querySelectorAll('.bulk-select:checked');
            const count = isAllTotal ? TOTAL_ITEMS_COUNT : checks.length;

            if (!confirm(`Are you sure you want to delete ${count} items? This cannot be undone.`)) return;

            const tokenIds = isAllTotal ? ALL_TOKEN_IDS : Array.from(checks).map(c => c.value);
            
            try {
                const response = await fetch('api-bulk-action', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'delete_batch', token_ids: tokenIds })
                });

                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    showToast('Failed to delete: ' + (result.error || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Bulk delete error:', error);
                showToast('Connection error.', 'error');
            }
        }

        async function bulkDownloadZip() {
            const isAllTotal = document.getElementById('selectAllTotal')?.checked;
            const checks = document.querySelectorAll('.bulk-select:checked');
            const tokens = isAllTotal ? ALL_TOKENS : Array.from(checks).map(c => c.dataset.token);
            
            if (tokens.length === 0) {
                showToast('Please select at least one item.', 'info');
                return;
            }

            const btn = document.querySelector('button[onclick="bulkDownloadZip()"]');
            const originalText = btn ? btn.innerHTML : 'Download ZIP';
            if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Zipping...';

            try {
                const response = await fetch('api-bulk-action', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        action: 'download_zip', 
                        tokens: tokens,
                        download_mode: document.getElementById('downloadMode').value,
                        card_type: document.getElementById('bulkCardType').value
                    })
                });

                if (response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const err = await response.json();
                        alert('Error generating ZIP: ' + (err.error || 'Unknown error'));
                        return;
                    }

                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `linkydz-bundle-${Date.now()}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('Failed to generate ZIP. Server error.');
                }
            } catch (error) {
                console.error('Bulk ZIP error:', error);
                showToast('Connection error.', 'error');
            } finally {
                if (btn) btn.innerHTML = originalText;
            }
        }

        // SORTING
        function sortTable(n) {
            const table = document.querySelector(".premium-table tbody");
            let rows = Array.from(table.rows);
            let dir = table.getAttribute("data-sort-dir") || "asc";
            // Toggle direction if clicking same column
            if (table.getAttribute("data-sort-col") == n) {
                dir = dir === "asc" ? "desc" : "asc";
            }
            table.setAttribute("data-sort-dir", dir);
            table.setAttribute("data-sort-col", n);

            rows.sort((a, b) => {
                let x = a.getElementsByTagName("TD")[n].innerText.toLowerCase();
                let y = b.getElementsByTagName("TD")[n].innerText.toLowerCase();
                
                // Numeric check for metrics
                if(n === 3) { 
                    x = parseInt(x) || 0; 
                    y = parseInt(y) || 0; 
                }

                if (x < y) return dir === "asc" ? -1 : 1;
                if (x > y) return dir === "asc" ? 1 : -1;
                return 0;
            });

            rows.forEach(row => table.appendChild(row));
            
            // Update icons
            document.querySelectorAll(".sortable i").forEach(i => i.className = "fas fa-sort");
            const th = document.querySelectorAll("th")[n];
            if(th) th.querySelector("i").className = `fas fa-sort-${dir === "asc" ? "up" : "down"}`;
        }
        if(EDIT_PAGE_ID) {
            updatePreview();
        }

        // Print Modal Functions
        function showPrintModal(token, tokenId) {
            document.getElementById('printToken').value = token;
            document.getElementById('printTokenId').value = tokenId;
            document.getElementById('printTokenDisplay').textContent = token;
            document.getElementById('printModal').style.display = 'flex';

            // Update preview
            document.getElementById('cpName').textContent = token;
            document.getElementById('cpUrl').textContent = 'linkydz.com/portal';
            document.getElementById('cpLabel').textContent = 'معلمات الدخول';
            document.getElementById('cpId').textContent = 'المعرف: ' + token;
            document.getElementById('cpPass').textContent = 'كلمة المرور: ********';
            document.getElementById('cpScan').textContent = 'امسح لإدارة الرابط';
            document.getElementById('cpFooter').textContent = 'احتفظ بهذه البطاقة في مكان آمن';

            // Try loading background image
            const bg = document.getElementById('cpBg');
            const img = new Image();
            img.onload = function() {
                bg.style.background = 'url(' + img.src + ') center/cover no-repeat';
            };
            img.onerror = function() {
                bg.style.background = '#fff';
            };
            img.src = 'thankyou-bg.png?_=' + Date.now();
        }

        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }

        function toggleBulkCardType() {
            // Now we want it visible for both QR Only and Whole Card to allow bundles
            const bulkCardType = document.getElementById('bulkCardType');
            // It's always visible now for maximum flexibility
            bulkCardType.style.display = 'block';
        }

        function selectPrintMode(mode) {
            document.querySelectorAll('.print-mode-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = 'rgba(255,255,255,0.03)';
                btn.style.borderColor = 'var(--glass-border)';
            });
            
            const selectedBtn = document.querySelector(`[data-mode="${mode}"]`);
            selectedBtn.classList.add('active');
            selectedBtn.style.background = 'rgba(99, 102, 241, 0.1)';
            selectedBtn.style.borderColor = 'var(--primary)';
            
            const cardTypeGroup = document.getElementById('cardTypeGroup');
            // Only show card type for Full Card mode
            cardTypeGroup.style.display = mode === 'whole_card' ? 'block' : 'none';
        }

        async function generatePrintPDF() {
            const token = document.getElementById('printToken').value;
            const printMode = document.querySelector('.print-mode-btn.active').dataset.mode;
            const printSize = document.getElementById('printSize').value;
            const cardType = document.getElementById('printCardType').value;

            showLoading(true);
            
            try {
                const response = await fetch('api-bulk-action', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>','Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        action: 'download_pdf', 
                        tokens: [token],
                        download_mode: printMode,
                        layout: printSize === 'a4' ? 'a4_single' : 'grid',
                        card_type: cardType
                    })
                });

                if (response.ok) {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const err = await response.json();
                        showToast('Error generating PDF: ' + (err.error || 'Unknown error'), 'error');
                        return;
                    }

                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `linkydz-${token}.pdf`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                    
                    closePrintModal();
                    showAlert('PDF generated successfully! 📄');
                } else {
                    const text = await response.text();
                    console.error('PDF Error:', text);
                    showToast('Failed to generate PDF. Server error.', 'error');
                }
            } catch (error) {
                console.error('Print PDF error:', error);
                showToast('Connection error or unexpected failure.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // HELPERS
        function escHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'});
        }

        // FULL-DATASET SEARCH
        function searchTable(query) {
            const filter = query.trim();
            const tbody = document.getElementById('overviewBody');
            const pagEl = document.getElementById('overviewPagination');

            if (!filter) {
                location.reload();
                return;
            }

            if (pagEl) pagEl.style.display = 'none';

            const terms = filter.toLowerCase().split(/\s+/).filter(t => t.length > 0);
            const results = ALL_PAGES_DATA.filter(page => {
                const haystack = [page.token, page.reference, page.profile_name, page.page_created, page.token_created].filter(Boolean).join(' ').toLowerCase();
                return terms.every(term => haystack.includes(term));
            });

            if (results.length === 0) {
                tbody.innerHTML = '<tr id="searchEmptyRow"><td colspan="6" style="text-align:center;color:var(--text-dim);padding:3rem;">No matches found for "' + escHtml(filter) + '"</td></tr>';
                updateBulkBar();
                return;
            }

            tbody.innerHTML = results.map(page => renderPageRow(page)).join('');
            updateBulkBar();
        }

        // RENDER A PAGE ROW (matches PHP server-rendered row structure)
        function renderPageRow(page) {
            const isAssigned = !!page.id;
            const profileName = isAssigned ? (page.profile_name || 'Not Assigned') : 'Not Assigned';
            const createdAt = isAssigned ? page.page_created : page.token_created;
            const linkCount = isAssigned ? (parseInt(page.link_count) || 0) : 0;
            const refHtml = page.reference ? '<span class="batch-tag" style="font-size:0.65rem;color:var(--text-dim);text-transform:uppercase;font-weight:700;background:rgba(168,85,247,0.1);border-radius:4px;padding:2px 6px;width:fit-content;"><i class="fas fa-layer-group" style="font-size:0.55rem;margin-right:3px;opacity:.7;"></i> ' + escHtml(page.reference) + '</span>' : '';
            const qrPath = page.qr_code_path ? QR_RAW_URL + page.qr_code_path : '';
            const qrHtml = page.qr_code_path
                ? '<button onclick="viewQR(\'' + qrPath + '\')" class="btn-action" style="color:var(--primary);" title="View QR"><i class="fas fa-eye"></i></button>'
                + '<a href="' + qrPath + '" download="qr-' + page.token + '.png" class="btn-action" style="color:var(--success);" title="Download QR Code"><i class="fas fa-download"></i></a>'
                + '<button onclick="showPrintModal(\'' + escHtml(page.token) + '\',' + page.token_id + ')" class="btn-action" style="color:#a855f7;" title="Print Card"><i class="fas fa-print"></i></button>'
                : '';

            let actions;
            if (isAssigned) {
                actions = '<a href="?view=edit&edit_id=' + page.id + '" class="btn-action" title="Configure Design"><i class="fas fa-magic"></i></a>'
                    + '<a href="analytics?id=' + page.id + '" class="btn-action" style="color:var(--accent);" title="View Analytics"><i class="fas fa-chart-line"></i></a>'
                    + '<a href="' + API_URL + '/u/' + page.token + '" target="_blank" class="btn-action" title="View Public Page"><i class="fas fa-external-link-alt"></i></a>'
                    + '<button onclick="copyLink(\'' + API_URL + '/u/' + page.token + '\')" class="btn-action" style="color:var(--accent);" title="Copy Link"><i class="fas fa-copy"></i></button>'
                    + qrHtml
                    + '<button onclick="changeSingleBatch(' + page.token_id + ',\'' + escHtml(page.token) + '\',\'' + escHtml(page.reference || '') + '\')" class="btn-action" style="color:var(--secondary);" title="Change Batch"><i class="fas fa-layer-group"></i></button>'
                    + '<button onclick="deletePage(' + page.id + ')" class="btn-action" style="color:var(--error);" title="Permanently Delete"><i class="fas fa-trash-alt"></i></button>';
            } else {
                actions = '<button onclick="showActivationModal(' + page.token_id + ',\'' + escHtml(page.token) + '\')" class="btn-action" style="color:var(--accent);background:rgba(34,212,238,0.1);border-color:rgba(34,212,238,0.2);" title="Activate This Card"><i class="fas fa-magic"></i></button>'
                    + '<a href="' + API_URL + '/u/' + page.token + '" target="_blank" class="btn-action" title="View Public Page"><i class="fas fa-external-link-alt"></i></a>'
                    + '<button onclick="copyLink(\'' + API_URL + '/u/' + page.token + '\')" class="btn-action" style="color:var(--accent);" title="Copy Link"><i class="fas fa-copy"></i></button>'
                    + qrHtml
                    + '<button onclick="changeSingleBatch(' + page.token_id + ',\'' + escHtml(page.token) + '\',\'' + escHtml(page.reference || '') + '\')" class="btn-action" style="color:var(--secondary);" title="Assign Batch"><i class="fas fa-layer-group"></i></button>'
                    + '<button class="btn-action" style="opacity:0.2;cursor:not-allowed;"><i class="fas fa-trash-alt"></i></button>';
            }

            return '<tr class="page-row">'
                + '<td style="text-align:center;"><label class="chk-container"><input type="checkbox" class="bulk-select" value="' + page.token_id + '" data-token="' + escHtml(page.token) + '" onchange="updateBulkBar()"><span class="checkmark"></span></label></td>'
                + '<td><div style="display:flex;align-items:center;gap:1rem;"><div style="width:40px;height:40px;background:rgba(255,255,255,0.05);border:1px solid var(--glass-border);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--accent);"><i class="fas fa-qrcode"></i></div><div style="display:flex;flex-direction:column;gap:4px;"><span class="badge" style="letter-spacing:0.1em;width:fit-content;">' + escHtml(page.token) + '</span>' + refHtml + '</div></div></td>'
                + '<td><div style="font-weight:700;color:' + (isAssigned ? 'var(--text-main)' : 'var(--text-dim)') + ';font-size:1rem;">' + escHtml(profileName) + '</div><div style="font-size:0.75rem;color:var(--text-dim);margin-top:2px;">linkydz.com/u/' + page.token + '</div></td>'
                + '<td><div style="display:flex;align-items:center;gap:0.5rem;"><span style="font-weight:800;color:' + (isAssigned ? 'var(--accent)' : 'var(--text-dim)') + ';font-size:1.1rem;">' + linkCount + '</span><div style="height:4px;width:4px;background:var(--text-dim);border-radius:50%;opacity:0.3;"></div><span style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Active Links</span></div></td>'
                + '<td><div style="color:var(--text-dim);font-size:0.85rem;display:flex;align-items:center;gap:0.5rem;"><i class="far fa-calendar-alt" style="opacity:0.5;"></i> ' + formatDate(createdAt) + '</div></td>'
                + '<td style="text-align:right;"><div style="display:flex;gap:0.5rem;justify-content:flex-end;">' + actions + '</div></td>'
                + '</tr>';
        }
    </script>
    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-bottom-nav">
        <?php $currentMobFile = basename($_SERVER['SCRIPT_FILENAME']); ?>
        <a href="dashboard" class="mob-nav-item <?= ($currentMobFile == 'dashboard.php') ? 'active' : '' ?>">
            <i class="fas fa-border-all"></i>
            <span>Overview</span>
        </a>
        <a href="view-orders" class="mob-nav-item <?= ($currentMobFile == 'view-orders.php') ? 'active' : '' ?>">
            <i class="fas fa-cart-shopping"></i>
            <span>Orders</span>
        </a>
        <a href="analytics" class="mob-nav-item <?= ($currentMobFile == 'analytics.php') ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Analytics</span>
        </a>
        <a href="bulk-generate" class="mob-nav-item <?= ($currentMobFile == 'bulk-generate.php') ? 'active' : '' ?>">
            <i class="fas fa-qrcode"></i>
            <span>QR</span>
        </a>
        <a href="#" class="mob-nav-item" onclick="toggleMobileMenu(); return false;">
            <i class="fas fa-bars"></i>
            <span>More</span>
        </a>
    </nav>

    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const icon = document.querySelector('#mobileToggle i');
            const isOpen = sidebar.classList.contains('open');
            if (isOpen) {
                sidebar.classList.remove('open');
                backdrop.classList.remove('active');
                icon.classList.replace('fa-times', 'fa-bars');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('open');
                backdrop.classList.add('active');
                icon.classList.replace('fa-bars', 'fa-times');
                document.body.style.overflow = 'hidden';
            }
        }
        function closeMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const icon = document.querySelector('#mobileToggle i');
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
            if (icon) icon.classList.replace('fa-times', 'fa-bars');
            document.body.style.overflow = '';
        }

    </script>
</body>
</html>
