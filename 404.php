<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        :root {
            --primary: #3B82F6;
            --bg-start: #0F172A;
            --bg-end: #020617;
            --text-primary: #FFFFFF;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.3);
            --blur-amount: 40px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        /* Liquid Background */
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
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s infinite alternate ease-in-out;
        }
        
        .blob-1 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            top: -10%;
            left: -10%;
        }
        
        .blob-2 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, #8B5CF6 0%, transparent 70%);
            bottom: -10%;
            right: -10%;
            animation-delay: -5s;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(20px, 20px) scale(1.1); }
        }
        
        .container {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 2rem;
        }
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(var(--blur-amount));
            -webkit-backdrop-filter: blur(var(--blur-amount));
            border: 1px solid var(--glass-border);
            border-radius: 2rem;
            padding: 4rem 3rem;
            box-shadow: var(--shadow-lg);
            max-width: 480px;
            width: 100%;
            animation: fadeInUp 0.8s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        h1 {
            font-size: 6rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.5) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
            padding: 1rem 2rem;
            border-radius: 1rem;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="liquid-bg">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="container">
        <div class="glass-card">
            <h1>404</h1>
            <h2>Page Not Found</h2>
            <p>The link you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
            <a href="<?php echo BASE_URL; ?>" class="btn">Return Home</a>
        </div>
    </div>
</body>
</html>
