<?php
// Handle incorrect URL access first
$request_uri = $_SERVER['REQUEST_URI'];
if (strpos($request_uri, '/voter/voter/') !== false) {
    $correct_url = str_replace('/voter/voter/', '/voter/', $request_uri);
    header('Location: ' . $correct_url, true, 301);
    exit;
}

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

$security = new SecurityManager($pdo);
$rateLimiter = new RateLimiter($pdo);
$security->setSecurityHeaders();

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'voter') {
  header('Location: dashboard.php');
  exit;
}

// Redirect to unified login page for unauthenticated users
header('Location: ../login.php');
exit;

$error = '';
$success = '';

// Check for logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
      ], 'Voter login rate limit exceeded');
  }
  // Validate CSRF token
  elseif (!$security->validateCSRFToken($csrfToken)) {
      $error = 'Security token validation failed. Please try again.';
      $security->logSecurityEvent('csrf_validation_failed', 'high', ['username' => $username], 'CSRF token validation failed for voter login');
  }
  // Check if account is locked out
  elseif ($lockout = $security->isLockedOut($username, 'voter')) {
      $minutes = ceil($lockout['time_remaining'] / 60);
      $error = "Account temporarily locked. Too many failed login attempts. Try again in $minutes minute(s).";
  }
  elseif (empty($username) || empty($password)) {
      $error = 'Please enter both voter ID/email and password.';
      $security->recordLoginAttempt($username, 'voter', false);
  } else {
      try {
          $sql = "SELECT id, voter_id, name, email, password FROM voters WHERE voter_id = ? OR email = ? LIMIT 1";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([$username, $username]);
          $voter = $stmt->fetch(PDO::FETCH_ASSOC);
          
          if ($voter && password_verify($password, $voter['password'])) {
              // Successful login
              $security->recordLoginAttempt($username, 'voter', true);
              
              $_SESSION['user_role'] = 'voter';
              $_SESSION['role'] = 'voter'; // For compatibility
              $_SESSION['voter_pk'] = (int)$voter['id'];
              $_SESSION['voter_id'] = $voter['voter_id'];
              $_SESSION['voter_name'] = $voter['name'];
              regen_session_id();
              header('Location: dashboard.php');
              exit;
          } else {
              // Failed login
              $security->recordLoginAttempt($username, 'voter', false);
              $error = 'Invalid voter credentials.';
          }
      } catch (Exception $e) {
          $security->recordLoginAttempt($username, 'voter', false);
          $security->logSecurityEvent('login_system_error', 'high', ['error' => $e->getMessage()], 'Voter login system error');
          $error = 'Login failed. Please try again.';
      }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo h(get_system_name($pdo)); ?> System – School Online Voting</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { 
      theme: { 
        extend: { 
          colors: { 
            brand: '#0ea5e9',
            primary: '#1e40af',
            secondary: '#64748b'
          },
          animation: {
            'fade-in': 'fadeIn 0.5s ease-in-out',
            'slide-up': 'slideUp 0.3s ease-out',
            'float': 'float 3s ease-in-out infinite'
          }
        }
      }
    }
  </script>
  <style>
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes slideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-200">
  <header class="backdrop-blur bg-white/70 border-b">
    <div class="max-w-6xl mx-auto px-4 py-5 flex items-center gap-3">
      <div class="flex items-center gap-3">
        <img src="../assets/images/logo.svg" class="w-10 h-10" alt="<?php echo h(get_system_name($pdo)); ?>" onerror="this.style.display='none'" />
        <div>
          <div class="text-xl font-bold text-slate-800"><?php echo h(get_system_name($pdo)); ?> System – School Online Voting</div>
          <div class="text-xs text-slate-500">Voter Portal</div>
        </div>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 py-12">
    <div class="grid md:grid-cols-2 gap-10 items-center">
      <div class="hidden md:block">
        <div class="bg-white/70 backdrop-blur rounded-2xl shadow p-6">
          <div class="w-full max-w-md rounded-lg shadow-sm bg-gradient-to-br from-green-100 to-blue-100 flex items-center justify-center h-64">
            <div class="text-center">
              <svg class="w-24 h-24 text-green-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
              <h3 class="text-lg font-semibold text-slate-700">Voter Portal</h3>
              <p class="text-sm text-slate-500">Cast your vote securely</p>
            </div>
          </div>
          <ul class="mt-6 text-slate-600 list-disc list-inside space-y-1">
          <li>Sign in with your Voter ID or email</li>
          <li>Vote once per position during active elections</li>
          <li>View results after elections close</li>
          <li>If admin follow <a href="../admin/index.php" class="voter-link">Admin portal</a></li>
          </ul>
        </div>
      </div>
      <div class="animate-slide-up">
        <div class="bg-white/80 backdrop-blur-md rounded-3xl shadow-2xl p-10 border border-slate-100">
          <div class="text-center mb-8">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-float">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
            <h2 class="text-3xl font-bold text-slate-800 mb-2">Welcome Back</h2>
            <p class="text-slate-600">Sign in to participate in your school elections</p>
          </div>
          
          <?php if ($success): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center">
              <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
              </svg>
              <?php echo h($success); ?>
            </div>
          <?php endif; ?>
          
          <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center">
              <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
              </svg>
              <?php echo h($error); ?>
            </div>
          <?php endif; ?>
          
          <form method="post" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Voter ID or Email</label>
              <input name="username" required class="w-full border-2 border-slate-200 rounded-xl p-4 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" placeholder="Enter your voter ID or email" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Password</label>
              <input type="password" name="password" required class="w-full border-2 border-slate-200 rounded-xl p-4 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200" placeholder="Enter your password" />
            </div>
            <button class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl py-4 font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105">
              <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Sign In
              </div>
            </button>
          </form>
           
        </div>
      </div>
    </div>
  </main>
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

