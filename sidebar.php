<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="user-avatar" style="width: 44px; height: 44px; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fff; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);">
            <img src="logolinky.png" alt="Linky Dz Logo" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <span style="font-size: 1.6rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.02em;">Linky Dz</span>
    </div>
    
    <nav class="nav-menu">
        <?php $currentSidebarFile = basename($_SERVER["SCRIPT_FILENAME"]); ?>
        <a href="dashboard" class="nav-link <?= ($currentSidebarFile == 'dashboard.php' || $currentSidebarFile == 'dashboard') ? 'active' : '' ?>">
            <i class="fa-solid fa-border-all"></i> Overview
        </a>
        <a href="view-orders" class="nav-link <?= ($currentSidebarFile == 'view-orders.php' || $currentSidebarFile == 'view-orders') ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-shopping"></i> Orders
        </a>
        <a href="manage-delivery" class="nav-link <?= ($currentSidebarFile == 'manage-delivery.php' || $currentSidebarFile == 'manage-delivery') ? 'active' : '' ?>">
            <i class="fa-solid fa-truck-fast"></i> Manage Delivery
        </a>
        <a href="manage-order" class="nav-link <?= ($currentSidebarFile == 'manage-order.php' || $currentSidebarFile == 'manage-order') ? 'active' : '' ?>">
            <i class="fa-solid fa-cart-shopping"></i> Order Settings
        </a>
        <a href="bulk-generate" class="nav-link <?= ($currentSidebarFile == 'bulk-generate.php' || $currentSidebarFile == 'bulk-generate') ? 'active' : '' ?>">
            <i class="fa-solid fa-qrcode"></i> Generate QR
        </a>
        <a href="analytics" class="nav-link <?= ($currentSidebarFile == 'analytics.php' || $currentSidebarFile == 'analytics') ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i> Analytics
        </a>
        
        <div style="margin-top: 2rem; padding: 0 1.5rem;">
            <p style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-dim); margin-bottom: 0.75rem; font-weight: 800; opacity: 0.6;">Account</p>
            <a href="logout" class="nav-link" style="margin: 0; padding: 0.75rem 0.5rem; color: var(--secondary);">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </div>

        <div style="margin-top: 1.5rem; padding: 0 1.5rem;">
            <p style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-dim); margin-bottom: 0.75rem; font-weight: 800; opacity: 0.6;">Quick Actions</p>
            <a href="/" target="_blank" class="nav-link" style="margin: 0; padding: 0.75rem 0.5rem;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View Landing
            </a>
            <a href="export-db.php" class="nav-link" style="margin: 0.5rem 0 0; padding: 0.75rem 0.5rem; color: #22c55e;">
                <i class="fa-solid fa-database"></i> Backup Database
            </a>
        </div>

        <!-- Pixel ID Form -->
        <div style="margin: 2rem 1.5rem 0; padding: 1.25rem; background: #f8fafc; border-radius: 1.25rem; border: 1px solid var(--glass-border);">
            <?php
            $db_p = Database::getInstance()->getConnection();
            $px_id = '';
            try {
                $st = $db_p->query("SELECT setting_value FROM settings WHERE setting_key = 'fb_pixel_id'");
                $px_id = $st->fetchColumn() ?: '';
            } catch(Exception $e) {}
            ?>
            <form method="POST" action="dashboard">
                <input type="hidden" name="action" value="update_pixel">
                <input type="hidden" name="csrf_token" value="<?php echo Security::getCSRFToken(); ?>">
                <label style="font-size: 0.6rem; text-transform: uppercase; color: var(--text-dim); font-weight: 800; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                    <i class="fa-brands fa-facebook" style="color: #1877F2; font-size: 0.9rem;"></i> Meta Pixel ID
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" name="fb_pixel_id" value="<?php echo htmlspecialchars($px_id); ?>" placeholder="ID..." style="flex: 1; background: #fff; border: 1px solid var(--glass-border); border-radius: 8px; padding: 0.5rem; color: var(--text-main); font-size: 0.8rem; font-weight: 600; outline: none; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <button type="submit" style="background: var(--primary); border: none; border-radius: 8px; color: #fff; padding: 0 0.75rem; cursor: pointer; transition: 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </form>
        </div>
    </nav>
</aside>

<!-- Global Toast System -->
<div id="toast-container" style="position: fixed; bottom: 2rem; right: 2rem; z-index: 10001; display: flex; flex-direction: column; gap: 0.75rem; pointer-events: none;"></div>

<script>
function showToast(msg, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-\${type} visible`;
    toast.style.pointerEvents = 'auto'; // Re-enable for the toast itself
    
    // Choose icon
    let icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-triangle-exclamation';
    if (type === 'info') icon = 'fa-info-circle';
    
    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <div class="toast-content">
            <div class="toast-message">${msg}</div>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Auto-remove
    setTimeout(() => {
        toast.classList.remove('visible');
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}
</script>
