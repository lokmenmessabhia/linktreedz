<?php
session_start();
require_once 'config.php';
require_once 'database.php';

if (isset($_SESSION['portal_token_id'])) {
    header('Location: portal');
    exit;
}

$db = Database::getInstance()->getConnection();

// Fetch Admin Contact Phone
$stmtPhone = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_contact_phone'");
$admin_phone = $stmtPhone->fetchColumn() ?: '0550000000';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['token']) && isset($_GET['pass']))) {
    // Check Rate Limit (5 attempts / 15 mins)
    if (!RateLimiter::checkLimit('login', 5, 900)) {
        $error = "Too many failed attempts. Please try again in 15 minutes.";
    } else {
        $token = trim($_POST['token'] ?? $_GET['token'] ?? '');
        $password = trim($_POST['password'] ?? $_GET['pass'] ?? '');
        
        if ($token && $password) {
            $stmt = $db->prepare("SELECT id, password_hash, is_active FROM tokens WHERE token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['is_active']) {
                if ($user['password_hash'] && password_verify($password, $user['password_hash'])) {
                    // Success! Clear attempts
                    RateLimiter::clearAttempts('login');
                    
                    $_SESSION['portal_token_id'] = $user['id'];
                    $_SESSION['portal_token'] = $token;
                    header('Location: portal');
                    exit;
                } else {
                    RateLimiter::recordAttempt('login');
                    $error = "Invalid token or password.";
                }
            } else {
                RateLimiter::recordAttempt('login');
                $error = "Token not found or deactivated.";
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Portal | <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #00853f;
            --primary-glow: rgba(0, 133, 63, 0.1);
            --secondary: #d21034;
            --background: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-dim: #64748b;
            --radius: 2.5rem;
            --shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--background);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            overflow-y: auto;
            position: relative;
            padding: 2rem 1rem;
        }

        .liquid-bg {
            position: fixed; inset: 0; z-index: -1;
            background: 
                radial-gradient(circle at 10% 10%, var(--primary-glow) 0%, transparent 40%),
                radial-gradient(circle at 90% 90%, rgba(210, 16, 52, 0.05) 0%, transparent 40%),
                var(--background);
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 3.5rem;
            box-shadow: var(--shadow);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @media (max-width: 520px) {
            body { padding: 1.25rem 1rem; align-items: flex-start; }
            .login-card { padding: 2rem 1.5rem; border-radius: 1.75rem; }
            .header { margin-bottom: 1.75rem; }
            .header h1 { font-size: 1.9rem; }
            .input-wrapper { margin-bottom: 1rem; }
            .btn-premium { margin-top: 0.75rem; }
            .forgot-link { margin-top: 1.25rem; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .header { text-align: center; margin-bottom: 3rem; }
        .header h1 { 
            font-family: 'Outfit';
            font-size: 2.5rem; 
            margin-bottom: 0.75rem; 
            letter-spacing: -0.05em; 
            color: var(--text-main);
        }
        .header p { color: var(--text-dim); font-size: 0.95rem; font-weight: 500; line-height: 1.6; }
        
        .form-label {
            display: block; font-size: 0.75rem; font-weight: 700;
            color: var(--text-dim); margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em;
            padding-left: 0.5rem;
        }

        .input-wrapper { position: relative; margin-bottom: 1.5rem; }
        .input-wrapper i {
            position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%);
            color: var(--text-dim); font-size: 1.1rem; transition: color 0.3s;
        }

        .input-glass {
            width: 100%;
            padding: 1.125rem 1.25rem 1.125rem 2.25rem;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--glass-border);
            border-radius: 1.25rem;
            color: var(--text-main);
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-glass:focus { 
            outline: none; 
            border-color: var(--primary); 
            background: #fff;
            box-shadow: 0 0 0 4px var(--primary-glow);
        }
        .input-glass:focus + i { color: var(--primary); }
        
        .btn-premium {
            width: 100%;
            padding: 1.125rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 1.25rem;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1.5rem;
            box-shadow: 0 10px 20px var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px var(--primary-glow);
            filter: brightness(1.1);
        }

        .error-alert {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 1.125rem;
            border-radius: 1.25rem;
            margin-bottom: 2rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 600;
        }

        .forgot-link {
            display: inline-block;
            margin-top: 2rem;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: color 0.3s;
            cursor: pointer;
        }
        .forgot-link:hover { color: var(--primary); }

        #contact-admin {
            display: none;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: white;
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-dim);
            line-height: 1.6;
            animation: slideUp 0.4s ease-out;
            box-shadow: var(--shadow);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .contact-number {
            display: block;
            color: var(--text-main);
            font-weight: 800;
            font-size: 1.25rem;
            margin-top: 0.5rem;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="liquid-bg"></div>

    <div class="login-card">
        <div class="header">
            <h1>Client Portal</h1>
            <p>Access your digital business card settings. No card yet? <a href="order" style="color: var(--primary); font-weight: 700; text-decoration: none;">Get yours today</a>.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-wrapper">
                <label class="form-label">Token ID</label>
                <i class="fas fa-id-badge"></i>
                <input type="text" name="token" class="input-glass" placeholder="Found on your card" autocomplete="off" required>
            </div>
            
            <div class="input-wrapper">
                <label class="form-label">Password</label>
                <i class="fas fa-lock"></i>
                <input type="password" name="password" class="input-glass" placeholder="Enter your password" required>
            </div>

            <input type="hidden" name="csrf_token" value="<?php echo Security::getCSRFToken(); ?>">
            <button type="submit" class="btn-premium">Secure Access <i class="fas fa-arrow-right"></i></button>
        </form>

        <div style="text-align: center;">
            <span class="forgot-link" onclick="toggleContact()">Forgot Password?</span>
            
            <div id="contact-admin">
                <i class="fas fa-headset" style="font-size: 1.5rem; margin-bottom: 0.75rem; display: block; color: var(--primary);"></i>
                Please contact our support team to reset your password:
                <span class="contact-number"><?php echo htmlspecialchars($admin_phone); ?></span>
            </div>
        </div>
    </div>

    <script>
        function toggleContact() {
            const el = document.getElementById('contact-admin');
            el.style.display = el.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</body>
</html>
