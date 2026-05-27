<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('voter');

$activeElection = current_active_election($pdo);
$voterHasVoted = false;
if (isset($_SESSION['voter_id']) && $activeElection) {
  $voterHasVoted = has_voted_in_active($pdo, $_SESSION['voter_id']);
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
            'pulse-slow': 'pulse 3s infinite'
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
      from { transform: translateY(10px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
  <!-- Skip to content link for accessibility -->
  <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-blue-600 text-white px-4 py-2 rounded-lg z-50">Skip to main content</a>
  
  <!-- Navigation Header -->
  <?php include __DIR__ . '/includes/navigation.php'; ?>

  <!-- Main Content -->
  <main id="main-content" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Welcome Section -->
    <div class="mb-8 animate-fade-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 mb-2">Welcome back, <?php echo h($_SESSION['voter_name'] ?? 'Voter'); ?>!</h2>
            <p class="text-sm sm:text-base text-slate-600">Your digital voting dashboard</p>
          </div>
          <div class="hidden md:block">
            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Election Status Card -->
    <div class="animate-slide-up">
      <?php if ($activeElection): ?>
        <?php 
        $electionStatus = get_election_status($activeElection);
        ?>
        
        <!-- Election Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
          <!-- Election Header -->
          <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 sm:p-6 text-white">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h3 class="text-xl sm:text-2xl font-bold mb-2"><?php echo h($activeElection['title']); ?></h3>
                <?php if ($activeElection['description']): ?>
                  <p class="text-blue-100 text-xs sm:text-sm"><?php echo h($activeElection['description']); ?></p>
                <?php endif; ?>
              </div>
              <div class="hidden md:block">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                  <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Election Status -->
          <div class="p-6">
            <?php if ($electionStatus === 'pending'): ?>
              <div class="bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 rounded-xl p-6">
                <div class="flex items-center">
                  <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <h4 class="text-lg font-semibold text-amber-800 mb-1">Election is Pending</h4>
                    <p class="text-amber-700 text-sm">Voting will start on <span class="font-medium"><?php echo date('M j, Y \a\t g:i A', strtotime($activeElection['start_date'])); ?></span></p>
                  </div>
                </div>
              </div>
              
            <?php elseif ($electionStatus === 'active'): ?>
              <?php if ($voterHasVoted): ?>
                <div class="bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-xl p-6">
                  <div class="flex items-center">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mr-4">
                      <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    </div>
                    <div class="flex-1">
                      <h4 class="text-lg font-semibold text-emerald-800 mb-1">Vote Submitted Successfully!</h4>
                      <p class="text-emerald-700 text-sm">Thank you for participating in the election.</p>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center">
                      <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                      </div>
                      <div>
                        <h4 class="text-lg font-semibold text-blue-800 mb-1">Election is Active</h4>
                        <p class="text-blue-700 text-sm">You can now cast your vote</p>
                      </div>
                    </div>
                    <a href="cast_vote.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-6 py-3 rounded-xl font-medium shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105">
                      <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Cast Your Vote
                      </div>
                    </a>
                  </div>
                  
                  <!-- Time Remaining Display -->
                  <?php 
                  $now = new DateTime();
                  $start = new DateTime($activeElection['start_date']);
                  $end = new DateTime($activeElection['end_date']);
                  $remaining = $now->diff($end);
                  $totalDuration = $start->diff($end);
                  $elapsed = $now->diff($start);
                  $progressPercentage = min(100, max(0, ($elapsed->days * 24 + $elapsed->h) / ($totalDuration->days * 24 + $totalDuration->h) * 100));
                  
                  // Pass election dates to JavaScript
                  $endTimestamp = $end->getTimestamp() * 1000; // Convert to milliseconds for JavaScript
                  $startTimestamp = $start->getTimestamp() * 1000;
                  ?>
                  <div class="mt-4 bg-blue-100 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-sm font-medium text-blue-800">Time Remaining:</span>
                      <span id="countdown-display" class="text-lg font-bold text-blue-900">Loading...</span>
                    </div>
                    <div class="w-full bg-blue-200 rounded-full h-2">
                      <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-500 h-2 rounded-full transition-all duration-300" style="width: <?php echo $progressPercentage; ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-blue-600 mt-1">
                      <span>Started: <?php echo date('M j, g:i A', strtotime($activeElection['start_date'])); ?></span>
                      <span>Ends: <?php echo date('M j, g:i A', strtotime($activeElection['end_date'])); ?></span>
                    </div>
                  </div>

                  <script>
                    // Real-time countdown timer
                    const electionEndTime = <?php echo $endTimestamp; ?>;
                    const electionStartTime = <?php echo $startTimestamp; ?>;
                    const countdownDisplay = document.getElementById('countdown-display');
                    const progressBar = document.getElementById('progress-bar');

                    function updateCountdown() {
                      const now = new Date().getTime();
                      const timeRemaining = electionEndTime - now;
                      const totalDuration = electionEndTime - electionStartTime;
                      const elapsed = now - electionStartTime;

                      if (timeRemaining > 0) {
                        // Calculate days, hours, minutes, seconds
                        const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);

                        // Format display string as day:hr:min:second
                        const paddedDays = String(days).padStart(2, '0');
                        const paddedHours = String(hours).padStart(2, '0');
                        const paddedMinutes = String(minutes).padStart(2, '0');
                        const paddedSeconds = String(seconds).padStart(2, '0');
                        
                        let displayText = `${paddedDays}:${paddedHours}:${paddedMinutes}:${paddedSeconds}`;

                        countdownDisplay.textContent = displayText;

                        // Update progress bar
                        if (elapsed > 0 && totalDuration > 0) {
                          const progressPercentage = Math.min(100, Math.max(0, (elapsed / totalDuration) * 100));
                          progressBar.style.width = progressPercentage + '%';
                        }

                        // Change color when time is running out
                        if (timeRemaining < 3600000) { // Less than 1 hour
                          countdownDisplay.className = 'text-lg font-bold text-red-600';
                          progressBar.className = 'bg-gradient-to-r from-red-500 to-orange-500 h-2 rounded-full transition-all duration-300';
                        } else if (timeRemaining < 7200000) { // Less than 2 hours
                          countdownDisplay.className = 'text-lg font-bold text-orange-600';
                          progressBar.className = 'bg-gradient-to-r from-orange-500 to-yellow-500 h-2 rounded-full transition-all duration-300';
                        }

                      } else {
                        // Election has ended
                        countdownDisplay.textContent = 'Election Ended';
                        countdownDisplay.className = 'text-lg font-bold text-red-600';
                        progressBar.style.width = '100%';
                        progressBar.className = 'bg-gradient-to-r from-red-500 to-red-600 h-2 rounded-full transition-all duration-300';
                        
                        // Optionally reload the page to show updated election status
                        setTimeout(() => {
                          window.location.reload();
                        }, 2000);
                      }
                    }

                    // Update countdown immediately and then every second
                    updateCountdown();
                    setInterval(updateCountdown, 1000);
                  </script>
                </div>
              <?php endif; ?>
              
            <?php else: ?>
              <div class="bg-gradient-to-r from-red-50 to-rose-50 border border-red-200 rounded-xl p-6">
                <div class="flex items-center">
                  <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <h4 class="text-lg font-semibold text-red-800 mb-1">Election Has Ended</h4>
                    <p class="text-red-700 text-sm">This election is no longer accepting votes</p>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
      <?php else: ?>
        <!-- No Active Election -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8 text-center">
          <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
          </div>
          <h3 class="text-xl font-semibold text-slate-800 mb-2">No Active Election</h3>
          <p class="text-slate-600">There are currently no elections available for voting.</p>
        </div>
      <?php endif; ?>
      
      <!-- Results Section -->
      <div class="mt-8">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-800">Election Results</h3>
              <p class="text-slate-600 text-sm">View published election results</p>
            </div>
            <a href="results.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all transform hover:scale-105">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
              View Results
            </a>
          </div>
          <p class="text-slate-500 text-sm">Check out the results of completed elections that have been published by administrators.</p>
        </div>
      </div>
    </div>
  </main>
</body>
</html>

