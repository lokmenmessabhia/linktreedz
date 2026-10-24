<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$auth = new Auth();
$db = Database::getInstance()->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    if (RECOVERY_KEY === '' || RECOVERY_PASS === '') {
        $error = 'Set RECOVERY_KEY and RECOVERY_PASS in .env before using this page.';
    } elseif (!hash_equals(RECOVERY_KEY, (string)($_POST['recovery_key'] ?? ''))) {
        $error = 'Invalid recovery key.';
    } else {
    try {
        $username = RECOVERY_USER;
        $email = RECOVERY_EMAIL;
        $password = RECOVERY_PASS;
        $hash = $auth->hashPassword($password);
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $db->prepare("UPDATE admins SET username = ?, email = ?, password_hash = ? WHERE id = ?");
            $success_exec = $stmt->execute([$username, $email, $hash, $exists['id']]);
            $message = "Credentials updated successfully for <strong>" . htmlspecialchars($username) . "</strong>! <br><br> <a href='login' class='btn' style='text-decoration:none;'>Log In Now</a>";
        } else {
            $stmt = $db->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
            $success_exec = $stmt->execute([$username, $email, $hash]);
            $message = "New admin account <strong>" . htmlspecialchars($username) . "</strong> created successfully! <br><br> <a href='login' class='btn' style='text-decoration:none;'>Log In Now</a>";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Auth Recovery - Linky Dz</title>
    <link rel="icon" type="image/png" href="logolinky.png">
    <style>
        body { font-family: sans-serif; background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .card { background: #1e293b; padding: 2rem; border-radius: 1rem; text-align: center; border: 1px solid #334155; max-width: 400px; }
        .btn { background: #6366f1; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: bold; }
        .success { color: #10b981; margin: 1rem 0; }
        .error { color: #ef4444; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Custom Admin Setup</h2>
        <p>This will set the admin to <strong><?php echo htmlspecialchars(RECOVERY_USER); ?></strong> using the credentials configured in <code>.env</code>.</p>

        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
            <p style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 1.5rem;">User: <?php echo htmlspecialchars(RECOVERY_USER); ?></p>
            <a href="login" class="btn" style="text-decoration: none; display: inline-block;">Go to Login</a>
        <?php elseif ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <form method="POST">
                <label for="recovery_key" style="display:block; margin-bottom:.5rem; font-size:.9rem;">Recovery key</label>
                <input type="password" id="recovery_key" name="recovery_key" required style="width:100%; padding:.6rem; margin-bottom:1rem; border-radius:.5rem; border:1px solid #334155; background:#0f172a; color:#fff;">
                <button type="submit" name="confirm_reset" class="btn">Update Admin Credentials</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
