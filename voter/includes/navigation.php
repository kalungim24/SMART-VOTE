<?php
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="bg-white/80 backdrop-blur-md border-b border-slate-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">
      <!-- Logo and Brand -->
      <div class="flex items-center space-x-3">
        <a href="dashboard.php" class="flex items-center space-x-3 hover:opacity-80 transition-opacity">
          <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <div>
            <h1 class="text-xl font-bold text-slate-800"><?php echo h(get_system_name($pdo)); ?></h1>
            <p class="text-xs text-slate-500">Digital Voting Platform</p>
          </div>
        </a>
      </div>
      
      <!-- Desktop Navigation -->
      <div class="hidden md:flex items-center space-x-4">
        <!-- Navigation Links -->
        <div class="flex items-center space-x-2">
          <a href="dashboard.php" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page === 'dashboard.php' ? 'text-blue-600 bg-blue-50' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100'; ?>">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
            </svg>
            Dashboard
          </a>
          <a href="results.php" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors <?php echo $current_page === 'results.php' ? 'text-blue-600 bg-blue-50' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100'; ?>">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Results
          </a>
        </div>
        
        <!-- User Profile -->
        <div class="hidden sm:block">
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center">
              <span class="text-white text-sm font-medium">
                <?php echo strtoupper(substr($_SESSION['voter_name'] ?? 'V', 0, 1)); ?>
              </span>
            </div>
            <div>
              <p class="text-sm font-medium text-slate-800"><?php echo h($_SESSION['voter_name'] ?? 'Voter'); ?></p>
              <p class="text-xs text-slate-500">Voter ID: <?php echo h($_SESSION['voter_id'] ?? ''); ?></p>
            </div>
          </div>
        </div>
        
        <!-- Logout Button -->
        <a href="../logout.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors" onclick="return confirm('Are you sure you want to logout?')">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
          </svg>
          Logout
        </a>
      </div>
      
      <!-- Mobile Menu Button -->
      <div class="md:hidden">
        <button type="button" class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-slate-400 hover:text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" aria-controls="mobile-menu" aria-expanded="false">
          <span class="sr-only">Open main menu</span>
          <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu hidden" id="mobile-menu">
      <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-white border-t border-slate-200">
        <a href="dashboard.php" class="block px-3 py-2 text-base font-medium rounded-md <?php echo $current_page === 'dashboard.php' ? 'text-blue-600 bg-blue-50' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100'; ?>">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
            </svg>
            Dashboard
          </div>
        </a>
        <a href="results.php" class="block px-3 py-2 text-base font-medium rounded-md <?php echo $current_page === 'results.php' ? 'text-blue-600 bg-blue-50' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-100'; ?>">
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Results
          </div>
        </a>
        
        <!-- Mobile User Profile -->
        <div class="border-t border-slate-200 pt-4 pb-3">
          <div class="flex items-center px-3">
            <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-full flex items-center justify-center">
              <span class="text-white text-sm font-medium">
                <?php echo strtoupper(substr($_SESSION['voter_name'] ?? 'V', 0, 1)); ?>
              </span>
            </div>
            <div class="ml-3">
              <div class="text-base font-medium text-slate-800"><?php echo h($_SESSION['voter_name'] ?? 'Voter'); ?></div>
              <div class="text-sm text-slate-500">Voter ID: <?php echo h($_SESSION['voter_id'] ?? ''); ?></div>
            </div>
          </div>
          <div class="mt-3 px-2 space-y-1">
            <a href="../logout.php" class="block px-3 py-2 text-base font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-md" onclick="return confirm('Are you sure you want to logout?')">
              <div class="flex items-center">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Logout
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
  const mobileMenuButton = document.querySelector('.mobile-menu-button');
  const mobileMenu = document.querySelector('.mobile-menu');
  
  if (mobileMenuButton && mobileMenu) {
    mobileMenuButton.addEventListener('click', function() {
      const isExpanded = mobileMenuButton.getAttribute('aria-expanded') === 'true';
      mobileMenuButton.setAttribute('aria-expanded', !isExpanded);
      mobileMenu.classList.toggle('hidden');
    });
  }
});
</script>
