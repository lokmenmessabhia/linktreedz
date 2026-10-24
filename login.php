<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';
require_once 'security.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isAuthenticated()) {
    header('Location: dashboard');
    exit;
}

$error = '';
$success = '';
$timeout = isset($_GET['timeout']) ? true : false;
$setupMode = !$auth->hasAdmins();

// Handle setup form (if no admins exist)
if ($setupMode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCSRF();
    $username = $_POST['reg_username'] ?? '';
    $email = $_POST['reg_email'] ?? '';
    $password = $_POST['reg_password'] ?? '';
    
    if (!empty($username) && !empty($email) && !empty($password)) {
        if ($auth->createAdmin($username, $email, $password)) {
            $success = 'Administrative account created! You can now sign in.';
            $setupMode = false;
        } else {
            $error = 'Initialization failed. Please check your database connection.';
        }
    } else {
        $error = 'Please complete all fields to establish administrative access.';
    }
}
// Handle standard login
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::verifyCSRF();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Both credentials are required to proceed.';
    } else {
        $loginResult = $auth->login($username, $password);
        
        if ($loginResult === true) {
            header('Location: dashboard');
            exit;
        } elseif ($loginResult === 'blocked') {
            $error = 'Too many failed login attempts. Please try again in 15 minutes.';
        } else {
            $error = 'Authentication failed. Please check your username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #00853f;
            --primary-glow: rgba(0, 133, 63, 0.15);
            --secondary: #d21034;
            --background: #f8fafc;
            --card-bg: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(0, 0, 0, 0.08);
            --text-main: #0f172a;
            --text-dim: #64748b;
            --radius: 2.5rem;
            --shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1);
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
            overflow: hidden;
            position: relative;
        }

        /* Ambient Background blobs */
        .ambient-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
        }
        .blob {
            position: absolute;
            width: 800px; height: 800px;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(80px);
            animation: move 20s infinite alternate;
        }
        .blob-1 { top: -400px; left: -200px; }
        .blob-2 { bottom: -400px; right: -200px; animation-delay: -5s; }

        @keyframes move {
            0% { transform: translate(0, 0); }
            100% { transform: translate(100px, 100px); }
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 3.5rem;
            box-shadow: var(--shadow);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo-area { text-align: center; margin-bottom: 3rem; }
        .logo-area img { height: 60px; margin-bottom: 1.5rem; }
        .logo-area h1 { 
            font-family: 'Outfit'; 
            font-size: 2.2rem; 
            font-weight: 800; 
            letter-spacing: -0.05em; 
            color: var(--text-main);
        }
        .logo-area p { color: var(--text-dim); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500; }

        .form-label {
            display: block; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.1em;
            color: var(--text-dim); margin-bottom: 0.75rem; padding-left: 0.5rem;
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
            border: none;
            border-radius: 1.25rem;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 20px var(--primary-glow);
            margin-top: 1.5rem;
            display: flex; align-items: center; justify-content: center; gap: 0.75rem;
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px var(--primary-glow);
            filter: brightness(1.1);
        }

        .alert {
            padding: 1.125rem;
            border-radius: 1.25rem;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            display: flex; align-items: center; gap: 1rem;
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
            color: #ef4444;
            font-weight: 600;
        }

        .alert-info { background: var(--primary-glow); border-color: rgba(0, 133, 63, 0.1); color: var(--primary); }

        .links-area { margin-top: 2.5rem; text-align: center; font-size: 0.9rem; }
        .links-area a { color: var(--text-dim); text-decoration: none; transition: color 0.3s; font-weight: 600; }
        .links-area a:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="ambient-bg">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="login-card">
        <div class="logo-area">
            <img src="logolinky.png" alt="Linky Dz">
            <h1>Linky Dz</h1>
            <p><?php echo $setupMode ? 'Establish Administrative Access' : 'Administrative Secure Login'; ?></p>
        </div>

        <?php if ($timeout && !$setupMode): ?>
            <div class="alert alert-info">
                <i class="fas fa-clock"></i> Session expired. Please login again.
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert" style="background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.1); color: #10b981;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($setupMode): ?>
            <form method="POST" action="">
                <div class="input-wrapper">
                    <label class="form-label">Admin Username</label>
                    <input type="text" name="reg_username" class="input-glass" placeholder="e.g. admin" required autofocus>
                    <i class="fas fa-user-shield"></i>
                </div>

                <div class="input-wrapper">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="reg_email" class="input-glass" placeholder="admin@example.com" required>
                    <i class="fas fa-envelope"></i>
                </div>

                <div class="input-wrapper">
                    <label class="form-label">Strong Password</label>
                    <input type="password" name="reg_password" class="input-glass" placeholder="••••••••" required>
                    <i class="fas fa-key"></i>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo Security::getCSRFToken(); ?>">
                <button type="submit" class="btn-premium">Initialize Setup <i class="fas fa-magic"></i></button>
            </form>
        <?php else: ?>
            <form method="POST" action="">
                <div class="input-wrapper">
                    <label class="form-label">Username / Email</label>
                    <input type="text" name="username" class="input-glass" placeholder="admin" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <i class="fas fa-user"></i>
                </div>

                <div class="input-wrapper">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="input-glass" placeholder="••••••••" required>
                    <i class="fas fa-lock"></i>
                </div>

                <input type="hidden" name="csrf_token" value="<?php echo Security::getCSRFToken(); ?>">
                <button type="submit" class="btn-premium">Sign In <i class="fas fa-arrow-right"></i></button>
            </form>
        <?php endif; ?>

        <div class="links-area">
            <a href="index">← Back to Public Portal</a>
        </div>
    </div>
</body>
</html>
