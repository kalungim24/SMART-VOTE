<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/security_manager.php';
require_once __DIR__ . '/includes/rate_limiter.php';
require_once __DIR__ . '/includes/functions.php';

$security = new SecurityManager($pdo);
$rateLimiter = new RateLimiter($pdo);
$security->setSecurityHeaders();

$error = '';
$success = '';

// If already logged in, redirect to the appropriate dashboard
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
    if ($_SESSION['user_role'] === 'voter') {
        header('Location: voter/dashboard.php');
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Rate limit
    $rateLimit = $rateLimiter->checkLimit('login');
    if (!$rateLimit['allowed']) {
        $error = $rateLimit['reason'];
        $security->logSecurityEvent('rate_limit_exceeded', 'medium', ['action' => 'login', 'username' => $username, 'retry_after' => $rateLimit['retry_after']], 'Login rate limit exceeded');
    }
    // CSRF
    elseif (!$security->validateCSRFToken($csrfToken)) {
        $error = 'Security token validation failed. Please try again.';
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['username' => $username], 'Unified login CSRF validation failed');
    }
    elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            // Try admin first (username)
            $stmt = $pdo->prepare("SELECT id, username, password, fullname, role FROM admins WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                // Admin login
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['fullname'] = $admin['fullname'];
                $_SESSION['user_role'] = $admin['role'] ?? 'admin';
                $_SESSION['role'] = $admin['role'] ?? 'admin';
                regen_session_id();
                header('Location: admin/dashboard.php');
                exit;
            }

            // Try voter (voter_id or email)
            $stmt = $pdo->prepare("SELECT id, voter_id, name, email, password FROM voters WHERE voter_id = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $voter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($voter && password_verify($password, $voter['password'])) {
                $_SESSION['user_role'] = 'voter';
                $_SESSION['role'] = 'voter';
                $_SESSION['voter_pk'] = (int)$voter['id'];
                $_SESSION['voter_id'] = $voter['voter_id'];
                $_SESSION['voter_name'] = $voter['name'];
                regen_session_id();
                header('Location: voter/dashboard.php');
                exit;
            }

            // Nothing matched
            $security->recordLoginAttempt($username, 'unified', false);
            $error = 'Invalid credentials.';
        } catch (Exception $e) {
            $security->recordLoginAttempt($username, 'unified', false);
            $security->logSecurityEvent('login_system_error', 'high', ['error' => $e->getMessage()], 'Unified login system error');
            $error = 'Login failed. Please try again.';
        }
    }
}

// Display a simple unified login form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SmartVote — Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>.kti-input{border:2px solid #e2e8f0}.kti-input:focus{border-color:#1e40af}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
    <div class="max-w-md w-full p-8 bg-white rounded-xl shadow">
        <h1 class="text-2xl font-bold mb-4">SmartVote — Sign In</h1>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Username / Voter ID / Email</label>
                <input name="username" required class="kti-input w-full p-3 rounded" placeholder="username or voter id or email" value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required class="kti-input w-full p-3 rounded" placeholder="Password">
            </div>
            <button class="w-full bg-blue-600 text-white py-3 rounded font-semibold">Sign in</button>
        </form>
    </div>
</body>
</html>
