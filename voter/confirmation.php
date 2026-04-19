<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('voter');

// Get election info for display
$activeElection = current_active_election($pdo);

// Get voter's vote summary (positions voted for)
$voterId = $_SESSION['voter_id'];
$voteSummary = [];
if ($activeElection) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT v.position 
        FROM votes v 
        WHERE v.voter_id = ? 
        ORDER BY v.position
    ");
    $stmt->execute([$voterId]);
    $voteSummary = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartVote System – School Online Voting</title>
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
            'bounce-slow': 'bounce 2s infinite'
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
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-green-50 to-emerald-100 flex items-center justify-center p-6">
  <div class="animate-fade-in">
    <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 p-12 text-center max-w-lg">
      <!-- Success Icon -->
      <div class="w-24 h-24 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-8 animate-bounce-slow">
        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
      
      <!-- Success Message -->
      <div class="animate-slide-up">
        <h1 class="text-3xl font-bold text-slate-800 mb-4">Vote Submitted Successfully!</h1>
        <p class="text-slate-600 text-lg mb-8">Thank you for participating in the election. Your vote has been securely recorded.</p>
        
        <!-- Election Details -->
        <?php if ($activeElection): ?>
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-6">
          <h3 class="font-semibold text-blue-900 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Election Details
          </h3>
          <div class="text-blue-800">
            <p class="font-medium text-lg mb-2"><?php echo h($activeElection['title']); ?></p>
            <?php if ($activeElection['description']): ?>
              <p class="text-sm mb-3"><?php echo h($activeElection['description']); ?></p>
            <?php endif; ?>
            <div class="flex justify-between text-sm">
              <span>Voting ends: <strong><?php echo date('M j, Y g:i A', strtotime($activeElection['end_date'])); ?></strong></span>
              <span>Status: <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Active</span></span>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Vote Summary -->
        <?php if (!empty($voteSummary)): ?>
        <div class="mb-6 bg-slate-50 border border-slate-200 rounded-xl p-6">
          <h3 class="font-semibold text-slate-800 mb-3 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Your Vote Summary
          </h3>
          <p class="text-slate-600 text-sm mb-3">You have successfully voted for the following positions:</p>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($voteSummary as $position): ?>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo h($position); ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- Security Notice -->
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-8">
          <div class="flex items-center justify-center">
            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            <span class="text-green-800 font-medium text-sm">Your vote is secure and confidential</span>
          </div>
        </div>
        
        <!-- Next Steps -->
        <div class="mb-8 bg-amber-50 border border-amber-200 rounded-xl p-4">
          <h4 class="font-semibold text-amber-800 mb-2 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            What's Next?
          </h4>
          <ul class="text-sm text-amber-700 space-y-1">
            <li>• You can view election results once voting closes</li>
            <li>• Check back later for the final results announcement</li>
            <li>• Your vote cannot be changed once submitted</li>
          </ul>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <a href="dashboard.php" class="inline-flex items-center bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-8 py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Back to Dashboard
          </a>
          <a href="results.php" class="inline-flex items-center bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-8 py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            View Results
          </a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

