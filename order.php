<?php
// order.php - Ultimate E-commerce Landing Page Redesign
// Matching the requested MENA e-commerce design exactly.

require_once 'config.php';
require_once 'database.php';
$db = Database::getInstance()->getConnection();

// Localization
$lang = isset($_GET['lang']) && $_GET['lang'] == 'fr' ? 'fr' : 'ar';
$is_rtl = ($lang == 'ar');

// Fetch dynamic settings
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('order_product_name_ar', 'order_product_name_fr', 'order_hero_subtitle_ar', 'order_hero_subtitle_fr', 'order_old_price', 'order_new_price', 'price_pack_all', 'price_pack_4')");
$site_settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

$product_name_ar = $site_settings['order_product_name_ar'] ?: 'بطاقة Linky الذكية';
$product_name_fr = $site_settings['order_product_name_fr'] ?: 'Smart Linky Card';
$hero_subtitle_ar = $site_settings['order_hero_subtitle_ar'] ?: 'أفضل جودة في الجزائر بمميزات متطورة';
$hero_subtitle_fr = $site_settings['order_hero_subtitle_fr'] ?: 'Meilleure qualité en Algérie avec fonctions avancées';
$old_price = $site_settings['order_old_price'] ?: '3500';
$new_price = $site_settings['order_new_price'] ?: '2000';
$price_all = $site_settings['price_pack_all'] ?: '2000';
$price_4 = $site_settings['price_pack_4'] ?: '2900';

$texts = [
    'ar' => [
        'hero_title' => $product_name_ar,
        'hero_subtitle' => $hero_subtitle_ar,
        'form_title' => 'أكد طلبك الآن',
        'form_subtitle' => 'املأ الاستمارة وسنتصل بك لتأكيد الطلب',
        'name' => 'الاسم الكامل',
        'phone' => 'رقم الهاتف',
        'wilaya' => 'الولاية',
        'baldia' => 'البلدية',
        'delivery' => 'طريقة التوصيل',
        'delivery_home' => 'توصيل للمنزل (+900 د.ج)',
        'delivery_stopdesk' => 'توصيل للمكتب (+300 د.ج)',
        'price_total' => 'المجموع الإجمالي',
        'submit' => 'إرسال الطلب الآن',
        'name_placeholder' => 'أدخل اسمك الكامل...',
        'pack' => 'اختر العرض',
        'pack_all' => 'جميع الروابط (All Links)',
        'pack_4' => '4 روابط (4 Links)',
        'price_all' => $price_all,
        'price_4' => $price_4,
        'discount_badge' => 'تخفيض خاص',
        'old_price' => $old_price,
        'new_price' => $new_price,
        'phone_placeholder' => '0XXXXXXXXX',
        'wilaya_placeholder' => 'اختر ولايتك',
        'baldia_placeholder' => 'اختر البلدية',
        'currency' => 'د.ج',
        'delivery_free_notice' => 'سعر التوصيل يضاف حسب المنطقة',
        'order_summary' => 'ملخص الطلبية',
        'product_name' => $product_name_ar,
        'announcement' => 'تمتع بأفضل تجربة شراء 🚀',
        'rating_count' => '(147 تقييم)',
        'stock' => 'متوفر في المخزن'
    ],
    'fr' => [
        'hero_title' => $product_name_fr,
        'hero_subtitle' => $hero_subtitle_fr,
        'form_title' => 'Confirmez votre commande',
        'form_subtitle' => 'Remplissez le formulaire et nous vous contacterons',
        'name' => 'Nom Complet',
        'phone' => 'Numéro de Téléphone',
        'wilaya' => 'Wilaya',
        'baldia' => 'Commune',
        'delivery' => 'Mode de Livraison',
        'delivery_home' => 'À Domicile (+900 DA)',
        'delivery_stopdesk' => 'Stopdesk (+300 DA)',
        'price_total' => 'Total Global',
        'submit' => 'Envoyer la commande',
        'name_placeholder' => 'Votre nom complet...',
        'pack' => 'Choisir le Pack',
        'pack_all' => 'Tous les liens (All Links)',
        'pack_4' => '4 Liens (4 Links)',
        'price_all' => $price_all,
        'price_4' => $price_4,
        'discount_badge' => 'Promotion',
        'old_price' => $old_price,
        'new_price' => $new_price,
        'phone_placeholder' => '0XXXXXXXXX',
        'wilaya_placeholder' => 'Choisissez votre wilaya',
        'baldia_placeholder' => 'Choisissez la commune',
        'currency' => 'DA',
        'delivery_free_notice' => 'Frais de livraison selon la zone',
        'order_summary' => 'Résumé de commande',
        'product_name' => $product_name_fr,
        'announcement' => 'Profitez de la meilleure expérience d\'achat 🚀',
        'rating_count' => '(147 avis)',
        'stock' => 'En stock'
    ]
];

$t = $texts[$lang];

// Dynamic Gallery Scanning
$gallery_dir = 'uploads/order_gallery/';
$gallery_images = glob($gallery_dir . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);
$card_designs = [];

foreach ($gallery_images as $img_path) {
    $filename = basename($img_path);
    $display_name = ucfirst(str_replace(['_', '-', '.png', '.jpg', '.jpeg', '.webp'], ' ', $filename));
    $card_designs[] = ['img' => $gallery_dir . $filename, 'name' => $display_name];
}

// Ensure we have at least one image
$firstImage = !empty($card_designs) ? $card_designs[0]['img'] : 'logolinky.png';

// Load Wilaya Data
$wilaya_json = file_get_contents('wilaya.json');
$wilaya_data = json_decode($wilaya_json, true);
$wilayas_list = isset($wilaya_data['wilayas']) ? $wilaya_data['wilayas'] : [];

// Load Delivery Prices
$delivery_prices_json = file_get_contents('delivery_prices.json');
$delivery_prices = json_decode($delivery_prices_json, true);

// Fetch All Settings for dynamic pricing
$stmtSettings = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

$fb_pixel_id = $settings['fb_pixel_id'] ?? '';
$old_price = $settings['order_old_price'] ?? '3500';
$new_price = $settings['order_new_price'] ?? '2000';
$price_all = $settings['price_pack_all'] ?? '2000';
$price_4 = $settings['price_pack_4'] ?? '2900';
$subtitle_ar = $settings['order_hero_subtitle_ar'] ?? 'أفضل جودة في الجزائر بمميزات متطورة';
$subtitle_fr = $settings['order_hero_subtitle_fr'] ?? 'Meilleure qualité en Algérie avec fonctions avancées';

// Update texts with dynamic prices
$texts['ar']['price_all'] = $price_all;
$texts['ar']['price_4'] = $price_4;
$texts['ar']['old_price'] = $old_price;
$texts['ar']['new_price'] = $new_price;
$texts['ar']['hero_subtitle'] = $subtitle_ar;

$texts['fr']['price_all'] = $price_all;
$texts['fr']['price_4'] = $price_4;
$texts['fr']['old_price'] = $old_price;
$texts['fr']['new_price'] = $new_price;
$texts['fr']['hero_subtitle'] = $subtitle_fr;

$t = $texts[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['hero_title']; ?> - Linky Dz</title>
    <link rel="icon" type="image/png" href="logolinky.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Facebook Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    <?php if ($fb_pixel_id): ?>
    fbq('init', '<?php echo htmlspecialchars($fb_pixel_id, ENT_QUOTES); ?>');
    fbq('track', 'PageView');
    fbq('track', 'ViewContent', {
        content_name: '<?php echo addslashes($is_rtl ? $product_name_ar : $product_name_fr); ?>',
        content_category: 'NFC Business Cards',
        value: <?php echo (int)$new_price; ?>,
        currency: 'DZD'
    });
    <?php endif; ?>
    </script>
    <?php if ($fb_pixel_id): ?>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?php echo htmlspecialchars($fb_pixel_id, ENT_QUOTES); ?>&ev=PageView&noscript=1"
    /></noscript>
    <?php endif; ?>
    <!-- End Facebook Pixel Code -->

    <style>
        :root {
            --primary: #103f58;
            --primary-light: #165374;
            --accent: #ffc10a;
            --accent-dark: #e5ad00;
            --red: #cd061d;
            --gray-light: #f9fafb;
            --gray-border: #dae1e7;
            --text-dark: #111827;
            --text-gray: #4b5563;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--gray-light);
            color: var(--text-dark);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Announcement Bar */
        .announcement-bar {
            background-color: var(--primary);
            color: white;
            text-align: center;
            padding: 8px 10px;
            font-size: 14px;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        /* Header */
        header {
            background: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
        }

        .header-logo img { height: 40px; }
        
        .header-actions { display: flex; gap: 15px; align-items: center; }
        .icon-btn { 
            background: none; border: none; cursor: pointer; color: var(--text-dark); 
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .cart-badge {
            position: absolute; top: -5px; right: -8px;
            background: var(--primary); color: white;
            font-size: 10px; padding: 1px 5px; border-radius: 10px;
        }

        /* Main Content Grid */
        main {
            max-width: 1200px;
            margin: 20px auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            padding: 0 20px;
        }

        /* Left Column: Product Showcase */
        .product-showcase {
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .main-image-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--gray-border);
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .main-image-container img {
            width: 100%; height: 100%; object-fit: cover;
        }

        .product-badge {
            position: absolute; top: 15px; left: 15px;
            background: var(--accent); color: var(--text-dark);
            padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 13px;
        }

        .thumbnail-gallery {
            display: flex; gap: 10px; margin-top: 15px;
            overflow-x: auto; padding-bottom: 5px;
        }
        
        .thumb {
            width: 80px; height: 80px; background: white; border-radius: 8px;
            border: 1px solid var(--gray-border); cursor: pointer; overflow: hidden;
            flex-shrink: 0; opacity: 0.7; transition: 0.2s;
        }
        .thumb.active { border-color: var(--primary); opacity: 1; border-width: 2px; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* Right Column: Details & Form */
        .product-details {
            display: flex; flex-direction: column; gap: 12px;
        }

        .category-label {
            color: var(--accent-dark); font-weight: 700; font-size: 14px;
        }

        .product-title {
            font-size: 28px; font-weight: 800; line-height: 1.2;
        }

        .rating-row {
            display: flex; align-items: center; gap: 8px; margin-bottom: 5px;
        }
        .stars { color: var(--accent); font-size: 18px; }
        .rating-text { color: var(--text-gray); font-size: 14px; }

        .price-section {
            display: flex; align-items: baseline; gap: 12px; margin: 10px 0;
        }
        .current-price { color: var(--primary); font-size: 32px; font-weight: 900; }
        .old-price { color: var(--text-gray); text-decoration: line-through; font-size: 20px; }
        .discount-label { 
            background: var(--primary); color: white; padding: 2px 10px; 
            border-radius: 20px; font-size: 14px; font-weight: 700;
        }

        /* Order Form Card */
        .order-card {
            background: white; border-radius: 16px; border: 1.5px solid var(--gray-border);
            padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-top: 10px;
        }

        .form-title { font-size: 18px; font-weight: 800; margin-bottom: 20px; }

        .form-group { margin-bottom: 18px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        input, select {
            width: 100%; padding: 14px 16px; border-radius: 10px;
            border: 1.5px solid var(--gray-border); font-family: inherit;
            font-size: 15px; outline: none; transition: 0.2s; background: var(--gray-light);
            height: 54px;
        }
        
        input:focus, select:focus { border-color: var(--primary); background: white; }

        .delivery-options {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;
        }
        .delivery-btn {
            border: 1.5px solid var(--gray-border); padding: 12px; border-radius: 10px;
            text-align: center; cursor: pointer; transition: 0.2s; background: var(--gray-light);
        }
        .delivery-btn.active { 
            border-color: var(--primary); background: rgba(16, 63, 88, 0.05);
            color: var(--primary); font-weight: 700;
        }

        .summary-box {
            background: var(--gray-light); border-radius: 10px; padding: 15px;
            margin: 20px 0; border: 1px dashed var(--gray-border);
        }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .total-row { 
            display: flex; justify-content: space-between; margin-top: 10px; padding-top: 10px;
            border-top: 1px solid var(--gray-border); font-weight: 800; font-size: 18px; color: var(--primary);
        }

        .submit-btn {
            width: 100%; padding: 16px; border-radius: 10px; border: none;
            background: var(--primary); color: white; font-size: 18px; font-weight: 800;
            cursor: pointer; transition: 0.3s; box-shadow: 0 4px 12px rgba(16, 63, 88, 0.2);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .submit-btn:hover { background: var(--primary-light); transform: translateY(-2px); }

        /* Footer */
        footer { padding: 40px 20px; text-align: center; color: var(--text-gray); border-top: 1px solid var(--gray-border); margin-top: 50px; }

        /* Responsive */
        @media (max-width: 850px) {
            main { grid-template-columns: 1fr; }
            .product-showcase { position: static; }
            .product-title { font-size: 24px; }
        }

        @media (max-width: 480px) {
            header { padding: 12px 10px; }
            main { padding: 0 10px; margin: 10px auto; width: 100%; overflow: hidden; }
            .form-grid { grid-template-columns: 1fr; }
            .order-card { padding: 20px 12px; }
            .product-title { font-size: 22px; }
            .current-price { font-size: 28px; }
            .thumb { width: 65px; height: 65px; }
        }


    </style>
</head>
<body>
    <div class="announcement-bar"><?php echo $t['announcement']; ?></div>

    <header>
        <div class="icon-btn">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
        </div>
        <div class="header-logo">
            <img src="logolinky.png" alt="Linky">
        </div>
        <div class="header-actions">
            <a href="?lang=<?php echo $lang == 'ar' ? 'fr' : 'ar'; ?>" style="text-decoration: none; font-weight: 700; color: var(--primary); font-size: 14px;">
                <?php echo $lang == 'ar' ? 'FR' : 'AR'; ?>
            </a>
            <div class="icon-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6z"/><path d="M3 6h18M16 10a4 4 0 01-8 0"/></svg>
                <span class="cart-badge">0</span>
            </div>
        </div>
    </header>

    <main>
        <!-- Left Column: Gallery -->
        <div class="product-showcase">
            <div class="main-image-container">
                <span class="product-badge">جديد</span>
                <img id="mainImage" src="<?php echo $firstImage; ?>" alt="NFC Card">
            </div>
            <div class="thumbnail-gallery">
                <?php foreach($card_designs as $index => $card): ?>
                <div class="thumb <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeImage(this, '<?php echo $card['img']; ?>')">
                    <img src="<?php echo $card['img']; ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Column: Details & Form -->
        <div class="product-details">
            <span class="category-label">Linky Business Cards</span>
            <h1 class="product-title"><?php echo $t['hero_title']; ?></h1>
            
            <div class="rating-row">
                <div class="stars">★★★★★</div>
                <span class="rating-text"><?php echo $t['rating_count']; ?></span>
                <span style="color: var(--success); font-weight: 700; font-size: 12px; margin-right: 10px; background: #e6f7f0; padding: 2px 8px; border-radius: 4px;"><?php echo $t['stock']; ?></span>
            </div>

            <div class="price-section">
                <span class="old-price"><?php echo $t['old_price']; ?> <?php echo $t['currency']; ?></span>
                <span class="current-price"><?php echo $t['new_price']; ?> <?php echo $t['currency']; ?></span>
                <span class="discount-label"><?php echo $t['discount_badge']; ?></span>
            </div>

            <p style="color: var(--text-gray); font-size: 15px; margin-bottom: 20px;">
                <?php echo $t['hero_subtitle']; ?>
            </p>

            <div class="order-card" id="form-container">
                <h2 class="form-title"><?php echo $t['form_title']; ?></h2>
                
                <form id="orderForm">
                    <div class="form-group">
                        <label><?php echo $t['pack']; ?></label>
                        <div class="delivery-options" style="grid-template-columns: 1fr;">
                            <div class="delivery-btn active" data-type="pack" data-value="All Links" data-price="<?php echo $price_all; ?>">
                                <?php echo $t['pack_all']; ?> - <?php echo $price_all; ?> <?php echo $t['currency']; ?>
                            </div>
                            <div class="delivery-btn" data-type="pack" data-value="4 Links" data-price="<?php echo $price_4; ?>" style="margin-top: 8px;">
                                <?php echo $t['pack_4']; ?> - <?php echo $price_4; ?> <?php echo $t['currency']; ?>
                            </div>
                        </div>
                        <input type="hidden" name="selected_pack" id="selected-pack-input" value="All Links">
                    </div>

                    <div class="form-group">
                        <label><?php echo $t['name']; ?></label>
                        <input type="text" name="name" placeholder="<?php echo $t['name_placeholder']; ?>" required>
                    </div>

                    <div class="form-group">
                        <label><?php echo $t['phone']; ?></label>
                        <input type="tel" name="phone" placeholder="<?php echo $t['phone_placeholder']; ?>" required>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label><?php echo $t['wilaya']; ?></label>
                            <select name="wilaya" id="wilaya-select" required>
                                <option value=""><?php echo $t['wilaya_placeholder']; ?></option>
                                <?php foreach($wilayas_list as $wilaya): ?>
                                    <option value="<?php echo $wilaya['wilaya_id']; ?>">
                                        <?php echo $wilaya['wilaya_id'] . ". " . ($lang == 'ar' ? $wilaya['wilaya_name_arabic'] : $wilaya['wilaya_name_latin']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?php echo $t['baldia']; ?></label>
                            <select name="baldia" id="baldia-select" required disabled>
                                <option value=""><?php echo $t['baldia_placeholder']; ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="delivery-section" style="display: none;">
                        <label><?php echo $t['delivery']; ?></label>
                        <div class="delivery-options">
                            <div class="delivery-btn active" data-type="delivery" data-value="Home" data-price="900">
                                <?php echo $is_rtl ? 'المنزل' : 'Domicile'; ?>
                            </div>
                            <div class="delivery-btn" data-type="delivery" data-value="Stopdesk" data-price="300">
                                <?php echo $is_rtl ? 'المكتب' : 'Stopdesk'; ?>
                            </div>
                        </div>
                        <input type="hidden" name="delivery_method" id="delivery-method-input" value="Home">
                    </div>

                    <div class="summary-box" id="summary-section" style="display: none;">
                        <div class="summary-row">
                            <span><?php echo $t['product_name']; ?></span>
                            <span id="product-price-summary"><?php echo $price_all; ?> <?php echo $t['currency']; ?></span>
                        </div>
                        <div class="summary-row">
                            <span><?php echo $t['delivery']; ?></span>
                            <span id="delivery-price-text">900 <?php echo $t['currency']; ?></span>
                        </div>
                        <div class="total-row">
                            <span><?php echo $t['price_total']; ?></span>
                            <span id="total-price-text"><?php echo (int)$price_all + 900; ?> <?php echo $t['currency']; ?></span>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                        <?php echo $t['submit']; ?>
                    </button>
                </form>
            </div>
        </div>
    </main>


    <footer>
        <p>&copy; <?php echo date('Y'); ?> Linky Dz. All rights reserved.</p>
    </footer>

    <script>
        const wilayaData = <?php echo $wilaya_json; ?>;
        const deliveryPrices = <?php echo $delivery_prices_json; ?>;
        const currentLang = "<?php echo $lang; ?>";

        function changeImage(el, src) {
            document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('mainImage').src = src;
        }

        // Delivery Selection
        const deliveryBtns = document.querySelectorAll('.delivery-btn');
        const deliveryInput = document.getElementById('delivery-method-input');
        const selectedPackInput = document.getElementById('selected-pack-input');
        const deliveryText = document.getElementById('delivery-price-text');
        const productPriceSummary = document.getElementById('product-price-summary');
        const totalText = document.getElementById('total-price-text');

        function updateTotals() {
            const activePack = document.querySelector('.delivery-btn.active[data-type="pack"]');
            const activeDelivery = document.querySelector('.delivery-btn.active[data-type="delivery"]');
            const selectedWilaya = wilayaSelect.value;
            
            const packPrice = parseInt(activePack.getAttribute('data-price'));
            let deliveryPrice = parseInt(activeDelivery.getAttribute('data-price'));
            
            // Override with wilaya-specific price if available
            if (selectedWilaya && deliveryPrices[selectedWilaya]) {
                const method = activeDelivery.getAttribute('data-value').toLowerCase(); // 'home' or 'stopdesk'
                deliveryPrice = deliveryPrices[selectedWilaya][method] || deliveryPrice;
            }
            
            productPriceSummary.innerText = packPrice + " " + (currentLang === 'ar' ? "د.ج" : "DA");
            deliveryText.innerText = deliveryPrice + " " + (currentLang === 'ar' ? "د.ج" : "DA");
            totalText.innerText = (packPrice + deliveryPrice) + " " + (currentLang === 'ar' ? "د.ج" : "DA");
        }

        deliveryBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.getAttribute('data-type');
                document.querySelectorAll(`.delivery-btn[data-type="${type}"]`).forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                if (type === 'pack') {
                    selectedPackInput.value = btn.getAttribute('data-value');
                } else {
                    deliveryInput.value = btn.getAttribute('data-value');
                }
                
                updateTotals();
            });
        });

        // Wilaya/Baldia logic
        const wilayaSelect = document.getElementById('wilaya-select');
        const baldiaSelect = document.getElementById('baldia-select');

        wilayaSelect.addEventListener('change', function() {
            const wilayaId = parseInt(this.value);
            baldiaSelect.innerHTML = '<option value=""><?php echo $t['baldia_placeholder']; ?></option>';
            if (wilayaId && wilayaData.communes) {
                const filtered = wilayaData.communes.filter(c => c.wilaya_id === wilayaId);
                filtered.sort((a,b) => (currentLang === 'ar' ? a.commune_name_arabic : a.commune_name_latin).localeCompare(currentLang === 'ar' ? b.commune_name_arabic : b.commune_name_latin));
                filtered.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = opt.textContent = (currentLang === 'ar' ? c.commune_name_arabic : c.commune_name_latin);
                    baldiaSelect.appendChild(opt);
                });
                baldiaSelect.disabled = false;
                
                // Show delivery and summary sections
                document.getElementById('delivery-section').style.display = 'block';
                document.getElementById('summary-section').style.display = 'block';
            } else {
                baldiaSelect.disabled = true;
                // Hide delivery and summary sections
                document.getElementById('delivery-section').style.display = 'none';
                document.getElementById('summary-section').style.display = 'none';
            }
            updateTotals(); // Trigger recalculation when wilaya changes
        });

        // Form Submission
        // Fire InitiateCheckout once the user starts interacting with the form
        let checkoutTracked = false;
        document.getElementById('orderForm').addEventListener('input', function() {
            if (!checkoutTracked && typeof fbq !== 'undefined') {
                checkoutTracked = true;
                fbq('track', 'InitiateCheckout', {
                    content_name: '<?php echo addslashes($is_rtl ? $product_name_ar : $product_name_fr); ?>',
                    currency: 'DZD'
                });
            }
        }, { once: false });

        document.getElementById('orderForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<?php echo $is_rtl ? "جاري الإرسال..." : "Envoi en cours..."; ?>';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            data.lang = currentLang;

            try {
                const response = await fetch('api-order', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo Security::getCSRFToken(); ?>'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('form-container').innerHTML = `
                        <div style="text-align:center; padding: 40px 10px;">
                            <div style="width: 80px; height: 80px; background: #e6f7f0; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                            </div>
                            <h2 style="margin-bottom: 15px;"><?php echo $is_rtl ? 'تم استلام طلبك بنجاح!' : 'Commande réussie !'; ?></h2>
                            <p style="color: var(--text-gray);"><?php echo $is_rtl ? 'سنتصل بك في أقرب وقت ممكن لتأكيد الطلب وشحنه إليك.' : 'Nous vous contacterons bientôt pour confirmer.'; ?></p>
                            <button onclick="location.reload()" class="submit-btn" style="margin-top:25px;"><?php echo $is_rtl ? 'رجوع' : 'Retour'; ?></button>
                        </div>`;
                    if (typeof fbq !== 'undefined') {
                        const activePack = document.querySelector('.delivery-btn.active[data-type="pack"]');
                        const orderValue = activePack ? parseInt(activePack.getAttribute('data-price')) : 0;
                        fbq('track', 'Purchase', {
                            value: orderValue,
                            currency: 'DZD',
                            content_name: '<?php echo addslashes($is_rtl ? $product_name_ar : $product_name_fr); ?>',
                            content_type: 'product'
                        });
                    }
                } else {
                    alert(result.error || 'Err');
                    btn.disabled = false;
                    btn.innerHTML = '<?php echo $t['submit']; ?>';
                }
            } catch (err) {
                console.error(err);
                alert('Connection error');
                btn.disabled = false;
                btn.innerHTML = '<?php echo $t['submit']; ?>';
            }
        });
    </script>
</body>
</html>
