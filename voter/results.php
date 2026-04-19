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
               ELSE 'not_published'
           END as results_status
    FROM elections e 
    WHERE e.voters_can_view_results = 1";
} catch (PDOException $e) {
    // Columns don't exist, show all elections
    $electionsQuery .= ", 'not_published' as results_status
    FROM elections e";
}
$electionsQuery .= " ORDER BY e.created_at DESC";
$elections = $pdo->query($electionsQuery)->fetchAll();

// Get the selected election (from URL parameter)
$selectedElectionId = (int)($_GET['election'] ?? 0);
$selectedElection = null;
$results = [];

if ($selectedElectionId > 0) {
    // Get the selected election
    $electionQuery = "SELECT * FROM elections WHERE id = ?";
    try {
        $pdo->query("SELECT voters_can_view_results FROM elections LIMIT 1");
        $electionQuery .= " AND voters_can_view_results = 1";
    } catch (PDOException $e) {
        // Columns don't exist, don't filter
    }
    $stmt = $pdo->prepare($electionQuery);
    $stmt->execute([$selectedElectionId]);
    $selectedElection = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedElection) {
        // Modified query to handle vote counting properly with election filtering
        $resultsQuery = "
            SELECT 
                c.name as candidate_name,
                c.position,
                c.photo,
                c.symbol,
                COUNT(CASE WHEN v.election_id = ? THEN v.id END) as vote_count,
                ROUND((COUNT(CASE WHEN v.election_id = ? THEN v.id END) * 100.0 / NULLIF((
                    SELECT COUNT(*) 
                    FROM votes 
                    WHERE election_id = ? AND candidate_id IN (
                        SELECT id FROM candidates WHERE position = c.position
                    )
                ), 0)), 2) as percentage
            FROM candidates c
            LEFT JOIN votes v ON c.id = v.candidate_id";
        
        // Check if active column exists
        try {
            $pdo->query("SELECT active FROM candidates LIMIT 1");
            $resultsQuery .= " WHERE c.active = 1";
        } catch (PDOException $e) {
            // active column doesn't exist, don't filter
        }
        
        $resultsQuery .= " GROUP BY c.id, c.name, c.position, c.photo, c.symbol
            ORDER BY c.position, vote_count DESC, c.name";
        
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
  <title>Election Results - SmartVote System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">Published</span>
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
              <p class="text-slate-600">No results available for this election.</p>
            </div>
          <?php else: ?>
            <!-- Results by Position -->
            <?php 
            $resultsByPosition = [];
            foreach ($results as $result) {
                $resultsByPosition[$result['position']][] = $result;
            }
            ?>
            
            <?php foreach ($resultsByPosition as $position => $positionResults): ?>
              <div class="mb-8 bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                <h4 class="text-xl font-semibold text-slate-800 mb-6 border-b border-slate-200 pb-3">
                  <?php echo h($position); ?>
                </h4>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  <!-- Chart Visualization -->
                  <div class="lg:order-1">
                    <div class="bg-slate-50 rounded-xl p-4">
                      <h5 class="text-sm font-medium text-slate-600 mb-3">Vote Distribution</h5>
                      <canvas id="chart_<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $position); ?>" height="200"></canvas>
                    </div>
                  </div>
                  
                  <!-- Results List -->
                  <div class="lg:order-2">
                    <div class="space-y-3">
                      <?php foreach ($positionResults as $index => $result): ?>
                        <div class="flex items-center p-3 border border-slate-200 rounded-lg <?php echo $index === 0 ? 'bg-gradient-to-r from-yellow-50 to-orange-50 border-yellow-200' : 'bg-slate-50'; ?>">
                          <div class="flex-shrink-0 mr-3">
                            <?php if ($index === 0): ?>
                              <div class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                              </div>
                            <?php else: ?>
                              <div class="w-6 h-6 bg-slate-400 rounded-full flex items-center justify-center">
                                <span class="text-white font-bold text-xs"><?php echo $index + 1; ?></span>
                              </div>
                            <?php endif; ?>
                          </div>
                          
                          <div class="flex-1 flex items-center">
                            <div class="flex-shrink-0 mr-3">
                              <?php if (isset($result['photo']) && !empty($result['photo'])): ?>
                                <img src="../<?php echo h($result['photo']); ?>" alt="<?php echo h($result['candidate_name']); ?>" 
                                     class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center" style="display: none;">
                                  <span class="text-white font-bold text-sm"><?php echo strtoupper(substr($result['candidate_name'], 0, 1)); ?></span>
                                </div>
                              <?php else: ?>
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center">
                                  <span class="text-white font-bold text-sm"><?php echo strtoupper(substr($result['candidate_name'], 0, 1)); ?></span>
                                </div>
                              <?php endif; ?>
                            </div>
                            
                            <div class="flex-1">
                              <h5 class="font-semibold text-slate-800 text-sm"><?php echo h($result['candidate_name']); ?></h5>
                              <div class="flex items-center space-x-3 text-xs text-slate-600">
                                <span><?php echo (int)$result['vote_count']; ?> votes</span>
                                <span><?php echo number_format($result['percentage'], 1); ?>%</span>
                              </div>
                            </div>
                            
                            <div class="flex-shrink-0">
                              <div class="w-16 bg-slate-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full" 
                                     style="width: <?php echo min(100, $result['percentage']); ?>%"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <script>
    // Chart.js configuration for results visualization
    const resultsData = <?php echo json_encode($results, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    const resultsByPosition = {};
    
    // Group results by position
    resultsData.forEach(result => {
      if (!resultsByPosition[result.position]) {
        resultsByPosition[result.position] = [];
      }
      resultsByPosition[result.position].push(result);
    });
    
    // Chart colors
    const chartColors = [
      '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', 
      '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6366F1'
    ];
    
    // Create charts for each position
    Object.keys(resultsByPosition).forEach((position, positionIndex) => {
      const candidates = resultsByPosition[position];
      const chartId = 'chart_' + position.replace(/[^a-zA-Z0-9]/g, '_');
      const ctx = document.getElementById(chartId);
      
      if (!ctx) return;
      
      const labels = candidates.map(c => c.candidate_name);
      const votes = candidates.map(c => Number(c.vote_count));
      const backgroundColor = chartColors[positionIndex % chartColors.length];
      
      new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: votes,
            backgroundColor: chartColors.slice(0, candidates.length),
            borderColor: '#ffffff',
            borderWidth: 2,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                padding: 20,
                usePointStyle: true,
                font: {
                  size: 12
                }
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = ((context.parsed / total) * 100).toFixed(1);
                  return context.label + ': ' + context.parsed + ' votes (' + percentage + '%)';
                }
              }
            }
          },
          cutout: '60%'
        }
      });
    });
  </script>
</body>
</html>

