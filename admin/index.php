<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

$security = new SecurityManager($pdo);
$rateLimiter = new RateLimiter($pdo);
$security->setSecurityHeaders();

$error = '';
$success = '';

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Check rate limiting first
    $rateLimit = $rateLimiter->checkLimit('login');
    if (!$rateLimit['allowed']) {
        $error = $rateLimit['reason'];
        $security->logSecurityEvent('rate_limit_exceeded', 'medium', [
            'action' => 'login',
            'username' => $username,
            'retry_after' => $rateLimit['retry_after']
        ], 'Admin login rate limit exceeded');
    }
    // Validate CSRF token
    elseif (!$security->validateCSRFToken($csrfToken)) {
        $error = 'Security token validation failed. Please try again.';
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['username' => $username], 'CSRF token validation failed for admin login');
    }
    // Check if account is locked out
    elseif ($lockout = $security->isLockedOut($username, 'admin')) {
        $minutes = ceil($lockout['time_remaining'] / 60);
        $error = "Account temporarily locked. Too many failed login attempts. Try again in $minutes minute(s).";
    }
    elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
        $security->recordLoginAttempt($username, 'admin', false);
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, fullname, role FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password'])) {
                // Successful login
                $security->recordLoginAttempt($username, 'admin', true);
                
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['fullname'] = $admin['fullname'];
                $_SESSION['user_role'] = $admin['role'] ?? 'admin';
                $_SESSION['role'] = $admin['role'] ?? 'admin'; // For compatibility
                
                regen_session_id();
                header('Location: dashboard.php');
                exit;
            } else {
                // Failed login
                $security->recordLoginAttempt($username, 'admin', false);
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $security->recordLoginAttempt($username, 'admin', false);
            $security->logSecurityEvent('login_system_error', 'high', ['error' => $e->getMessage()], 'Admin login system error');
            $error = 'Login failed. Please try again.';
        }
    }
}

// Redirect to unified login if not already authenticated as admin
if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin')) {
    header('Location: dashboard.php');
    exit;
}

header('Location: ../login.php');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(get_system_name($pdo)); ?> Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'kti-blue': '#1e40af',
                        'kti-dark': '#1e3a8a',
                        'kti-light': '#3b82f6'
                    }
                }
            }
        }
    </script>
    <style>
        .institutional-bg {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }
        .kti-card {
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .kti-input {
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        .kti-input:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
    </style>
</head>
<body class="min-h-screen institutional-bg">
    <!-- Header Section -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-kti-blue rounded-lg flex items-center justify-center mr-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900"><?php echo h(get_system_name($pdo)); ?> System</h1>
                        <p class="text-sm text-gray-600">Digital Voting Platform</p>
                    </div>
    </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex items-center justify-center min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Login Card -->
            <div class="kti-card rounded-lg p-8">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-kti-blue rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-gray-900 mb-2">Admin Portal</h2>
                </div>

                <form method="post" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username
                        </label>
                        <input type="text" id="username" name="username" required
                               class="kti-input w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none"
                               placeholder="Enter your username"
                               value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Password
                        </label>
                        <input type="password" id="password" name="password" required
                               class="kti-input w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none"
                               placeholder="Enter your password">
                    </div>

                    <!-- Success Message -->
                    <?php if ($success): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center mb-6">
                            <svg class="w-5 h-5 text-green-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-green-700 text-sm font-medium"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex items-center">
                            <svg class="w-5 h-5 text-red-400 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-red-700 text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Login Button -->
                    <button type="submit" 
                            class="w-full bg-kti-blue hover:bg-kti-dark text-white font-semibold py-3 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-kti-blue focus:ring-offset-2">
                        <div class="flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            Login
                        </div>
                    </button>
                </form>

                <!-- Security Notice -->
                <div class="mt-6 text-center">
                    <div class="flex items-center justify-center text-gray-500 text-sm">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="../voter/index.php" class="voter-link">I am a voter</a>
                    </div>
                </div>
            </div>

            
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();
        
        // Form submission handling
        const form = document.querySelector('form');
        const button = form.querySelector('button[type="submit"]');
        
        form.addEventListener('submit', function() {
            button.innerHTML = `
                <div class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Signing In...
                </div>
            `;
            button.disabled = true;
            button.classList.add('opacity-75', 'cursor-not-allowed');
        });

        // Input focus effects
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('transform', 'scale-105');
            });
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('transform', 'scale-105');
            });
        });
    </script>
    <style>
  /* Default state */
  .voter-link {
    color: black; 
    text-decoration: none;
  }

  /* When you mouse over it */
  .voter-link:hover {
    color: blue;
  }

  /* When you click it */
  .voter-link:active {
    color: red;
  }
</style>

</body>
</html>