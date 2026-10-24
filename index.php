<?php
require_once 'config.php';
require_once 'database.php';

// Fetch Facebook Pixel ID
$dbPixel = Database::getInstance()->getConnection();
$stmtPixel = $dbPixel->query("SELECT setting_value FROM settings WHERE setting_key = 'fb_pixel_id'");
$fb_pixel_id = $stmtPixel->fetchColumn() ?: '';

// Fetch dynamic product names and subtitles
$stmtSettings = $dbPixel->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('order_product_name_ar', 'order_product_name_fr', 'order_hero_subtitle_ar', 'order_hero_subtitle_fr', 'index_hero_image')");
$site_settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

$index_hero = $site_settings['index_hero_image'] ?: 'premium_nfc_card_mockup_1768326596149.png';

$product_name_ar = $site_settings['order_product_name_ar'] ?? 'بطاقة Linky الذكية';
$product_name_fr = $site_settings['order_product_name_fr'] ?? 'Linky Card';
$hero_subtitle_ar = $site_settings['order_hero_subtitle_ar'] ?? 'أفضل جودة في الجزائر بمميزات متطورة';
$hero_subtitle_fr = $site_settings['order_hero_subtitle_fr'] ?? 'Meilleure qualité en Algérie avec fonctions avancées';

// Simple localization helper
$lang = isset($_GET['lang']) && $_GET['lang'] == 'fr' ? 'fr' : 'ar';
$is_rtl = ($lang == 'ar');

$texts = [
    'ar' => [
        'title' => 'Linky Dz - هويتك الرقمية بلمسة جزائرية',
        'meta' => 'أفضل منصة للهوية الرقمية في الجزائر. اجمع عالمك في رابط واحد.',
        'hero_badge' => 'كل ما تملك، في رابط واحد',
        'hero_title' => 'هويتك الرقمية،<br><span class="gradient-text">بلمسة عالمية</span>',
        'hero_tagline' => 'انضم لآلاف الجزائريين الذين يستخدمون ' . $product_name_ar . ' لمشاركة أعمالهم، حساباتهم ومنتجاتهم. رابط واحد يجمع كل شيء.',
        'features_title' => 'مميزات قوية،<br>سهلة الاستخدام',
        'features_subtitle' => $hero_subtitle_ar,
        'feat1_title' => 'رموز QR ذكية',
        'feat1_desc' => 'أنشئ رموز QR جذابة تربط حضورك الواقعي بالرقمي بكل سلاسة.',
        'feat2_title' => 'تصميم مخصص',
        'feat2_desc' => 'اجعل صفحتك تعبر عنك مع خيارات تخصيص غير محدودة تناسب علامتك التجارية.',
        'feat3_title' => 'إحصائيات متقدمة',
        'feat3_desc' => 'تتبع كل نقرة، افهم جمهورك، وطور استراتيجية المحتوى الخاصة بك.',
        'showcase_title' => 'تصاميم بدون<br><span class="gradient-text">حدود</span>',
        'showcase_subtitle' => 'اختر من بين مجموعتنا من القوالب الفاخرة. من المجرات الكونية إلى المستقبل المضيء، عبر عن نفسك الحقيقية.',
        'template1' => 'المجرة (Galaxy)',
        'template1_desc' => 'جماليات أعماق الفضاء مع حقول النجوم الديناميكية.',
        'template2' => 'الأورورا (Aurora)',
        'template2_desc' => 'تدرجات لونية سائلة مستوحاة من أضواء القطب الشمالي.',
        'template3' => 'النيون (Neon)',
        'template3_desc' => 'تباين عالٍ، وتألق السايبربانك المتميز.',
        'physical_badge' => 'امسح للاتصال',
        'physical_title' => 'الجسر نحو<br><span class="gradient-text">الواقع</span>',
        'physical_tagline' => 'انقل هويتك الرقمية إلى العالم الحقيقي. بطاقاتنا الفاخرة المزودة بـ QR تسمح لأي شخص بزيارة ملفك بمجرد المسح.',
        'li1' => 'جودة طباعة ممتازة مع لمسة احترافية',
        'li2' => 'مادة PVC متينة وطويلة الأمد',
        'li3' => 'رمز QR بدقة عالية وسهل المسح',
        'order_btn' => 'اطلب بطاقتك الآن',
        'cta_h2' => 'هل أنت جاهز للبدء؟',
        'cta_p' => 'أنشئ صفحة الروابط الخاصة بك في ثوانٍ. لا تتطلب مهارات تقنية.',
        'buy_btn' => 'اطلب ' . $product_name_ar . ' الآن',
        'footer_made' => 'صنع بواسطة',
        'footer_in' => 'في الجزائر'
    ],
    'fr' => [
        'title' => 'Linky Dz - Votre identité numérique à la touche algérienne',
        'meta' => 'La plateforme d\'identité numérique premium pour l\'Algérie. Connectez votre monde avec un seul lien.',
        'hero_badge' => 'Tout ce que vous êtes, en un seul lien',
        'hero_title' => 'Votre identité,<br><span class="gradient-text">magnifiquement unifiée</span>',
        'hero_tagline' => 'Rejoignez des milliers d\'Algériens utilisant ' . $product_name_fr . ' pour partager tout ce qu\'ils créent et vendent. Un lien pour tout diriger.',
        'features_title' => 'Fonctions puissantes,<br>simples à utiliser',
        'features_subtitle' => $hero_subtitle_fr,
        'feat1_title' => 'QR Codes Intelligents',
        'feat1_desc' => 'Générez de magnifiques QR codes qui connectent votre présence physique et numérique.',
        'feat2_title' => 'Marquage Personnalisé',
        'feat2_desc' => 'Personnalisez votre page avec des options illimitées. Adaptez-la à votre marque.',
        'feat3_title' => 'Analyses Avancées',
        'feat3_desc' => 'Suivez chaque clic, comprenez votre audience et optimisez votre stratégie.',
        'showcase_title' => 'Design sans<br><span class="gradient-text">limites</span>',
        'showcase_subtitle' => 'Choisissez parmi notre collection de templates premium. Exprimez votre vraie personnalité.',
        'template1' => 'La Galaxie',
        'template1_desc' => 'Esthétique spatiale profonde avec champs d\'étoiles dynamiques.',
        'template2' => 'L\'Aurora',
        'template2_desc' => 'Gradients fluides inspirés de l\'Arctique.',
        'template3' => 'Le Néon',
        'template3_desc' => 'Haut contraste, excellence cyberpunk brillante.',
        'physical_badge' => 'Scannez pour connecter',
        'physical_title' => 'Le pont vers<br><span class="gradient-text">la réalité</span>',
        'physical_tagline' => 'Amenez votre identité numérique dans le monde réel. Nos cartes premium permettent de scanner votre profil instantanément.',
        'li1' => 'Finition mate de haute qualité',
        'li2' => 'Matériau PVC durable',
        'li3' => 'QR Code UV-imprimé scannable',
        'order_btn' => 'Commandez votre carte',
        'cta_h2' => 'Prêt à commencer ?',
        'cta_p' => 'Créez votre page de liens en quelques secondes. Aucune compétence technique requise.',
        'buy_btn' => 'Acheter ' . $product_name_fr,
        'footer_made' => 'Fait par',
        'footer_in' => 'en Algérie'
    ]
];

$t = $texts[$lang];

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Facebook Pixel -->
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
    fbq('init', '<?php echo $fb_pixel_id; ?>'); 
    fbq('track', 'PageView');
    <?php endif; ?>
    </script>
    <title><?php echo $t['title']; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <meta name="description" content="<?php echo $t['meta']; ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Outfit:wght@300;400;600;800&family=Inter:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #00853f;
            --primary-light: #00a851;
            --primary-glow: rgba(0, 133, 63, 0.1);
            --secondary: #d21034;
            --background: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(0, 0, 0, 0.06);
            --text-main: #0f172a;
            --text-dim: #64748b;
            --radius-lg: 3rem;
            --radius-md: 2rem;
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 20px 60px rgba(0, 0, 0, 0.06);
            --shadow-glow: 0 0 20px var(--primary-glow);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; outline: none; }
        
        html { overflow-x: hidden; }

        body {
            font-family: 'Inter', 'Cairo', sans-serif;
            background: var(--background);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* Animated Tech Background */
        .tech-bg {
            position: fixed; inset: 0; z-index: 0;
            overflow: hidden; pointer-events: none;
        }
        .tech-circle {
            position: absolute;
            border: 1px solid rgba(0, 133, 63, 0.05);
            border-radius: 50%;
            animation: breathe 10s infinite ease-in-out;
        }
        .circle-1 { width: 60vw; height: 60vw; top: -10vw; left: -10vw; }
        .circle-2 { width: 40vw; height: 40vw; bottom: -5vw; right: -5vw; animation-delay: -5s; }

        .particle {
            position: absolute;
            background: var(--primary);
            border-radius: 50%;
            pointer-events: none;
            opacity: 0.25;
            filter: blur(2px);
            animation: float-particle 20s infinite linear;
            z-index: 1;
        }

        @keyframes float-particle {
            0% { transform: translateY(0) rotate(0deg); opacity: 0; }
            10% { opacity: 0.2; }
            90% { opacity: 0.2; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }

        @keyframes breathe {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.05); opacity: 0.6; }
        }

        /* Futuristic Reveal Animation - Managed by AOS now */
        .reveal { transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1); }

        /* Navigation Header */
        header {
            padding: 1.25rem 0;
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            background: rgba(255, 255, 255, 0.7);
            border-bottom: 1px solid var(--glass-border);
            position: sticky; top: 0; z-index: 1000;
            transition: all 0.3s;
        }

        .container { max-width: 1280px; margin: 0 auto; padding: 0 2.5rem; width: 100%; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }

        .logo { height: 50px; transition: 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .logo:hover { transform: scale(1.1) rotate(-3deg); }

        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        
        .btn-pill {
            text-decoration: none; padding: 0.7rem 1.4rem; border-radius: 100px;
            font-weight: 700; font-size: 0.8rem; border: 1px solid var(--glass-border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex; align-items: center; gap: 0.6rem;
            font-family: 'Outfit'; text-transform: uppercase; letter-spacing: 0.05em;
        }
        .btn-pill-light { background: #fff; color: var(--text-main); }
        .btn-pill-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-pill:hover { transform: translateY(-3px); box-shadow: var(--shadow-sm); }

        /* Hero */
        .hero { padding: 10rem 0; text-align: center; position: relative; }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 0.75rem;
            padding: 0.75rem 1.5rem; background: #fff; border: 1px solid var(--glass-border);
            border-radius: 100px; font-size: 0.75rem; font-weight: 800; color: var(--primary);
            margin-bottom: 2.5rem; box-shadow: var(--shadow-sm);
            font-family: 'Fira Code', monospace;
            max-width: 100%;
        }

        .hero h1 {
            font-family: 'Outfit', sans-serif;
            font-size: clamp(2rem, 9vw, 6rem);
            font-weight: 800; line-height: 1.1; margin-bottom: 2rem;
            letter-spacing: -0.04em; color: var(--text-main);
            word-break: break-word;
        }

        .gradient-text {
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shine 4s linear infinite;
            display: inline-block;
        }

        @keyframes shine {
            to { background-position: 200% center; }
        }

        .hero-tagline {
            font-size: clamp(0.95rem, 3vw, 1.35rem); color: var(--text-dim);
            max-width: 750px; margin: 0 auto 3.5rem;
            font-weight: 500;
        }

        .btn-futuristic {
            padding: 1.1rem 2.5rem; border-radius: 100px;
            background: var(--primary); color: #fff; font-weight: 800;
            font-size: clamp(0.9rem, 3vw, 1.15rem); cursor: pointer; transition: all 0.5s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 0.75rem;
            box-shadow: 0 20px 40px var(--primary-glow);
            position: relative; overflow: hidden;
            max-width: 100%;
        }
        .btn-futuristic::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: rotate(45deg); transition: 0.8s;
        }
        .btn-futuristic:hover::after { left: 150%; }
        .btn-futuristic:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 25px 50px var(--primary-glow);
        }
        
        /* Magnetic Button Helper */
        .magnetic-wrap { display: inline-block; transition: transform 0.3s cubic-bezier(0.23, 1, 0.32, 1); }

        /* Bento Grid */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: auto auto;
            gap: 1.5rem;
            margin-top: 4rem;
        }

        .bento-card {
            background: var(--card-bg);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 3rem 2.5rem 2.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex; flex-direction: column; justify-content: flex-start;
            text-align: center;
            min-height: 320px;
        }
        .bento-card:hover { transform: translateY(-8px); border-color: rgba(0, 133, 63, 0.2); box-shadow: var(--shadow-md); }
        
        .bento-card.wide { grid-column: span 2; }
        .bento-card.tall { grid-row: span 1; }

        .card-glow {
            position: absolute; inset: 0;
            background: radial-gradient(circle at var(--x) var(--y), rgba(0, 133, 63, 0.1), transparent 70%);
            opacity: 0; transition: opacity 0.3s;
        }
        .bento-card:hover .card-glow { opacity: 1; }

        .bento-card .icon-zone { 
            width: 70px; height: 70px; border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: #fff;
            margin: 0 auto 2rem;
        }

        .icon-p { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .icon-s { background: linear-gradient(135deg, var(--secondary), #ef4444); }
        .icon-a { background: linear-gradient(135deg, #6366f1, #4f46e5); }

        .bento-card h3 { font-family: 'Outfit'; font-size: 1.4rem; font-weight: 700; margin-bottom: 0.75rem; }
        .bento-card p { color: var(--text-dim); font-size: 0.95rem; }

        /* 3D Visualizer */
        .visualizer {
            padding: 10rem 0;
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 6rem; align-items: center;
            width: 100%;
        }

        .tilt-container {
            perspective: 2000px;
            position: relative;
        }
        .tilt-card {
            width: 100%; border-radius: 3rem;
            box-shadow: 0 50px 100px -20px rgba(0,0,0,0.15);
            transition: transform 0.1s ease-out;
            transform-style: preserve-3d;
            background: #fff;
            padding: 5px;
        }
        .tilt-card img { width: 100%; border-radius: 2.8rem; display: block; }

        .visualizer h2 { font-family: 'Outfit'; font-size: clamp(2.5rem, 6vw, 4rem); font-weight: 800; line-height: 1; margin-bottom: 2.5rem; letter-spacing: -0.04em; }

        .futuristic-list { list-style: none; margin-bottom: 3.5rem; }
        .futuristic-list li {
            display: flex; align-items: center; gap: 1.25rem;
            margin-bottom: 1.5rem; font-weight: 600; font-size: 1.1rem;
        }
        .futuristic-list i { 
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--primary-glow); color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
        }

        /* Footer */
        footer { padding: 6rem 0 3rem; text-align: center; }
        .footer-social { display: flex; justify-content: center; gap: 2rem; margin-top: 3rem; }
        .social-circle {
            width: 54px; height: 54px; border-radius: 50%;
            background: #fff; border: 1px solid var(--glass-border);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-dim); font-size: 1.4rem; transition: 0.4s;
            text-decoration: none;
        }
        .social-circle:hover { background: var(--primary); color: #fff; transform: translateY(-5px) rotate(8deg); border-color: var(--primary); }

        @media (max-width: 968px) {
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
            .visualizer { grid-template-columns: 1fr; gap: 3rem; text-align: center; padding: 5rem 0; }
            .visualizer-image { order: 2; }
            .futuristic-list li { justify-content: center; }
        }
        @media (max-width: 640px) {
            .container { padding: 0 1.25rem; }
            .hero { padding: 5rem 0 3rem; }
            .bento-grid { grid-template-columns: 1fr; gap: 1rem; margin-top: 2.5rem; }
            .bento-card.wide { grid-column: span 1; }
            .bento-card { padding: 2rem 1.5rem; min-height: 220px; }
            .visualizer { padding: 3rem 0; gap: 2rem; }
            .hero-tagline { font-size: 0.95rem; margin-bottom: 2rem; }
            .btn-futuristic { padding: 0.9rem 1.75rem; font-size: 0.9rem; }
            .nav-actions { gap: 0.5rem; }
            .btn-pill { padding: 0.5rem 0.85rem; font-size: 0.72rem; }
        }
        @media (max-width: 480px) {
            .bento-grid { grid-template-columns: 1fr; }
            .bento-card.wide { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="tech-bg">
        <div class="tech-circle circle-1"></div>
        <div class="tech-circle circle-2"></div>
    </div>

    <header>
        <div class="container header-content">
            <a href="index?lang=<?php echo $lang; ?>">
                <img src="logolinky.png" alt="Linky" class="logo">
            </a>
            <div class="nav-actions">
                <a href="portal-login" class="btn-pill btn-pill-light">
                    <i class="fas fa-atom"></i> <?php echo $is_rtl ? 'البوابة' : 'Portal'; ?>
                </a>
                <a href="?lang=<?php echo $lang == 'ar' ? 'fr' : 'ar'; ?>" class="btn-pill btn-pill-light">
                    <?php echo $lang == 'ar' ? 'FR' : 'AR'; ?>
                </a>
                <a href="order?lang=<?php echo $lang; ?>" class="btn-pill btn-pill-primary">
                    <?php echo $is_rtl ? 'اطلب الآن' : 'Order Now'; ?>
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="hero reveal">
            <div class="hero-badge" data-aos="fade-down">
                <i class="fas fa-terminal"></i>
                <?php echo $t['hero_badge']; ?>
            </div>
            <h1 data-aos="zoom-out-up" data-aos-delay="100"><?php echo $t['hero_title']; ?></h1>
            <p class="hero-tagline" data-aos="fade-up" data-aos-delay="200"><?php echo $t['hero_tagline']; ?></p>
            
            <div class="magnetic-wrap" data-aos="fade-up" data-aos-delay="300">
                <a href="order?lang=<?php echo $lang; ?>" class="btn-futuristic"><?php echo $t['buy_btn']; ?> <i class="fas fa-rocket"></i></a>
            </div>

            <div class="bento-grid">
                <div class="bento-card wide reveal" data-aos="fade-right" data-aos-delay="400">
                    <div class="card-glow"></div>
                    <div class="icon-zone icon-p"><i class="fas fa-qrcode"></i></div>
                    <h3><?php echo $t['feat1_title']; ?></h3>
                    <p><?php echo $t['feat1_desc']; ?></p>
                </div>
                <div class="bento-card reveal" data-aos="fade-up" data-aos-delay="500">
                    <div class="card-glow"></div>
                    <div class="icon-zone icon-s"><i class="fas fa-palette"></i></div>
                    <h3><?php echo $t['feat2_title']; ?></h3>
                    <p><?php echo $t['feat2_desc']; ?></p>
                </div>
                <div class="bento-card reveal" data-aos="fade-left" data-aos-delay="600">
                    <div class="card-glow"></div>
                    <div class="icon-zone icon-a"><i class="fas fa-chart-line"></i></div>
                    <h3><?php echo $t['feat3_title']; ?></h3>
                    <p><?php echo $t['feat3_desc']; ?></p>
                </div>
            </div>
        </section>

        <section class="visualizer reveal" style="padding-top: 5rem;">
            <div class="visualizer-image reveal" data-aos="flip-right">
                <div class="tilt-container">
                    <div class="tilt-card" id="nfcTilt">
                        <img src="uploads/order_gallery/<?php echo $index_hero; ?>" alt="Linky Card">
                    </div>
                </div>
            </div>
            <div class="visualizer-content reveal" data-aos="fade-<?php echo $is_rtl ? 'right' : 'left'; ?>">
                <div class="hero-badge" style="background: rgba(0, 133, 63, 0.05); text-transform: uppercase;">
                    <i class="fas fa-microchip"></i> Hardware v2.0
                </div>
                <h2><?php echo $t['physical_title']; ?></h2>
                <p class="hero-tagline" style="margin: 0 0 2.5rem 0; text-align: inherit;"><?php echo $t['physical_tagline']; ?></p>
                <ul class="futuristic-list">
                    <li><i class="fas fa-check"></i> <?php echo $t['li1']; ?></li>
                    <li><i class="fas fa-check"></i> <?php echo $t['li2']; ?></li>
                    <li><i class="fas fa-check"></i> <?php echo $t['li3']; ?></li>
                </ul>
                <a href="order?lang=<?php echo $lang; ?>" class="btn-futuristic"><?php echo $t['order_btn']; ?></a>
            </div>
        </section>
    </main>

    <footer>
        <div class="container reveal">
            <img src="logolinky.png" alt="Linky" style="height: 35px; opacity: 0.6; margin-bottom: 2rem;">
            <p class="copyright">
                &copy; <?php echo date('Y'); ?> • <?php echo $t['footer_made']; ?> 
                <a href="https://www.instagram.com/lokpix.studio/">LOKPIX STUDIO</a> 
                <?php echo $t['footer_in']; ?>
            </p>
            <div class="footer-social">
                <a href="https://www.instagram.com/linky.dz/" class="social-circle"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-circle"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-circle"><i class="fab fa-tiktok"></i></a>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // --- Futuristic Interactions ---

        // 0. Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            easing: 'ease-out-cubic'
        });

        // 1. Mouse Tracking Glow Effect for Bento Cards
        document.querySelectorAll('.bento-card').forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.querySelector('.card-glow').style.setProperty('--x', `${x}px`);
                card.querySelector('.card-glow').style.setProperty('--y', `${y}px`);
            });
        });

        // 2. 3D Tilt Effect for NFC Card
        const tiltCard = document.getElementById('nfcTilt');
        if (tiltCard) {
            window.addEventListener('mousemove', e => {
                if (window.innerWidth < 968) return; 
                const rect = tiltCard.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const mouseX = e.clientX - centerX;
                const mouseY = e.clientY - centerY;
                
                const rotateX = (mouseY / (window.innerHeight / 2)) * -15;
                const rotateY = (mouseX / (window.innerWidth / 2)) * 15;
                
                tiltCard.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });
            window.addEventListener('mouseout', () => {
                tiltCard.style.transform = 'rotateX(0deg) rotateY(0deg)';
            });
        }

        // 3. Magnetic Buttons
        document.querySelectorAll('.magnetic-wrap').forEach(wrap => {
            const btn = wrap.querySelector('a');
            wrap.addEventListener('mousemove', e => {
                const rect = wrap.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                wrap.style.transform = `translate(${x * 0.3}px, ${y * 0.3}px)`;
            });
            wrap.addEventListener('mouseleave', () => {
                wrap.style.transform = `translate(0px, 0px)`;
            });
        });

        // 4. Particle System
        function createParticles() {
            const container = document.querySelector('.tech-bg');
            for (let i = 0; i < 15; i++) {
                const p = document.createElement('div');
                p.className = 'particle';
                const size = Math.random() * 8 + 4;
                p.style.width = `${size}px`;
                p.style.height = `${size}px`;
                p.style.left = `${Math.random() * 100}vw`;
                p.style.top = `${Math.random() * 100}vh`;
                p.style.animationDelay = `${Math.random() * 20}s`;
                p.style.animationDuration = `${Math.random() * 10 + 10}s`;
                container.appendChild(p);
            }
        }
        createParticles();
    </script>
</body>
</html>
