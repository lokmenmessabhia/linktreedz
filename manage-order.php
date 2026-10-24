<?php
/**
 * manage-order.php - Administrative Control for Order Landing Page
 * Controls prices and product showcase gallery.
 */

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$auth = new Auth();
$auth->requireAuth();
$user = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Fetch current settings
$stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'order_%' OR setting_key LIKE 'price_%' OR setting_key = 'index_hero_image' OR setting_key = 'admin_contact_phone'");
$settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$prices = [
    'old_price' => $settings_raw['order_old_price'] ?? '3500',
    'new_price' => $settings_raw['order_new_price'] ?? '2000',
    'price_all' => $settings_raw['price_pack_all'] ?? '2000',
    'price_4' => $settings_raw['price_pack_4'] ?? '2900',
    'subtitle_ar' => $settings_raw['order_hero_subtitle_ar'] ?? '',
    'subtitle_fr' => $settings_raw['order_hero_subtitle_fr'] ?? '',
    'product_name_ar' => $settings_raw['order_product_name_ar'] ?? '',
    'product_name_fr' => $settings_raw['order_product_name_fr'] ?? '',
    'admin_phone' => $settings_raw['admin_contact_phone'] ?? '',
    'index_hero' => $settings_raw['index_hero_image'] ?? 'premium_nfc_card_mockup_1768326596149.png'
];

// Fetch current gallery images
$gallery_dir = 'uploads/order_gallery/';
if (!is_dir($gallery_dir)) mkdir($gallery_dir, 0755, true);
$gallery_images = glob($gallery_dir . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Order Assets | Linky Dz</title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Outfit:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-style.css?v=<?php echo time(); ?>">
    <style>
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .gallery-item {
            position: relative;
            aspect-ratio: 1/1;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid var(--glass-border);
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.05);
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-item:hover {
            transform: translateY(-5px);
            border-color: var(--accent);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .delete-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(239, 68, 68, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
            cursor: pointer;
        }
        .gallery-item:hover .delete-overlay { opacity: 1; }
        .delete-overlay i { color: white; font-size: 1.5rem; }
        
        .hero-badge-active {
            position: absolute;
            top: 10px; left: 10px;
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            z-index: 5;
            box-shadow: 0 4px 10px rgba(0, 133, 63, 0.4);
            display: flex; align-items: center; gap: 5px;
        }

        .set-hero-btn {
            position: absolute;
            bottom: 10px; right: 10px;
            width: 38px; height: 38px;
            background: #fff;
            color: var(--text-main);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            z-index: 10;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateY(10px);
        }
        .gallery-item:hover .set-hero-btn { opacity: 1; transform: translateY(0); }
        .set-hero-btn:hover { background: var(--primary); color: #fff; transform: scale(1.1); }
        .set-hero-btn.is-hero { background: var(--primary); color: #fff; opacity: 1; transform: none; }
        
        .upload-zone {
            border: 2px dashed var(--glass-border);
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(255,255,255,0.02);
        }
        .upload-zone:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--primary);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main class="main">
        <?php include 'topbar.php'; ?>
        
        <div class="container-fluid" style="padding: 1.5rem; max-width: 1200px;">
            <div class="header" style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end;">
                <div>
                    <a href="dashboard" class="btn-back" style="margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 2.2rem; margin: 0;">Order Landing Settings</h1>
                    <p style="color: var(--text-dim); margin-top: 0.5rem;">Manage prices and the showcase gallery for your public order page.</p>
                </div>
                <div style="display: flex; gap: 0.8rem;">
                    <a href="order" target="_blank" class="btn-glass">
                        <i class="fa-solid fa-external-link"></i> View Page
                    </a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem;">
                <!-- Left: Pricing Controls -->
                <div class="price-settings">
                    <div class="glass-card" style="padding: 2rem;">
                        <h3 style="font-family: 'Outfit'; font-weight: 800; margin-bottom: 1.8rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fa-solid fa-tags" style="color: var(--primary);"></i> Pricing Control
                        </h3>
                        
                        <form id="priceForm">
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem; color: var(--text-dim);">Main "Old Price" (Crossed out)</label>
                                <div style="position: relative;">
                                    <input type="number" name="old_price" class="input-glass" value="<?php echo htmlspecialchars($prices['old_price']); ?>" style="padding-right: 3.5rem;">
                                    <span style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-dim);">DA</span>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem; color: var(--text-dim);">Main "New Price" (Highlighted)</label>
                                <div style="position: relative;">
                                    <input type="number" name="new_price" class="input-glass" value="<?php echo htmlspecialchars($prices['new_price']); ?>" style="padding-right: 3.5rem; border-color: var(--primary);">
                                    <span style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--primary);">DA</span>
                                </div>
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--glass-border); margin: 2rem 0;">

                            <h4 style="font-size: 0.9rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 1rem; font-weight: 800; letter-spacing: 0.05em;">Landing Page Content</h4>

                            <div class="form-group" style="margin-bottom: 1.2rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem;">Hero Subtitle (Arabic)</label>
                                <input type="text" name="subtitle_ar" class="input-glass" value="<?php echo htmlspecialchars($prices['subtitle_ar']); ?>" placeholder="أفضل جودة في الجزائر...">
                            </div>

                            <div class="form-group" style="margin-bottom: 1.2rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem;">Hero Subtitle (French)</label>
                                <input type="text" name="subtitle_fr" class="input-glass" value="<?php echo htmlspecialchars($prices['subtitle_fr']); ?>" placeholder="Meilleure qualité en Algérie...">
                            </div>

                            <div class="form-group" style="margin-bottom: 1.2rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem;">Product Name (Arabic)</label>
                                <input type="text" name="product_name_ar" class="input-glass" value="<?php echo htmlspecialchars($prices['product_name_ar']); ?>" placeholder="بطاقة Linky الذكية">
                            </div>

                            <div class="form-group" style="margin-bottom: 1.2rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem;">Product Name (French)</label>
                                <input type="text" name="product_name_fr" class="input-glass" value="<?php echo htmlspecialchars($prices['product_name_fr']); ?>" placeholder="Linky Card">
                            </div>

                            <hr style="border: none; border-top: 1px solid var(--glass-border); margin: 2rem 0;">

                            <h4 style="font-size: 0.9rem; text-transform: uppercase; color: var(--text-dim); margin-bottom: 1rem; font-weight: 800; letter-spacing: 0.05em;">Contact Settings</h4>

                            <div class="form-group" style="margin-bottom: 2rem;">
                                <label style="display: block; font-weight: 700; margin-bottom: 0.6rem; font-size: 0.9rem;">Admin Contact Phone (Forgot Password)</label>
                                <div style="position: relative;">
                                    <input type="text" name="admin_phone" class="input-glass" value="<?php echo htmlspecialchars($prices['admin_phone']); ?>" placeholder="0550000000" style="padding-left: 3rem;">
                                    <i class="fa-solid fa-phone" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-dim);"></i>
                                </div>
                            </div>

                            <button type="submit" class="btn-premium" style="width: 100%;">
                                <i class="fa-solid fa-save"></i> Update Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right: Gallery Management -->
                <div class="gallery-management">
                    <div class="glass-card" style="padding: 2rem;">
                        <h3 style="font-family: 'Outfit'; font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fa-solid fa-images" style="color: var(--primary);"></i> Product Gallery
                        </h3>
                        <p style="color: var(--text-dim); font-size: 0.9rem; margin-bottom: 2rem;">These images appear in the main slider on the ordering page.</p>

                        <div class="upload-zone" onclick="document.getElementById('galleryInput').click()">
                            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                            <h4 style="margin-bottom: 0.3rem;">Upload New Image</h4>
                            <p style="font-size: 0.8rem; color: var(--text-dim);">PNG, JPG or WEBP (Max 5MB)</p>
                            <input type="file" id="galleryInput" hidden accept="image/*" onchange="uploadImage(this)">
                        </div>

                        <div class="gallery-grid" id="galleryGrid">
                            <?php foreach ($gallery_images as $img): $filename = basename($img); ?>
                                <div class="gallery-item" data-filename="<?php echo htmlspecialchars($filename); ?>">
                                    <?php if ($filename == $prices['index_hero']): ?>
                                        <div class="hero-badge-active"><i class="fa-solid fa-star"></i> Active Hero</div>
                                    <?php endif; ?>
                                    <img src="<?php echo $img; ?>" alt="Product">
                                    <div class="delete-overlay" onclick="deleteImage('<?php echo htmlspecialchars($filename); ?>')">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </div>
                                    <div class="set-hero-btn <?php echo ($filename == $prices['index_hero'] ? 'is-hero' : ''); ?>" 
                                         onclick="setHeroImage('<?php echo htmlspecialchars($filename); ?>')"
                                         title="Set as Landing Page Hero">
                                        <i class="fa-solid fa-rocket"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Price Updates
        document.getElementById('priceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.action = 'update_prices';

            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            try {
                const response = await fetch('api-order-settings.php', {
                    method: 'POST',
                    body: JSON.stringify(data),
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Prices updated successfully!', 'success');
                } else {
                    showToast(result.error || 'Update failed', 'error');
                }
            } catch (err) {
                showToast('Connection error', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-save"></i> Update Prices';
            }
        });

        // Gallery Upload
        async function uploadImage(input) {
            if (!input.files || !input.files[0]) return;
            
            const formData = new FormData();
            formData.append('image', input.files[0]);
            formData.append('action', 'upload_gallery');

            showToast('Uploading image...', 'info');

            try {
                const response = await fetch('api-order-settings.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Image uploaded!', 'success');
                    location.reload(); // Refresh to show new image
                } else {
                    showToast(result.error || 'Upload failed', 'error');
                }
            } catch (err) {
                showToast('Connection error', 'error');
            }
        }

        // Gallery Delete
        async function deleteImage(filename) {
            if (!confirm('Are you sure you want to delete this image?')) return;

            try {
                const response = await fetch('api-order-settings.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'delete_gallery', filename: filename }),
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    const item = document.querySelector(`.gallery-item[data-filename="${filename}"]`);
                    item.style.opacity = '0';
                    setTimeout(() => item.remove(), 300);
                    showToast('Image deleted', 'info');
                } else {
                    showToast(result.error || 'Delete failed', 'error');
                }
            } catch (err) {
                showToast('Connection error', 'error');
            }
        }

        // Set Landing Hero
        async function setHeroImage(filename) {
            try {
                const response = await fetch('api-order-settings.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'set_hero_image', filename: filename }),
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    }
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Landing Page Hero Updated!', 'success');
                    location.reload(); 
                } else {
                    showToast(result.error || 'Update failed', 'error');
                }
            } catch (err) {
                showToast('Connection error', 'error');
            }
        }

        function showToast(msg, type = 'info') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type} visible`;
            toast.style.margin = '10px 0';
            toast.innerHTML = `
                <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                <span>${msg}</span>
            `;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }
    </script>
</body>
</html>
