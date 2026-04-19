<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_role('voter');

$security = new SecurityManager($pdo);
$security->setSecurityHeaders();

$active = current_active_election($pdo);
if (!$active) {
    header('Location: dashboard.php');
    exit;
}

// Check if election is actually active (not pending)
$electionStatus = get_election_status($active);
if ($electionStatus !== 'active') {
    header('Location: dashboard.php');
    exit;
}

$voterId = $_SESSION['voter_id'];
$message = '';

// Get current election ID
$electionId = $active['id'];

// Fetch positions the voter has already voted for in this election
$votedPositions = [];
try {
    $votedStmt = $pdo->prepare("SELECT DISTINCT position FROM votes WHERE voter_id = ? AND election_id = ?");
    $votedStmt->execute([$voterId, $electionId]);
    $votedPositions = $votedStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching voted positions: " . $e->getMessage());
}

// Fetch candidates grouped by position (check if active column exists)
$candidatesQuery = "SELECT * FROM candidates";
try {
    $pdo->query("SELECT active FROM candidates LIMIT 1");
    $candidatesQuery .= " WHERE active = 1";
} catch (PDOException $e) {
    // active column doesn't exist, use all candidates
}
$candidatesQuery .= " ORDER BY position, name";
$candidates = $pdo->query($candidatesQuery)->fetchAll();
$byPosition = [];
foreach ($candidates as $c) { $byPosition[$c['position']][] = $c; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$security->validateCSRFToken($csrfToken)) {
        $message = 'Security token validation failed. Please try again.';
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'vote'], 'CSRF token validation failed for voting');
    } else {
        // One vote per position - strict validation
        $pdo->beginTransaction();
        try {
            // Get current election ID
            $electionId = $active['id'];
            
            // Validate: Only one selection per position is allowed
            $voteData = [];
            $hasDuplicatePosition = false;
            
            // First, collect all submitted votes by iterating through all POST data
            $submittedVotes = [];
                foreach ($_POST as $key => $value) {
                if (strpos($key, 'choice_') === 0) {
                    // This is a position choice field
                    $positionHash = substr($key, 7); // Remove 'choice_' prefix
                    
                    // Find which position this hash corresponds to
                    $foundPosition = null;
                    foreach ($byPosition as $position => $candidates) {
                        if (md5($position) === $positionHash) {
                            $foundPosition = $position;
                                break;
                        }
                    }
                    
                    if ($foundPosition) {
                        // Handle both array and string values
                        $choices = is_array($value) ? $value : [$value];
                        $choices = array_filter($choices, function($val) {
                            return $val !== '' && $val !== null;
                        });
                        
                        if (count($choices) > 1) {
                    $hasDuplicatePosition = true;
                            $message = 'Error: Multiple candidates selected for the same position (' . htmlspecialchars($foundPosition) . '). Please select only one candidate per position.';
                            break; // Break out of foreach loop
                        }
                        
                        if (count($choices) === 1) {
                            $submittedVotes[$foundPosition] = (int)reset($choices);
                        }
                    }
                }
            }
            
            // Now process each submitted vote
            foreach ($submittedVotes as $position => $choice) {
                    // Get position ID from position name
                    $positionStmt = $pdo->prepare("SELECT id FROM positions WHERE name = ?");
                    $positionStmt->execute([$position]);
                    $positionData = $positionStmt->fetch();
                    $positionId = $positionData ? $positionData['id'] : null;
                    
                    // Strict check: prevent double voting for the same position
                    $check = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND position = ? AND election_id = ?");
                    $check->execute([$voterId, $position, $electionId]);
                    
                    if ((int)$check->fetchColumn() > 0) {
                        $hasDuplicatePosition = true;
                    $message = 'Error: You have already voted for the position: ' . htmlspecialchars($position) . '. Only one vote per position is allowed.';
                        break;
                    }
                    
                    // Validate candidate belongs to this position
                    $candidateCheck = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE id = ? AND position = ?");
                $candidateCheck->execute([$choice, $position]);
                    
                    if ((int)$candidateCheck->fetchColumn() === 0) {
                        $hasDuplicatePosition = true;
                    $message = 'Error: Invalid candidate selection for position: ' . htmlspecialchars($position) . '. Please refresh the page and try again.';
                        break;
                    }
                    
                    $voteData[] = [
                        'voter_id' => $voterId,
                    'candidate_id' => $choice,
                        'position' => $position,
                        'election_id' => $electionId,
                        'position_id' => $positionId
                    ];
            }
            
            if ($hasDuplicatePosition) {
                $pdo->rollBack();
            } else {
                // Insert all votes (one per position)
                foreach ($voteData as $vote) {
                    $ins = $pdo->prepare("INSERT INTO votes (voter_id, candidate_id, position, election_id, position_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $ins->execute([$vote['voter_id'], $vote['candidate_id'], $vote['position'], $vote['election_id'], $vote['position_id']]);
                }
                
                // Mark voter as having voted at least once
                if (!empty($voteData)) {
                    $pdo->prepare("UPDATE voters SET has_voted = 1 WHERE voter_id = ?")->execute([$voterId]);
                }
                
                $pdo->commit();
                header('Location: confirmation.php');
                exit;
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            $message = 'Error submitting your vote. Please try again.';
            error_log("Vote submission error: " . $e->getMessage());
        }
    }
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
    /* Ensure persistent selection styling */
    .persistent-selection {
      border-color: #2563eb !important;
      background: linear-gradient(to right, #dbeafe, #e0e7ff) !important;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
    }
    /* Style for candidate checkboxes */
    .candidate-checkbox:checked {
      background-color: #2563eb;
      border-color: #2563eb;
    }
    
    .candidate-checkbox:checked + .candidate-selected,
    .candidate-card:has(.candidate-checkbox:checked) label {
      border-color: #2563eb !important;
      background: linear-gradient(to right, #dbeafe, #e0e7ff) !important;
    }
    
    .candidate-checkbox:checked ~ .candidate-checked-badge,
    .candidate-card:has(.candidate-checkbox:checked) .candidate-checked-badge {
      opacity: 1 !important;
      transform: scale(1) !important;
    }
    
    .candidate-card:has(.candidate-checkbox:checked) .candidate-photo {
      border-color: #2563eb !important;
    }
    
    .candidate-card:has(.candidate-checkbox:checked) .candidate-symbol {
      border-color: #2563eb !important;
    }
    
    .candidate-card:has(.candidate-checkbox:checked) .candidate-name {
      color: #1e3a8a !important;
    }
    
    .candidate-card:has(.candidate-checkbox:checked) .candidate-description {
      color: #1e293b !important;
    }
    
    .candidate-card:has(.candidate-checkbox:checked) .candidate-number {
      background-color: #bfdbfe !important;
      color: #1e3a8a !important;
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
            <h2 class="text-3xl font-bold text-slate-800 mb-2">Cast Your Vote</h2>
            <p class="text-slate-600">Select your preferred candidates for each position</p>
          </div>
          <div class="hidden md:block">
            <div class="w-20 h-20 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center">
              <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Vote Progress Indicator -->
    <div class="mb-8 animate-slide-up">
      <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-slate-800">Voting Progress</h3>
          <span id="vote-progress" class="text-sm font-medium text-blue-600">0 of <?php echo count($byPosition); ?> positions selected</span>
        </div>
        <div class="w-full bg-slate-200 rounded-full h-3">
          <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <p class="text-sm text-slate-600 mt-2">Select your preferred candidate for each position below</p>
      </div>
    </div>

    <!-- Voting Form -->
    <div class="animate-slide-up">
      <?php if ($message): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
          </svg>
          <?php echo h($message); ?>
        </div>
      <?php endif; ?>
      
      <form method="post" class="space-y-8" id="voting-form" onsubmit="return validateVoteSubmission(event);">
        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
        <?php foreach ($byPosition as $position => $list): ?>
          <?php 
          // Check if voter has already voted for this position
          $hasVoted = in_array($position, $votedPositions);
          
          // Get the candidate they voted for
          $votedCandidate = null;
          if ($hasVoted) {
              try {
                  $votedStmt = $pdo->prepare("SELECT candidate_id FROM votes WHERE voter_id = ? AND election_id = ? AND position = ? LIMIT 1");
                  $votedStmt->execute([$voterId, $electionId, $position]);
                  $votedCandidateId = $votedStmt->fetchColumn();
                  if ($votedCandidateId) {
                      $candidateStmt = $pdo->prepare("SELECT name FROM candidates WHERE id = ?");
                      $candidateStmt->execute([$votedCandidateId]);
                      $votedCandidate = $candidateStmt->fetchColumn();
                  }
              } catch (PDOException $e) {
                  error_log("Error fetching voted candidate: " . $e->getMessage());
              }
          }
          ?>
          <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
            <!-- Position Header -->
            <div class="bg-gradient-to-r <?php echo $hasVoted ? 'from-amber-600 to-orange-700' : 'from-slate-600 to-slate-700'; ?> p-6 text-white">
              <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mr-4">
                  <?php if ($hasVoted): ?>
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  <?php else: ?>
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                  <?php endif; ?>
                </div>
                <div>
                  <h3 class="text-xl font-bold"><?php echo h($position); ?></h3>
                  <p class="text-slate-200 text-sm">
                    <?php if ($hasVoted): ?>
                      Already voted for this position
                    <?php else: ?>
                      Select your preferred candidate
                    <?php endif; ?>
                  </p>
                </div>
              </div>
            </div>
            
            <!-- Already Voted Message or Candidates -->
            <?php if ($hasVoted): ?>
              <div class="p-6">
                <div class="bg-amber-50 border-2 border-amber-200 rounded-xl p-6 text-center">
                  <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 bg-amber-500 rounded-full flex items-center justify-center">
                      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    </div>
                  </div>
                  <h4 class="text-xl font-bold text-amber-900 mb-2">You have already voted for this position</h4>
                  <?php if ($votedCandidate): ?>
                    <p class="text-amber-800 text-lg mb-3">
                      Your selection: <strong><?php echo h($votedCandidate); ?></strong>
                    </p>
                  <?php endif; ?>
                  <p class="text-amber-700 text-sm">
                    You cannot change your vote once submitted. Thank you for participating!
                  </p>
                </div>
              </div>
            <?php else: ?>
              <!-- Candidates -->
              <div class="p-6">
              <div class="grid gap-4">
                <?php foreach ($list as $c): ?>
                  <div class="candidate-card relative">
                    <label for="candidate-<?php echo (int)$c['id']; ?>" class="group relative flex items-center p-6 border-2 border-slate-200 rounded-xl hover:border-blue-400 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 cursor-pointer transition-all duration-300 candidate-selected:border-blue-600 candidate-selected:bg-gradient-to-r candidate-selected:from-blue-100 candidate-selected:to-indigo-100 candidate-selected:shadow-lg candidate-selected:ring-2 candidate-selected:ring-blue-500 candidate-selected:ring-offset-2" role="button" tabindex="0" aria-label="Select <?php echo h($c['name']); ?> for <?php echo h($position); ?>">
                      
                      <!-- Checkbox -->
                      <div class="flex-shrink-0 mr-4">
                        <input type="checkbox" name="<?php echo 'choice_' . md5($position); ?>" value="<?php echo (int)$c['id']; ?>" id="candidate-<?php echo (int)$c['id']; ?>" class="candidate-checkbox w-5 h-5 text-blue-600 border-2 border-slate-300 rounded focus:ring-blue-500 focus:ring-2 cursor-pointer transition-all duration-200" onchange="handleCandidateCheckbox(this, '<?php echo md5($position); ?>'); updateVoteProgress(); highlightSelection(this); persistSelection(this); validateOneSelection(this); updatePositionStatus(this);" onclick="updatePositionStatus(this);" aria-describedby="candidate-<?php echo (int)$c['id']; ?>-description" />
                      </div>
                      
                      <!-- Selection Status Badge -->
                      <div class="absolute -top-2 -right-2 w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center opacity-0 candidate-checked-badge transition-all duration-300 scale-75 z-10">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                      </div>

                      <div class="flex items-center w-full">
                        <!-- Candidate Photo -->
                        <div class="flex-shrink-0 mr-6">
                          <?php if (!empty($c['photo']) || !empty($c['photo_path'])): ?>
                            <?php $photoSrc = !empty($c['photo_path']) ? $c['photo_path'] : $c['photo']; ?>
                            <img src="../<?php echo h($photoSrc); ?>" alt="<?php echo h($c['name']); ?>" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-lg group-hover:shadow-xl group-hover:scale-110 candidate-photo transition-all duration-300" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center shadow-lg" style="display: none;">
                              <span class="text-white font-bold text-2xl"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></span>
                            </div>
                          <?php else: ?>
                            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center shadow-lg group-hover:shadow-xl group-hover:scale-110 candidate-photo transition-all duration-300">
                              <span class="text-white font-bold text-2xl"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></span>
                            </div>
                          <?php endif; ?>
                        </div>
                        
                        <!-- Candidate Symbol -->
                        <?php if (!empty($c['symbol']) || !empty($c['symbol_path'])): ?>
                          <div class="flex-shrink-0 mr-6">
                            <?php $symbolSrc = !empty($c['symbol_path']) ? $c['symbol_path'] : $c['symbol']; ?>
                            <div class="w-16 h-16 bg-white rounded-lg shadow-md border-2 border-slate-200 flex items-center justify-center p-2 group-hover:border-blue-300 candidate-symbol transition-all duration-300">
                              <img src="../<?php echo h($symbolSrc); ?>" alt="<?php echo h($c['name']); ?> symbol" class="max-w-full max-h-full object-contain" onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'text-slate-400 text-xs text-center\'>No Symbol</div>';" />
                            </div>
                          </div>
                        <?php endif; ?>
                        
                        <!-- Candidate Info -->
                        <div class="flex-1">
                          <div class="font-bold text-slate-800 group-hover:text-blue-800 candidate-name transition-colors text-xl mb-2">
                            <?php echo h($c['name']); ?>
                          </div>
                          <?php if (!empty($c['description'])): ?>
                            <div id="candidate-<?php echo (int)$c['id']; ?>-description" class="text-slate-600 group-hover:text-slate-700 candidate-description transition-colors leading-relaxed">
                              <?php echo h($c['description']); ?>
                            </div>
                          <?php else: ?>
                            <div class="text-slate-500 italic">No description provided</div>
                          <?php endif; ?>
                          
                          <!-- Candidate Number -->
                          <div class="mt-3 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 group-hover:bg-blue-100 group-hover:text-blue-800 candidate-number transition-all duration-300">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Candidate #<?php echo (int)$c['id']; ?>
                          </div>
                        </div>
                      </div>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              
              <!-- Position Summary -->
              <?php if (!$hasVoted): ?>
              <div class="mt-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
                <div class="flex items-center justify-between">
                  <div class="flex items-center text-sm text-slate-600">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <?php echo count($list); ?> candidate<?php echo count($list) != 1 ? 's' : ''; ?> available
                  </div>
                  <div class="position-status text-sm font-medium text-slate-500" id="status-<?php echo md5($position); ?>">
                    <span class="flex items-center text-slate-500">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                      No selection made
                    </span>
                  </div>
                </div>
                <div class="mt-2 text-xs text-blue-600 font-medium">
                  <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                  </svg>
                  Select only ONE candidate for this position
                </div>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        
        <!-- Vote Review Section -->
        <div id="vote-review" class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8 hidden">
          <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
            </div>
            <h3 class="text-2xl font-bold text-slate-800 mb-2">Review Your Votes</h3>
            <p class="text-slate-600">Please review your selections before submitting</p>
          </div>
          
          <div id="vote-summary" class="space-y-4 mb-8">
            <!-- Vote summary will be populated by JavaScript -->
          </div>
          
          <!-- Final Confirmation Checkbox -->
          <div class="mb-6 p-6 bg-green-50 border-2 border-green-200 rounded-xl">
            <label for="final-confirm-checkbox" class="flex items-center justify-center cursor-pointer group">
              <input type="checkbox" id="final-confirm-checkbox" class="sr-only" onchange="toggleSubmitButton(); updateCheckboxVisual(this);">
              <div id="checkbox-visual-final" class="w-6 h-6 border-2 border-green-600 rounded mr-3 flex items-center justify-center bg-white transition-all duration-200 group-hover:border-green-700 group-hover:shadow-md">
                <svg id="checkbox-checkmark-final" class="w-4 h-4 text-white opacity-0 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
              <span class="text-slate-800 font-semibold text-base select-none">
                I confirm that all my selections are correct and I want to submit my vote
              </span>
            </label>
            <p class="text-sm text-slate-600 mt-3 text-center italic">Please confirm once more before final submission</p>
          </div>
          
          <div class="flex space-x-4">
            <button type="button" id="edit-votes-btn" class="flex-1 bg-slate-200 hover:bg-slate-300 text-slate-700 px-6 py-3 rounded-xl font-semibold transition-all duration-200">
              <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                </svg>
                Edit Votes
              </div>
            </button>
            <button type="button" id="confirm-submit-btn" disabled class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
              <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Confirm & Submit
              </div>
            </button>
          </div>
        </div>

        <!-- Submit Button -->
        <div id="submit-section" class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8">
          <div class="text-center">
            <!-- Voter Confirmation Checkbox -->
            <div class="mb-6 p-6 bg-blue-50 border-2 border-blue-200 rounded-xl">
              <label for="voter-confirm-checkbox" class="flex items-center justify-center cursor-pointer group">
                <input type="checkbox" id="voter-confirm-checkbox" class="sr-only" onchange="toggleReviewButton(); updateCheckboxVisual(this);">
                <div id="checkbox-visual-voter" class="w-6 h-6 border-2 border-blue-500 rounded mr-3 flex items-center justify-center bg-white transition-all duration-200 group-hover:border-blue-600 group-hover:shadow-md">
                  <svg id="checkbox-checkmark-voter" class="w-4 h-4 text-white opacity-0 transition-opacity duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                  </svg>
                </div>
                <span class="text-slate-800 font-medium text-base select-none">
                  I confirm that I have reviewed my selections and want to proceed with submitting my vote
                </span>
              </label>
              <p class="text-sm text-slate-600 mt-3 text-center italic">Please check this box to confirm your voting selections</p>
            </div>
            
            <!-- Save for Later Button -->
            <button type="button" id="save-draft-btn" class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 mr-4 mb-4 sm:mb-0">
              <div class="flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                </svg>
                Save Draft
              </div>
            </button>
            
            <!-- Review Vote Button -->
            <button type="button" id="review-vote-btn" disabled class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-8 py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
              <div class="flex items-center justify-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                Review & Submit Vote
              </div>
            </button>
            
            <div class="mt-6 space-y-2">
              <p class="text-slate-500 text-sm">🔒 Your vote is secure and confidential</p>
              <p class="text-slate-400 text-xs">Select candidates for each position to proceed</p>
              <div id="voting-status" class="text-sm font-medium">
                <span class="text-slate-500">Voting Progress: </span>
                <span id="status-text" class="text-slate-600">No selections made</span>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>

  <script>
    // Enhanced voting functionality
    let votingData = {};
    let totalPositions = 0;
    
    function updateVoteProgress() {
      const positions = document.querySelectorAll('input[type="checkbox"].candidate-checkbox[name^="choice_"]');
      totalPositions = new Set(Array.from(positions).map(p => p.name)).size;
      let selectedCount = 0;
      
      // Count unique positions with selections and store data
      const selectedPositions = new Set();
      votingData = {};
      
      positions.forEach(input => {
        if (input.checked) {
          selectedPositions.add(input.name);
          const candidateCard = input.closest('.candidate-card');
          const candidateName = candidateCard?.querySelector('.candidate-name')?.textContent || candidateCard?.querySelector('.font-bold')?.textContent || 'Unknown';
          const positionName = getPositionNameFromInput(input.name);
          
          votingData[positionName] = {
            candidateId: input.value,
            candidateName: candidateName,
            inputName: input.name
          };
          
          // Update status for this position
          updatePositionStatus(input);
        }
      });
      selectedCount = selectedPositions.size;
      
      // Update all position statuses to ensure they're correct
      updatePositionStatuses();
      
      // Update progress display
      const progressText = document.getElementById('vote-progress');
      const progressBar = document.getElementById('progress-bar');
      const reviewBtn = document.getElementById('review-vote-btn');
      const statusText = document.getElementById('status-text');
      
      if (progressText) {
        progressText.textContent = `${selectedCount} of ${totalPositions} positions selected`;
      }
      
      if (progressBar) {
        const percentage = totalPositions > 0 ? (selectedCount / totalPositions) * 100 : 0;
        progressBar.style.width = `${percentage}%`;
        
        // Update progress bar color based on completion
        if (percentage === 100) {
          progressBar.className = 'bg-gradient-to-r from-green-500 to-emerald-600 h-3 rounded-full transition-all duration-300';
        } else if (percentage > 50) {
          progressBar.className = 'bg-gradient-to-r from-yellow-500 to-orange-500 h-3 rounded-full transition-all duration-300';
        } else {
          progressBar.className = 'bg-gradient-to-r from-blue-500 to-indigo-600 h-3 rounded-full transition-all duration-300';
        }
      }
      
      if (reviewBtn) {
        const confirmCheckbox = document.getElementById('voter-confirm-checkbox');
        reviewBtn.disabled = selectedCount === 0 || !confirmCheckbox?.checked;
      }
      
      if (statusText) {
        if (selectedCount === 0) {
          statusText.textContent = 'No selections made';
          statusText.className = 'text-slate-600';
        } else if (selectedCount === totalPositions) {
          statusText.textContent = 'All positions completed ✓';
          statusText.className = 'text-green-600';
        } else {
          statusText.textContent = `${selectedCount}/${totalPositions} positions selected`;
          statusText.className = 'text-blue-600';
        }
      }
      
      // Update position status indicators
      updatePositionStatuses();
    }
    
    function handleCandidateCheckbox(checkbox, positionHash) {
      // Ensure only one candidate per position is selected (like radio buttons)
      if (checkbox.checked) {
        // Get all checkboxes in the same position group
        const groupName = checkbox.name;
        document.querySelectorAll(`input[name="${groupName}"]`).forEach(cb => {
          if (cb !== checkbox && cb.checked) {
            cb.checked = false;
            // Update visual state for unchecked candidate
            const otherCard = cb.closest('.candidate-card');
            if (otherCard) {
              otherCard.querySelector('label')?.classList.remove('candidate-selected');
              const badge = otherCard.querySelector('.candidate-checked-badge');
              if (badge) {
                badge.classList.remove('opacity-100');
                badge.classList.add('opacity-0');
              }
            }
          }
        });
        
        // Update visual state for checked candidate
        const card = checkbox.closest('.candidate-card');
        if (card) {
          card.querySelector('label')?.classList.add('candidate-selected');
          const badge = card.querySelector('.candidate-checked-badge');
          if (badge) {
            badge.classList.remove('opacity-0');
            badge.classList.add('opacity-100');
          }
        }
      } else {
        // Update visual state when unchecked
        const card = checkbox.closest('.candidate-card');
        if (card) {
          card.querySelector('label')?.classList.remove('candidate-selected');
          const badge = card.querySelector('.candidate-checked-badge');
          if (badge) {
            badge.classList.remove('opacity-100');
            badge.classList.add('opacity-0');
          }
        }
      }
    }
    
    function highlightSelection(input) {
      // Add visual feedback when selection changes
      const card = input.closest('.candidate-card');
      const label = card?.querySelector('label');
      
      // Ensure selection is persistent
      if (input.checked) {
        // Force the checked state to be visually clear
        if (label) {
          label.classList.add('candidate-selected', 'selected');
        }
        
        // Update badge visibility
        const badge = card?.querySelector('.candidate-checked-badge');
        if (badge) {
          badge.classList.remove('opacity-0', 'scale-75');
          badge.classList.add('opacity-100', 'scale-100');
        }
        
        // Animate the selection (light animation, doesn't remove selection)
        if (label) {
        label.style.transform = 'scale(1.02)';
        setTimeout(() => {
          label.style.transform = '';
        }, 200);
        }
        
        // Show success feedback (non-intrusive)
        showTemporaryFeedback(card, 'Selected!', 'success');
      } else {
        // Remove selection styling if unchecked
        if (label) {
          label.classList.remove('candidate-selected', 'selected', 'persistent-selection');
        }
        
        // Update badge visibility
        const badge = card?.querySelector('.candidate-checked-badge');
        if (badge) {
          badge.classList.add('opacity-0', 'scale-75');
          badge.classList.remove('opacity-100', 'scale-100');
        }
      }
      
      // Update position status immediately and persistently
      updatePositionStatus(input);
    }
    
    function updatePositionStatus(input) {
      // Update the status indicator for the position
      if (!input || !input.name) return;
      
      // Extract position hash directly from input name
      if (!input.name.startsWith('choice_')) return;
      const positionHash = input.name.substring(7); // Remove 'choice_' prefix
      const statusElement = document.getElementById(`status-${positionHash}`);
      
      if (statusElement) {
        // Check if any candidate in this position is selected
        const allCheckboxesInGroup = document.querySelectorAll(`input[name="${input.name}"]`);
        let checkedCheckbox = null;
        
        allCheckboxesInGroup.forEach(checkbox => {
          if (checkbox.checked) {
            checkedCheckbox = checkbox;
          }
        });
        
        if (checkedCheckbox) {
          // A candidate is selected - show the candidate name
          const selectedCard = checkedCheckbox.closest('.candidate-card');
          const candidateName = selectedCard?.querySelector('.candidate-name')?.textContent || selectedCard?.querySelector('.font-bold')?.textContent || 'Selected';
            statusElement.innerHTML = `
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-green-600 font-semibold">Selected: ${candidateName}</span>
              </span>
            `;
            statusElement.className = 'text-sm font-medium';
        } else {
          // No candidate selected
          statusElement.innerHTML = `
            <span class="flex items-center text-slate-500">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              No selection made
            </span>
          `;
          statusElement.className = 'text-sm font-medium text-slate-500';
        }
      }
    }
    
    function updatePositionStatuses() {
      // Update all position statuses based on current selections
      document.querySelectorAll('.position-status').forEach(status => {
        const positionHash = status.id.replace('status-', '');
        const positionName = positionHashMap[positionHash] || '';
        
        if (!positionName) return;
        
        // Find the checkbox group for this position
        let checkedCheckbox = null;
        const positionInputName = 'choice_' + positionHash;
        
        document.querySelectorAll(`input[type="checkbox"].candidate-checkbox[name="${positionInputName}"]`).forEach(checkbox => {
          if (checkbox.checked) {
            checkedCheckbox = checkbox;
          }
        });
        
        if (checkedCheckbox) {
          // A candidate is selected - show the candidate name
          const selectedCard = checkedCheckbox.closest('.candidate-card');
          const candidateName = selectedCard?.querySelector('.candidate-name')?.textContent || selectedCard?.querySelector('.font-bold')?.textContent || 'Selected';
            status.innerHTML = `
              <span class="flex items-center">
                <svg class="w-4 h-4 mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                <span class="text-green-600 font-semibold">Selected: ${candidateName}</span>
              </span>
            `;
            status.className = 'text-sm font-medium';
        } else {
          // No candidate selected
          status.innerHTML = `
            <span class="flex items-center text-slate-500">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              No selection made
            </span>
          `;
          status.className = 'text-sm font-medium text-slate-500';
        }
      });
    }
    
    // Position hash mapping from PHP (md5 hash -> position name)
    const positionHashMap = <?php 
      $hashMap = [];
      foreach ($byPosition as $position => $list) {
        $hash = md5($position);
        $hashMap[$hash] = $position;
      }
      echo json_encode($hashMap);
    ?>;
    
    // Reverse mapping (position name -> md5 hash) for debugging
    const positionNameToHash = {};
    Object.entries(positionHashMap).forEach(([hash, name]) => {
      positionNameToHash[name] = hash;
    });
    
    function getPositionNameFromInput(inputName) {
      // Extract position name from input name using PHP-generated MD5 hashes
      if (!inputName || typeof inputName !== 'string') {
      return '';
    }
    
      if (inputName.startsWith('choice_')) {
        const hash = inputName.substring(7); // Remove 'choice_' prefix
        const positionName = positionHashMap[hash];
        
        // Debug: log if hash not found
        if (!positionName) {
          console.warn('Position hash not found:', hash, 'Available hashes:', Object.keys(positionHashMap));
        }
        
        return positionName || '';
      }
      return '';
    }
    
    function validateOneSelection(input) {
      // Ensure only one selection per position (checkboxes need this enforced)
      const allCheckboxesInGroup = document.querySelectorAll(`input[type="checkbox"][name="${input.name}"]`);
      let checkedCount = 0;
      
      allCheckboxesInGroup.forEach(checkbox => {
        if (checkbox.checked) {
          checkedCount++;
        }
      });
      
      // If somehow multiple are checked, uncheck all except the current one
      if (checkedCount > 1 && input.checked) {
        allCheckboxesInGroup.forEach(checkbox => {
          if (checkbox !== input && checkbox.checked) {
            checkbox.checked = false;
            // Remove visual selection from unchecked items
            const uncheckedCard = checkbox.closest('.candidate-card');
            const uncheckedLabel = uncheckedCard?.querySelector('label');
            if (uncheckedLabel) {
              uncheckedLabel.classList.remove('persistent-selection', 'candidate-selected');
            }
            const badge = uncheckedCard?.querySelector('.candidate-checked-badge');
            if (badge) {
              badge.classList.add('opacity-0', 'scale-75');
              badge.classList.remove('opacity-100', 'scale-100');
            }
          }
        });
      }
      
      // Ensure only the checked one has visual styling
      allCheckboxesInGroup.forEach(checkbox => {
        const card = checkbox.closest('.candidate-card');
        const label = card?.querySelector('label');
        if (label) {
          if (checkbox.checked) {
            label.classList.add('persistent-selection', 'candidate-selected');
            const badge = card?.querySelector('.candidate-checked-badge');
            if (badge) {
              badge.classList.remove('opacity-0', 'scale-75');
              badge.classList.add('opacity-100', 'scale-100');
            }
          } else {
            label.classList.remove('persistent-selection', 'candidate-selected');
            const badge = card?.querySelector('.candidate-checked-badge');
            if (badge) {
              badge.classList.add('opacity-0', 'scale-75');
              badge.classList.remove('opacity-100', 'scale-100');
            }
          }
        }
      });
      
      // Update position status after validation
      updatePositionStatus(input);
    }
    
    function persistSelection(input) {
      // Ensure the selection persists by explicitly maintaining checked state
      if (input.checked) {
        // Uncheck other checkboxes in the same group first (only one per position)
        const allCheckboxesInGroup = document.querySelectorAll(`input[type="checkbox"][name="${input.name}"]`);
        allCheckboxesInGroup.forEach(checkbox => {
          if (checkbox !== input && checkbox.checked) {
            checkbox.checked = false;
            const otherCard = checkbox.closest('.candidate-card');
            const otherLabel = otherCard?.querySelector('label');
            if (otherLabel) {
              otherLabel.classList.remove('persistent-selection', 'candidate-selected');
            }
            const otherBadge = otherCard?.querySelector('.candidate-checked-badge');
            if (otherBadge) {
              otherBadge.classList.add('opacity-0', 'scale-75');
              otherBadge.classList.remove('opacity-100', 'scale-100');
            }
          }
        });
        
        // Save to votingData immediately
        const positionName = getPositionNameFromInput(input.name);
        const candidateCard = input.closest('.candidate-card');
        const candidateName = candidateCard?.querySelector('.candidate-name')?.textContent || candidateCard?.querySelector('.font-bold')?.textContent || 'Unknown';
        
        votingData[positionName] = {
          candidateId: input.value,
          candidateName: candidateName,
          inputName: input.name
        };
        
        // Ensure visual state is clear
        const cardLabel = candidateCard?.querySelector('label');
        if (cardLabel) {
          cardLabel.classList.add('persistent-selection', 'candidate-selected');
        }
        
        const badge = candidateCard?.querySelector('.candidate-checked-badge');
        if (badge) {
          badge.classList.remove('opacity-0', 'scale-75');
          badge.classList.add('opacity-100', 'scale-100');
        }
      }
    }
    
    function updateCheckboxVisual(checkbox) {
      // Update visual checkbox based on checked state
      if (checkbox.id === 'voter-confirm-checkbox') {
        const visual = document.getElementById('checkbox-visual-voter');
        const checkmark = document.getElementById('checkbox-checkmark-voter');
        if (checkbox.checked) {
          if (visual) {
            visual.classList.add('bg-blue-600', 'border-blue-600');
            visual.classList.remove('bg-white');
          }
          if (checkmark) {
            checkmark.classList.remove('opacity-0');
            checkmark.classList.add('opacity-100');
          }
        } else {
          if (visual) {
            visual.classList.remove('bg-blue-600', 'border-blue-600');
            visual.classList.add('bg-white');
          }
          if (checkmark) {
            checkmark.classList.add('opacity-0');
            checkmark.classList.remove('opacity-100');
          }
        }
      } else if (checkbox.id === 'final-confirm-checkbox') {
        const visual = document.getElementById('checkbox-visual-final');
        const checkmark = document.getElementById('checkbox-checkmark-final');
        if (checkbox.checked) {
          if (visual) {
            visual.classList.add('bg-green-600', 'border-green-600');
            visual.classList.remove('bg-white');
          }
          if (checkmark) {
            checkmark.classList.remove('opacity-0');
            checkmark.classList.add('opacity-100');
          }
        } else {
          if (visual) {
            visual.classList.remove('bg-green-600', 'border-green-600');
            visual.classList.add('bg-white');
          }
          if (checkmark) {
            checkmark.classList.add('opacity-0');
            checkmark.classList.remove('opacity-100');
          }
        }
      }
    }
    
    function toggleReviewButton() {
      const confirmCheckbox = document.getElementById('voter-confirm-checkbox');
      const reviewBtn = document.getElementById('review-vote-btn');
      const positions = document.querySelectorAll('[name^="choice_"]');
      const selectedPositions = new Set();
      
      positions.forEach(input => {
        if (input.checked) {
          selectedPositions.add(input.name);
        }
      });
      
      if (reviewBtn) {
        reviewBtn.disabled = selectedPositions.size === 0 || !confirmCheckbox?.checked;
      }
    }
    
    function toggleSubmitButton() {
      const finalConfirmCheckbox = document.getElementById('final-confirm-checkbox');
      const submitBtn = document.getElementById('confirm-submit-btn');
      
      if (submitBtn) {
        submitBtn.disabled = !finalConfirmCheckbox?.checked;
      }
    }
    
    function validateVoteSubmission(event) {
      // Final validation before form submission
      const allRadioGroups = new Set();
      const selectedPositions = {};
      let hasMultipleSelections = false;
      let errorMessage = '';
      
      // Check if confirmation checkbox is checked
      const finalConfirmCheckbox = document.getElementById('final-confirm-checkbox');
      const voterConfirmCheckbox = document.getElementById('voter-confirm-checkbox');
      
      // If we're in review mode, check final confirmation
      const reviewSection = document.getElementById('vote-review');
      if (reviewSection && !reviewSection.classList.contains('hidden')) {
        if (!finalConfirmCheckbox?.checked) {
          event.preventDefault();
          alert('Please check the confirmation box before submitting your vote.');
          return false;
        }
      } else {
        // If submitting directly, check initial confirmation
        if (!voterConfirmCheckbox?.checked) {
          event.preventDefault();
          alert('Please check the confirmation box before submitting your vote.');
          return false;
        }
      }
      
      // Get all checkbox groups
      document.querySelectorAll('input[type="checkbox"].candidate-checkbox').forEach(checkbox => {
        allRadioGroups.add(checkbox.name);
      });
      
      // Check each position group
      allRadioGroups.forEach(groupName => {
        const checkboxesInGroup = document.querySelectorAll(`input[type="checkbox"][name="${groupName}"]`);
        let checkedCount = 0;
        let checkedValue = null;
        
        checkboxesInGroup.forEach(checkbox => {
          if (checkbox.checked) {
            checkedCount++;
            checkedValue = checkbox.value;
          }
        });
        
        if (checkedCount > 1) {
          hasMultipleSelections = true;
          errorMessage = 'Error: Multiple candidates selected for the same position. Please select only one candidate per position.';
        } else if (checkedCount === 1) {
          const positionName = getPositionNameFromInput(groupName);
          selectedPositions[positionName] = checkedValue;
        }
      });
      
      if (hasMultipleSelections) {
        event.preventDefault();
        alert(errorMessage);
        return false;
      }
      
      // Validate at least one position is selected
      const totalSelected = Object.keys(selectedPositions).length;
      if (totalSelected === 0) {
        event.preventDefault();
        alert('Please select at least one candidate before submitting your vote.');
        return false;
      }
      
      return true;
    }
    
    function showTemporaryFeedback(element, message, type) {
      const feedback = document.createElement('div');
      feedback.className = `absolute top-0 right-0 transform -translate-y-2 translate-x-2 px-3 py-1 rounded-full text-xs font-medium z-20 shadow-lg ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
      }`;
      feedback.textContent = message;
      
      element.style.position = 'relative';
      element.appendChild(feedback);
      
      // Remove feedback but keep selection visual state
      setTimeout(() => {
        feedback.style.opacity = '0';
        feedback.style.transition = 'opacity 0.3s';
        setTimeout(() => {
          feedback.remove();
        }, 300);
      }, 1500);
    }
    
    // Review and confirmation functionality
    document.getElementById('review-vote-btn').addEventListener('click', function() {
      // Check if confirmation checkbox is checked
      const confirmCheckbox = document.getElementById('voter-confirm-checkbox');
      
      if (!confirmCheckbox || !confirmCheckbox.checked) {
        alert('Please check the confirmation box before reviewing your vote.');
        return;
      }
      
      showVoteReview();
    });
    
    document.getElementById('edit-votes-btn').addEventListener('click', function() {
      hideVoteReview();
    });
    
    document.getElementById('confirm-submit-btn').addEventListener('click', function() {
      // Check if final confirmation checkbox is checked
      const finalConfirmCheckbox = document.getElementById('final-confirm-checkbox');
      
      if (!finalConfirmCheckbox || !finalConfirmCheckbox.checked) {
        alert('Please check the confirmation box before submitting your vote.');
        return;
      }
      
      // Validate before submitting
      const form = document.getElementById('voting-form');
      const fakeEvent = { preventDefault: () => {} };
      
      if (validateVoteSubmission(fakeEvent)) {
        if (confirm('Are you sure you want to submit your vote? This action cannot be undone.')) {
          form.submit();
        }
      } else {
        // If validation fails, go back to edit
        hideVoteReview();
      }
    });
    
    document.getElementById('save-draft-btn').addEventListener('click', function() {
      saveDraft();
    });
    
    function showVoteReview() {
      const reviewSection = document.getElementById('vote-review');
      const submitSection = document.getElementById('submit-section');
      const summaryContainer = document.getElementById('vote-summary');
      
      // Rebuild votingData from all currently checked checkboxes
      votingData = {};
      
      console.log('=== Starting vote review (Position-First Method) ===');
      
      // NEW APPROACH: Find all position containers first, then check which have checked boxes
      // This is more reliable than traversing up from checkboxes
      
      // Step 1: Find all position containers in the form
      // Position containers are: form > .bg-white.rounded-2xl.overflow-hidden (excluding vote-review and submit-section)
      const form = document.querySelector('form#voting-form') || document.querySelector('form');
      if (!form) {
        console.error('❌ Could not find form element!');
        return;
      }
      
      // Find all potential position containers - try multiple selectors
      const allContainers1 = form.querySelectorAll('.bg-white.rounded-2xl.overflow-hidden');
      const allContainers2 = form.querySelectorAll('.rounded-2xl.overflow-hidden');
      const allContainers3 = form.querySelectorAll('form > .bg-white.rounded-2xl');
      
      // Combine and deduplicate
      const allContainersSet = new Set([...allContainers1, ...allContainers2, ...allContainers3]);
      const allContainers = Array.from(allContainersSet);
      console.log('Found', allContainers.length, 'potential position containers (deduplicated)');
      
      // Log all containers for debugging
      allContainers.forEach((container, idx) => {
        const id = container.id || '';
        const hasHeader = container.querySelector('.bg-gradient-to-r h3') !== null ||
                         container.querySelector('h3.text-xl.font-bold') !== null ||
                         container.querySelector('h3') !== null;
        const hasCheckboxes = container.querySelector('input[type="checkbox"].candidate-checkbox') !== null;
        console.log(`  Container ${idx + 1}: ID="${id}", hasHeader=${hasHeader}, hasCheckboxes=${hasCheckboxes}`);
      });
      
      // Filter to only actual position containers (exclude vote-review and submit-section)
      const positionContainers = allContainers.filter(container => {
        const id = container.id || '';
        if (id === 'vote-review' || id === 'submit-section') {
          console.log('Skipping excluded container:', id);
          return false;
        }
        
        // Must have position header (h3 inside .bg-gradient-to-r OR any h3)
        const hasHeader = container.querySelector('.bg-gradient-to-r h3') !== null ||
                         container.querySelector('.bg-gradient-to-r h3.text-xl.font-bold') !== null ||
                         container.querySelector('h3.text-xl.font-bold') !== null ||
                         container.querySelector('h3') !== null;
        
        // Must have candidate checkboxes
        const hasCheckboxes = container.querySelector('input[type="checkbox"].candidate-checkbox') !== null ||
                             container.querySelector('input[type="checkbox"][name^="choice_"]') !== null;
        
        const isValid = hasHeader && hasCheckboxes;
        if (isValid) {
          const header = container.querySelector('.bg-gradient-to-r h3') || 
                        container.querySelector('h3.text-xl.font-bold') ||
                        container.querySelector('h3');
          const headerText = header ? header.textContent?.trim() : 'Unknown';
          console.log(`✓ Valid position container found: "${headerText}" (ID: ${id})`);
        } else {
          console.log(`✗ Invalid container (ID: ${id}): hasHeader=${hasHeader}, hasCheckboxes=${hasCheckboxes}`);
        }
        
        return isValid;
      });
      
      console.log('Found', positionContainers.length, 'valid position containers');
      
      if (positionContainers.length === 0) {
        console.error('❌ No valid position containers found!');
        console.error('All containers:', allContainers);
        votingData = {};
      } else {
        // NEW APPROACH: Process each position container and find checked checkboxes within it
        positionContainers.forEach((positionContainer, containerIndex) => {
          try {
            console.log(`\n--- Processing position container ${containerIndex + 1}/${positionContainers.length} ---`);
            
            // Step 1: Extract position name from header
            let positionName = null;
            const headerSelectors = [
              '.bg-gradient-to-r.from-slate-600.to-slate-700 h3.text-xl.font-bold',
              '.bg-gradient-to-r.from-slate-600 h3.text-xl.font-bold',
              '.bg-gradient-to-r h3.text-xl.font-bold',
              '.bg-gradient-to-r h3',
              'h3.text-xl.font-bold',
              'h3.font-bold',
              'h3'
            ];
            
            for (const selector of headerSelectors) {
              const header = positionContainer.querySelector(selector);
              if (header) {
                positionName = header.textContent?.trim() || '';
                if (positionName && positionName.length > 0) {
                  console.log(`  ✓ Position name found: "${positionName}"`);
                  break;
                }
              }
            }
            
            // Fallback: If position name extraction failed, try to get it from checkbox hash map
            if (!positionName || positionName === '') {
              console.warn(`  ⚠ Could not extract position name from header, trying hash map fallback`);
              
              // Find any checkbox in this container and use its hash to look up position name
              const anyCheckbox = positionContainer.querySelector('input[type="checkbox"][name^="choice_"]');
              if (anyCheckbox && anyCheckbox.name) {
                const positionHash = anyCheckbox.name.substring(7); // Remove 'choice_' prefix
                if (positionHashMap && positionHashMap[positionHash]) {
                  positionName = positionHashMap[positionHash];
                  console.log(`  ✓ Position name from hash map: "${positionName}"`);
                } else {
                  console.error(`  ✗ Hash not found in map: ${positionHash}`);
                  console.error(`  Available hashes:`, Object.keys(positionHashMap || {}));
                }
              }
              
              if (!positionName || positionName === '') {
                console.error(`  ✗✗✗ Could not extract position name from container ${containerIndex + 1}`);
                console.error(`  Container HTML preview:`, positionContainer.innerHTML.substring(0, 500));
                // Don't skip - continue with placeholder name
                positionName = `Position ${containerIndex + 1}`;
                console.warn(`  Using placeholder name: "${positionName}"`);
              }
            }
            
            // Step 2: Find checked checkboxes within this container
            let checkedCheckboxes = positionContainer.querySelectorAll('input[type="checkbox"].candidate-checkbox:checked');
            console.log(`  Method 1 - Found ${checkedCheckboxes.length} checked checkbox(es) via :checked selector in "${positionName}"`);
            
            // Fallback: find all and filter by checked property
            if (checkedCheckboxes.length === 0) {
              const allCheckboxes = positionContainer.querySelectorAll('input[type="checkbox"].candidate-checkbox');
              console.log(`  Method 2 - Found ${allCheckboxes.length} total checkboxes in "${positionName}"`);
              checkedCheckboxes = Array.from(allCheckboxes).filter(cb => cb.checked === true);
              console.log(`  Method 2 - Found ${checkedCheckboxes.length} checked checkboxes after filtering`);
            }
            
            // Another fallback: find by name pattern
            if (checkedCheckboxes.length === 0) {
              const allChoiceInputs = positionContainer.querySelectorAll('input[type="checkbox"][name^="choice_"]');
              console.log(`  Method 3 - Found ${allChoiceInputs.length} choice_ checkboxes in "${positionName}"`);
              checkedCheckboxes = Array.from(allChoiceInputs).filter(cb => cb.checked === true);
              console.log(`  Method 3 - Found ${checkedCheckboxes.length} checked checkboxes after filtering`);
            }
            
            if (checkedCheckboxes.length === 0) {
              console.log(`  ⚠ No selection made for "${positionName}"`);
              return; // No selection in this position
            }
            
            // Step 3: Process each checked checkbox (should only be one per position)
            checkedCheckboxes.forEach((checkbox, checkboxIndex) => {
              if (!checkbox || !checkbox.checked) return;
              
              console.log(`  Processing checkbox ${checkboxIndex + 1}/${checkedCheckboxes.length}:`, checkbox.name, '=', checkbox.value);
              
              // Extract candidate name from the candidate card
              let candidateName = null;
              const candidateCard = checkbox.closest('.candidate-card');
              
              if (candidateCard) {
                // Method 1: Look for .candidate-name class (most reliable)
                let nameElement = candidateCard.querySelector('.candidate-name');
                
                if (nameElement) {
                  candidateName = nameElement.textContent?.trim() || null;
                } else {
                  // Method 2: Look for .font-bold.text-xl.text-slate-800 or .font-bold.text-xl
                  nameElement = candidateCard.querySelector('.font-bold.text-xl.text-slate-800') ||
                              candidateCard.querySelector('.font-bold.text-xl') ||
                              candidateCard.querySelector('.text-xl.font-bold');
                  
                  if (nameElement) {
                    candidateName = nameElement.textContent?.trim() || null;
                  } else {
                    // Method 3: Find all bold elements and pick the right one
                    const boldElements = candidateCard.querySelectorAll('.font-bold');
                    for (const bold of boldElements) {
                      const text = bold.textContent?.trim() || '';
                      // Skip candidate numbers, position names, and very short text
                      if (text && 
                          !text.includes('Candidate #') && 
                          !text.includes('#' + checkbox.value) &&
                          !text.includes(positionName) &&
                          text.length > 2 &&
                          text.length < 100) {
                        candidateName = text;
                        break;
                      }
                    }
                  }
                }
                
                if (candidateName) {
                  candidateName = candidateName.replace(/\s+/g, ' ').trim();
                  console.log(`    ✓ Candidate name: "${candidateName}"`);
                } else {
                  console.warn(`    ⚠ Could not extract candidate name, using fallback`);
                  candidateName = 'Candidate #' + checkbox.value;
                }
              } else {
                console.warn(`    ⚠ Could not find candidate card, using fallback`);
                candidateName = 'Candidate #' + checkbox.value;
              }
              
              // Extract position hash from checkbox name for reference
              const positionHash = checkbox.name.startsWith('choice_') ? checkbox.name.substring(7) : '';
              
              // Store in votingData
              votingData[positionName] = {
                candidateId: checkbox.value,
                candidateName: candidateName,
                inputName: checkbox.name,
                positionHash: positionHash,
                positionName: positionName
              };
              
              console.log(`    ✓✓✓ Stored: "${positionName}" -> "${candidateName}"`);
            });
            
          } catch (error) {
            console.error(`Error processing position container ${containerIndex + 1}:`, error);
            console.error('Stack:', error.stack);
          }
        });
      }
      
      console.log('=== Final Results ===');
      console.log('Final votingData:', votingData);
      console.log('Number of positions in votingData:', Object.keys(votingData).length);
      console.log('Position names in votingData:', Object.keys(votingData));
      
      if (Object.keys(votingData).length === 0) {
        console.error('❌❌❌ votingData is EMPTY after processing!');
        console.error('This means no positions were successfully extracted.');
        console.error('Position containers found:', positionContainers ? positionContainers.length : 0);
        console.error('Please check the console logs above to see where the extraction failed.');
      }
      
      // Build vote summary - filter out invalid entries and sort by position name
      let summaryHTML = '';
      
      // Filter out any entries where position name is invalid (hash-like or empty)
      const validVotes = Object.entries(votingData).filter(([position, vote]) => {
        // Basic validation: position must exist, not be empty, and not be a hash
        if (!position || typeof position !== 'string') {
          console.warn('Filtering out: position is not a string', position, vote);
          return false;
        }
        
        const trimmedPosition = position.trim();
        if (trimmedPosition === '') {
          console.warn('Filtering out: empty position name', vote);
          return false;
        }
        
        // Check if it looks like a hash (32 char hex string)
        const isHash = /^[a-f0-9]{32}$/i.test(trimmedPosition);
        if (isHash) {
          console.warn('Filtering out: position name is a hash', trimmedPosition, vote);
          return false;
        }
        
        // Check if candidate name is valid (allow "Candidate #X" as fallback)
        if (!vote || !vote.candidateName) {
          console.warn('Filtering out: missing candidate name', position, vote);
          return false;
        }
        
        console.log(`✓ Valid vote: "${trimmedPosition}" -> "${vote.candidateName}"`);
        return true;
      });
      
      console.log('Valid votes after filtering:', validVotes.length);
      console.log('Valid vote positions:', validVotes.map(([pos]) => pos));
      
      const sortedVotes = validVotes.sort((a, b) => a[0].localeCompare(b[0]));
      
      if (sortedVotes.length === 0) {
        // Provide more helpful error message
        const debugInfo = `Found ${checkedCheckboxes.length} checked checkbox(es) but 0 valid votes. ` +
                         `Check browser console (F12) for detailed debugging information.`;
        
        summaryHTML = `
          <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-center">
            <p class="text-yellow-800 font-medium mb-2">No valid selections found.</p>
            <p class="text-yellow-700 text-sm mb-2">${debugInfo}</p>
            <p class="text-yellow-600 text-xs mt-2">
              Debug: Checkboxes found: ${checkedCheckboxes.length}, 
              votingData keys: ${Object.keys(votingData).length},
              Valid votes: ${validVotes.length}
            </p>
          </div>
        `;
        
        console.error('❌❌❌ Displaying error message: No valid selections found');
        console.error('Summary:', {
          checkedCheckboxes: checkedCheckboxes.length,
          votingDataKeys: Object.keys(votingData).length,
          validVotes: validVotes.length
        });
      } else {
        // Add summary header
        summaryHTML += `
          <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-blue-800 font-medium text-center">
              You have selected <strong>${sortedVotes.length}</strong> position${sortedVotes.length !== 1 ? 's' : ''}:
            </p>
          </div>
        `;
        
        sortedVotes.forEach(([position, vote], index) => {
          // Ensure we're displaying the actual position name, not a hash
          const displayPosition = vote.positionName || position;
          
          console.log(`Adding to summary ${index + 1}: ${displayPosition} -> ${vote.candidateName}`);
          
          summaryHTML += `
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg border border-slate-200 mb-2 hover:bg-slate-100 transition-colors">
              <div class="flex-1">
                <div class="font-semibold text-slate-800 text-lg mb-1">${displayPosition}</div>
                <div class="text-slate-600">${vote.candidateName}</div>
              </div>
              <div class="text-green-600 ml-4">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
              </div>
            </div>
          `;
        });
      }
      
      console.log('Summary HTML generated with', sortedVotes.length, 'positions');
      summaryContainer.innerHTML = summaryHTML;
      
      // Show review, hide submit
      reviewSection.classList.remove('hidden');
      submitSection.classList.add('hidden');
      
      // Scroll to review section
      reviewSection.scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideVoteReview() {
      const reviewSection = document.getElementById('vote-review');
      const submitSection = document.getElementById('submit-section');
      const finalConfirmCheckbox = document.getElementById('final-confirm-checkbox');
      
      // Reset final confirmation checkbox when going back to edit
      if (finalConfirmCheckbox) {
        finalConfirmCheckbox.checked = false;
        toggleSubmitButton();
      }
      
      reviewSection.classList.add('hidden');
      submitSection.classList.remove('hidden');
      
      // Scroll back to submit section
      submitSection.scrollIntoView({ behavior: 'smooth' });
    }
    
    function saveDraft() {
      // Save current selections to localStorage
      localStorage.setItem('smartvote_draft', JSON.stringify(votingData));
      
      // Show feedback
      const btn = document.getElementById('save-draft-btn');
      const originalText = btn.innerHTML;
      btn.innerHTML = `
        <div class="flex items-center justify-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
          </svg>
          Saved!
        </div>
      `;
      btn.className = 'bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 mr-4 mb-4 sm:mb-0';
      
      setTimeout(() => {
        btn.innerHTML = originalText;
        btn.className = 'bg-amber-500 hover:bg-amber-600 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 transform hover:scale-105 mr-4 mb-4 sm:mb-0';
      }, 2000);
    }
    
    function loadDraft() {
      // Load saved selections from localStorage
      const saved = localStorage.getItem('smartvote_draft');
      if (saved) {
        const savedData = JSON.parse(saved);
        
        Object.entries(savedData).forEach(([position, vote]) => {
          const input = document.querySelector(`input[name="${vote.inputName}"][value="${vote.candidateId}"]`);
          if (input) {
            input.checked = true;
            highlightSelection(input);
          }
        });
        
        updateVoteProgress();
        
        // Show notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
          <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            Draft loaded successfully!
          </div>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
          notification.remove();
        }, 3000);
      }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateVoteProgress();
      loadDraft();
      
      // Restore visual state of checked inputs
      document.querySelectorAll('input[type="checkbox"].candidate-checkbox:checked').forEach(input => {
        persistSelection(input);
        highlightSelection(input);
        updatePositionStatus(input);
      });
      
      // Initialize checkbox visuals
      const voterCheckbox = document.getElementById('voter-confirm-checkbox');
      if (voterCheckbox) updateCheckboxVisual(voterCheckbox);
      const finalCheckbox = document.getElementById('final-confirm-checkbox');
      if (finalCheckbox) updateCheckboxVisual(finalCheckbox);
      
      // Update all position statuses on page load
      updatePositionStatuses();
      
      // Ensure selections persist on page interactions
      document.querySelectorAll('input[type="checkbox"].candidate-checkbox').forEach(input => {
        input.addEventListener('change', function() {
          persistSelection(this);
          setTimeout(() => updatePositionStatus(this), 50);
          // Update review button state when selection changes
          updateVoteProgress();
          toggleReviewButton();
        });
        
        // Update immediately on click
        input.addEventListener('click', function() {
          if (this.checked) {
            persistSelection(this);
            setTimeout(() => updatePositionStatus(this), 50);
          }
        });
        
        // Mouse down for immediate feedback
        input.addEventListener('mousedown', function() {
          if (!this.checked) {
            setTimeout(() => updatePositionStatus(this), 100);
          }
        });
      });
      
      // Add keyboard navigation
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          if (e.target.tagName === 'LABEL') {
            e.preventDefault();
            const input = e.target.querySelector('input') || document.getElementById(e.target.getAttribute('for'));
            if (input) {
              input.checked = true;
              persistSelection(input);
              highlightSelection(input);
              updateVoteProgress();
            }
          }
        }
      });
    });
  </script>
</body>
</html>

