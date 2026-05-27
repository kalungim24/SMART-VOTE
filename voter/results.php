<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('voter');

// Get elections that voters can view results for
$electionsQuery = "SELECT e.*";
try {
    $pdo->query("SELECT voters_can_view_results FROM elections LIMIT 1");
    $electionsQuery .= ", CASE 
               WHEN e.voters_can_view_results = 1 THEN 'published'
               ELSE 'live'
           END as results_status
    FROM elections e";
} catch (PDOException $e) {
    // Columns don't exist, show all elections
    $electionsQuery .= ", 'live' as results_status
    FROM elections e";
}
$electionsQuery .= " ORDER BY e.created_at DESC";
$elections = $pdo->query($electionsQuery)->fetchAll();

// Get the selected election (from URL parameter)
$selectedElectionId = (int)($_GET['election'] ?? 0);
if ($selectedElectionId === 0 && !empty($elections)) {
    $selectedElectionId = (int)$elections[0]['id'];
}
$selectedElection = null;
$results = [];

if ($selectedElectionId > 0) {
    // Get the selected election
    $stmt = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $stmt->execute([$selectedElectionId]);
    $selectedElection = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedElection) {
        // Modified query to handle vote counting properly with election filtering
        $resultsQuery = "
            SELECT 
                c.name AS candidate_name,
                COALESCE(p.name, c.position) AS position_name,
                c.position_id,
                c.photo,
                c.symbol,
                COUNT(v.id) AS vote_count,
                ROUND(
                    (COUNT(v.id) * 100.0) / NULLIF((
                        SELECT COUNT(*) 
                        FROM votes v2
                        WHERE v2.election_id = ?
                          AND (v2.position_id = c.position_id OR v2.position = c.position)
                    ), 0), 2
                ) AS percentage
            FROM candidates c
            LEFT JOIN positions p ON c.position_id = p.id
            INNER JOIN election_positions ep ON ep.position_id = COALESCE(c.position_id, p.id)
                AND ep.election_id = ?
            LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ?";
        
        // Check if active column exists
        try {
            $pdo->query("SELECT active FROM candidates LIMIT 1");
            $resultsQuery .= " WHERE c.active = 1";
        } catch (PDOException $e) {
            // active column doesn't exist, don't filter
        }
        
        $resultsQuery .= " GROUP BY c.id, c.name, position_name, c.position_id, c.photo, c.symbol
            ORDER BY position_name, vote_count DESC, c.name";
        
        $results = $pdo->prepare($resultsQuery);
        $results->execute([$selectedElectionId, $selectedElectionId, $selectedElectionId]);
        $results = $results->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Election Results - <?php echo h(get_system_name($pdo)); ?></title>
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
    <!-- Header -->
    <div class="mb-8 animate-fade-in">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-3xl font-bold text-slate-800 mb-2">Election Results</h2>
            <p class="text-slate-600">View published election results</p>
          </div>
          <div class="hidden md:block">
            <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($elections)): ?>
      <!-- No Results Available -->
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8 text-center">
        <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
          </svg>
        </div>
        <h3 class="text-xl font-semibold text-slate-800 mb-2">No Results Available</h3>
        <p class="text-slate-600 mb-4">No election results have been published yet.</p>
        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-lg hover:bg-sky-600 transition-colors">
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          Back to Dashboard
        </a>
      </div>
    <?php else: ?>
      <!-- Election Selection -->
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6 mb-8">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Select Election to View Results</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($elections as $election): ?>
            <a href="?election=<?php echo (int)$election['id']; ?>" 
               class="block p-4 border border-slate-200 rounded-lg hover:border-brand hover:shadow-md transition-all <?php echo $selectedElectionId == $election['id'] ? 'border-brand bg-blue-50' : ''; ?>">
              <h4 class="font-semibold text-slate-800 mb-2"><?php echo h($election['name'] ?? $election['title']); ?></h4>
              <p class="text-sm text-slate-600 mb-2"><?php echo h($election['description']); ?></p>
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span><?php echo date('M j, Y', strtotime($election['start_date'])); ?></span>
                <?php if ($election['results_status'] === 'published'): ?>
                  <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">Published</span>
                <?php else: ?>
                  <span class="px-2 py-1 bg-slate-100 text-slate-800 rounded-full">Live</span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($selectedElection): ?>
        <!-- Results Display -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
          <div class="mb-6">
            <h3 class="text-2xl font-bold text-slate-800 mb-2"><?php echo h($selectedElection['name'] ?? $selectedElection['title']); ?></h3>
            <p class="text-slate-600"><?php echo h($selectedElection['description']); ?></p>
            <?php if (isset($selectedElection['results_published_at']) && $selectedElection['results_published_at']): ?>
            <p class="text-sm text-slate-500 mt-2">
              Published on <?php echo date('F j, Y \a\t g:i A', strtotime($selectedElection['results_published_at'])); ?>
            </p>
            <?php endif; ?>
          </div>

          <?php if (empty($results)): ?>
            <div class="text-center py-8">
              <p class="text-slate-600">No results available for this election yet. Check back after votes are cast.</p>
            </div>
          <?php else: ?>
            <!-- Results by Position -->
            <?php 
            $resultsByPosition = [];
            foreach ($results as $result) {
                $resultsByPosition[$result['position_name']][] = $result;
            }
            ?>
            
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <?php foreach ($resultsByPosition as $position => $positionResults): ?>
              <?php $positionId = preg_replace('/[^a-zA-Z0-9]/', '_', $position); ?>
              <?php $positionTotalVotes = array_sum(array_column($positionResults, 'vote_count')); ?>
              <div class="bg-white rounded-3xl shadow-lg border border-slate-200 overflow-hidden">
                <button type="button" class="w-full text-left px-6 py-5 flex items-center justify-between gap-4 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2" onclick="togglePositionDetails('<?php echo $positionId; ?>')">
                  <div>
                    <h4 class="text-xl font-semibold text-slate-800"><?php echo h($position); ?></h4>
                    <p class="text-sm text-slate-500 mt-1">Tap to expand for vote counts and candidate percentages.</p>
                  </div>
                  <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-slate-100 text-slate-700 font-semibold" id="toggle-icon-<?php echo $positionId; ?>">+</span>
                </button>

                <div class="px-6 pb-6 bg-slate-50">
                  <?php $colorPalette = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1']; ?>
                  <div class="rounded-full bg-slate-100 h-4 overflow-hidden">
                    <div class="flex h-full">
                      <?php foreach ($positionResults as $index => $result): ?>
                        <div class="h-full" style="width: <?php echo max(1, min(100, $result['percentage'])); ?>%; background: <?php echo $colorPalette[$index % count($colorPalette)]; ?>;"></div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?php foreach ($positionResults as $index => $result): ?>
                      <div class="flex items-center justify-between rounded-2xl bg-white border border-slate-200 p-3">
                        <div class="flex items-center gap-3">
                          <span class="inline-flex items-center justify-center w-3 h-3 rounded-full" style="background: <?php echo $colorPalette[$index % count($colorPalette)]; ?>;"></span>
                          <div>
                            <p class="text-sm font-semibold text-slate-800"><?php echo h($result['candidate_name']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo number_format($result['percentage'], 1); ?>%</p>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div id="details-<?php echo $positionId; ?>" class="hidden border-t border-slate-200 bg-slate-50 p-6">
                  <?php $colorPalette = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1']; ?>
                  <div class="space-y-4">
                    <div class="rounded-2xl bg-white border border-slate-200 p-4">
                      <p class="text-sm text-slate-500">Total votes cast</p>
                      <p class="mt-2 text-2xl font-semibold text-slate-900"><?php echo (int)$positionTotalVotes; ?></p>
                    </div>
                    <div class="rounded-2xl bg-white border border-slate-200 p-4">
                      <p class="text-sm text-slate-600 font-semibold mb-4">Votes by candidate</p>
                      <div class="space-y-3">
                        <?php foreach ($positionResults as $index => $result): ?>
                          <div class="flex items-center justify-between rounded-2xl bg-slate-50 border border-slate-200 p-3">
                            <div class="flex items-center gap-3">
                              <span class="inline-flex items-center justify-center w-3 h-3 rounded-full" style="background: <?php echo $colorPalette[$index % count($colorPalette)]; ?>;"></span>
                              <div>
                                <p class="text-sm font-semibold text-slate-800"><?php echo h($result['candidate_name']); ?></p>
                                <p class="text-xs text-slate-500"><?php echo (int)$result['vote_count']; ?> votes</p>
                              </div>
                            </div>
                            <span class="text-sm font-semibold text-slate-900"><?php echo (int)$result['vote_count']; ?></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <script>
    function togglePositionDetails(positionId) {
      const details = document.getElementById('details-' + positionId);
      const icon = document.getElementById('toggle-icon-' + positionId);
      if (!details || !icon) {
        return;
      }

      const isHidden = details.classList.contains('hidden');
      if (isHidden) {
        details.classList.remove('hidden');
        icon.textContent = '−';
      } else {
        details.classList.add('hidden');
        icon.textContent = '+';
      }
    }
  </script>
</body>
</html>

