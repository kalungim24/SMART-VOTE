<?php
/**
 * Admin Sidebar Navigation
 * Dynamically generated from sidebar configuration for easy management
 */

// Load sidebar configuration
$sidebarConfig = require __DIR__ . '/sidebar_config.php';

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Handle special cases for active state
$is_dashboard = ($current_page === 'dashboard.php' || $current_page === 'index.php');

/**
 * Check if a menu item is currently active
 */
function isActive($item, $currentPage, $isDashboard = false) {
    if (isset($item['is_dashboard']) && $item['is_dashboard']) {
        return $isDashboard;
    }
    return basename($item['url']) === $currentPage;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
}

function isMenuVisible($item, $userRole) {
    if (!isset($item['roles']) || !is_array($item['roles'])) {
        return true;
    }
    return in_array($userRole, $item['roles'], true);
}

/**
 * Generate menu item HTML
 */
function renderMenuItem($item, $currentPage, $isDashboard = false) {
    $active = isActive($item, $currentPage, $isDashboard);
    $activeClass = $active ? 'bg-slate-800 text-white' : 'hover:bg-slate-800';
    
    return sprintf(
        '<a class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors %s" href="%s">%s<span>%s</span></a>',
        $activeClass,
        htmlspecialchars($item['url']),
        $item['icon'] ?? '',
        htmlspecialchars($item['title'])
    );
}

/**
 * Generate logout button HTML
 */
function renderLogout($logoutConfig) {
    $confirmAttr = '';
    if ($logoutConfig['confirm'] ?? false) {
        $message = htmlspecialchars($logoutConfig['confirm_message'] ?? 'Are you sure you want to logout?');
        $confirmAttr = sprintf('onclick="return confirm(\'%s\')"', $message);
    }
    
    $styleClass = 'hover:bg-red-600/20 text-red-300 hover:text-red-200 border border-red-600/30 hover:border-red-500/50';
    
    return sprintf(
        '<a class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors %s" href="%s" %s>%s<span class="font-medium">%s</span></a>',
        $styleClass,
        htmlspecialchars($logoutConfig['url']),
        $confirmAttr,
        $logoutConfig['icon'] ?? '',
        htmlspecialchars($logoutConfig['title'])
    );
}
?>
<!-- Mobile Menu Overlay -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden" onclick="closeMobileMenu()"></div>

<!-- Mobile Menu Button -->
<button id="mobile-menu-button" class="fixed top-4 left-4 z-50 lg:hidden p-2 rounded-md bg-slate-900 text-slate-200 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Toggle menu">
  <svg id="menu-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
  </svg>
  <svg id="close-icon" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
  </svg>
</button>

<aside id="sidebar" class="fixed left-0 top-0 w-64 bg-slate-900 text-slate-200 h-screen overflow-y-auto z-50 transform -translate-x-full transition-transform duration-300 ease-in-out lg:translate-x-0">
  <!-- Brand Header -->
  <div class="p-4 border-b border-slate-800 flex items-center gap-2 sticky top-0 bg-slate-900 z-10">
    <div class="w-8 h-8 bg-gradient-to-br from-blue-600 to-indigo-600 rounded flex items-center justify-center">
      <?php echo $sidebarConfig['brand']['icon']; ?>
    </div>
    <?php if ($sidebarConfig['brand']['show_title'] ?? true): ?>
      <div class="font-semibold"><?php echo htmlspecialchars($sidebarConfig['brand']['title']); ?></div>
    <?php endif; ?>
  </div>
  
  <!-- Navigation -->
  <nav class="p-3 text-sm space-y-1">
    <?php $currentRole = getCurrentUserRole(); ?>
    <?php foreach ($sidebarConfig['main_menu'] as $item): ?>
      <?php if (isMenuVisible($item, $currentRole)): ?>
        <?php echo renderMenuItem($item, $current_page, $is_dashboard); ?>
      <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Divider -->
    <div class="my-4 border-t border-slate-700"></div>
    
    <?php foreach ($sidebarConfig['settings_menu'] as $item): ?>
      <?php if (isMenuVisible($item, $currentRole)): ?>
        <?php echo renderMenuItem($item, $current_page, $is_dashboard); ?>
      <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Logout -->
    <?php echo renderLogout($sidebarConfig['logout']); ?>
  </nav>
</aside>

<script>
function toggleMobileMenu() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('mobile-menu-overlay');
  const menuIcon = document.getElementById('menu-icon');
  const closeIcon = document.getElementById('close-icon');
  
  if (sidebar && overlay) {
    const isOpen = !sidebar.classList.contains('-translate-x-full');
    
    if (isOpen) {
      // Close menu
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
      menuIcon.classList.remove('hidden');
      closeIcon.classList.add('hidden');
    } else {
      // Open menu
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
      menuIcon.classList.add('hidden');
      closeIcon.classList.remove('hidden');
    }
  }
}

function closeMobileMenu() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('mobile-menu-overlay');
  const menuIcon = document.getElementById('menu-icon');
  const closeIcon = document.getElementById('close-icon');
  
  if (sidebar && overlay) {
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    menuIcon.classList.remove('hidden');
    closeIcon.classList.add('hidden');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const menuButton = document.getElementById('mobile-menu-button');
  if (menuButton) {
    menuButton.addEventListener('click', toggleMobileMenu);
  }
  
  // Close menu when clicking on a link
  const sidebarLinks = document.querySelectorAll('#sidebar a');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', function() {
      // Close menu on mobile after clicking a link
      if (window.innerWidth < 1024) {
        closeMobileMenu();
      }
    });
  });
  
  // Handle window resize
  window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
      closeMobileMenu();
    }
  });
});
</script>
