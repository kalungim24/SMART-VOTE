<?php
// Start a secure session and set basic controls
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    // Get the base path for the application (works with subdirectories)
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $cookiePath = ($scriptPath === '/' || $scriptPath === '\\') ? '/' : $scriptPath . '/';
    
    // Initialize session with proper cookie parameters
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 8, // 8 hours
        'path' => '/', // Use root path to ensure cookies work across subdirectories
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('smartvote_sess');
    session_start();
}

// Initialize last_activity if not set
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Check session timeout BEFORE destroying (to prevent premature logout)
$inactivityTimeout = 60 * 60 * 8; // 8 hours of inactivity
$timeSinceLastActivity = time() - (int)$_SESSION['last_activity'];

// Only destroy session if user has been inactive for the full timeout period
// AND the user is not currently logged in and actively using the system
if ($timeSinceLastActivity > $inactivityTimeout) {
    // Check if user is logged in
    $isLoggedIn = isset($_SESSION['user_role']) && 
                  (isset($_SESSION['user_id']) || isset($_SESSION['admin_id']) || isset($_SESSION['voter_id']));
    
    // Only destroy if truly inactive (not just refreshing the page)
    if (!$isLoggedIn || $timeSinceLastActivity > ($inactivityTimeout * 2)) {
        // User has been inactive for double the timeout (16 hours) - destroy session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    } else {
        // User is logged in but approaching timeout - refresh their activity
        $_SESSION['last_activity'] = time();
    }
} else {
    // User is active - update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Ensure logged-in users maintain their session
if (isset($_SESSION['user_role'])) {
    // Extend session for active logged-in users
    $_SESSION['last_activity'] = time();
}

function regen_session_id(): void {
    if (!isset($_SESSION['regen_at'])) {
        $_SESSION['regen_at'] = time();
    }
    // Only regenerate session ID every 2 hours to avoid issues
    if (time() - (int)$_SESSION['regen_at'] > 60 * 60 * 2) { // every 2 hours
        session_regenerate_id(false); // Don't delete old session immediately
        $_SESSION['regen_at'] = time();
    }
}

function require_role(string $role): void {
    // Ensure session is active
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    // Check both 'user_role' and 'role' session variables for compatibility
    $userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
    
    // Verify user has valid session data (user_id or admin_id or voter_id)
    $hasValidSession = isset($userRole) && 
                      (isset($_SESSION['user_id']) || 
                       isset($_SESSION['admin_id']) || 
                       isset($_SESSION['voter_id']) ||
                       isset($_SESSION['voter_pk']));
    
    $allowedRoles = [$role];
    if ($role === 'admin') {
        $allowedRoles = ['admin', 'super_admin'];
    }
    
    if (!$hasValidSession || !in_array($userRole, $allowedRoles, true)) {
        // Clear any problematic session data that might cause loops
        if (isset($_SESSION['admin_logged_in'])) {
            unset($_SESSION['admin_logged_in']);
        }
        
        // Redirect to the appropriate login page for requested role
        if ($role === 'admin') {
            // Get the current script path
            $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            
            // Prevent redirect loops - don't redirect if already on login page
            if (basename($currentScript) === 'index.php' && strpos($requestUri, '/admin/') !== false) {
                // Already on admin login page, don't redirect
                return;
            }
            
            // Determine correct redirect path
            if (strpos($currentScript, '/admin/') !== false) {
                // We're in admin directory, redirect relatively
                header('Location: index.php');
            } else {
                // We're outside admin directory, redirect absolutely
                // Use relative path for better portability
                $basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
                if ($basePath === '/' || $basePath === '\\') {
                    header('Location: /admin/index.php');
                } else {
                    header('Location: ' . $basePath . '/admin/index.php');
                }
            }
        } else {
            // Determine correct redirect path for voter
            $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
            if (strpos($currentScript, '/voter/') !== false) {
                header('Location: index.php');
            } else {
                $basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
                if ($basePath === '/' || $basePath === '\\') {
                    header('Location: /voter/index.php');
                } else {
                    header('Location: ' . $basePath . '/voter/index.php');
                }
            }
        }
        exit;
    }
    
    // Ensure session data persists by writing it
    if (isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }
}

function require_super_admin(): void {
    // If a regular admin is logged in, send them back to the admin area
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
    $hasValidSession = isset($userRole) && 
                      (isset($_SESSION['user_id']) || 
                       isset($_SESSION['admin_id']) || 
                       isset($_SESSION['voter_id']) ||
                       isset($_SESSION['voter_pk']));

    if ($hasValidSession && $userRole === 'admin') {
        // Regular admin tried to access a super-admin page — redirect to admin index
        // Use relative redirect so it works both from /admin/* and other locations
        $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($currentScript, '/admin/') !== false) {
            header('Location: index.php');
        } else {
            $basePath = dirname(dirname($_SERVER['SCRIPT_NAME']));
            if ($basePath === '/' || $basePath === '\\') {
                header('Location: /admin/index.php');
            } else {
                header('Location: ' . $basePath . '/admin/index.php');
            }
        }
        exit;
    }

    // For all other cases, fall back to role enforcement (this will redirect to login if unauthenticated)
    require_role('super_admin');
}

