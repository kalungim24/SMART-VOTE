<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/election_manager.php';
require_role('admin');

// Initialize election manager
$electionManager = new ElectionManager($pdo);

// Get statistics
$stats = [];

// Total voters
$stats['total_voters'] = $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();

// Total candidates
$stats['total_candidates'] = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();

// Total positions
$stats['total_positions'] = $pdo->query("SELECT COUNT(*) FROM positions")->fetchColumn();

// Total elections
$stats['total_elections'] = $pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();

// Active election
$activeElection = $electionManager->getActiveElection();

// Recent elections
$recentElections = $pdo->query("
    SELECT e.*, 
           GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as position_names
    FROM elections e 
    LEFT JOIN election_positions ep ON e.id = ep.election_id 
    LEFT JOIN positions p ON ep.position_id = p.id 
    GROUP BY e.id 
    ORDER BY e.created_at DESC 
    LIMIT 5
")->fetchAll();

// Recent voters
$recentVoters = $pdo->query("
    SELECT * FROM voters 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Recent candidates
$recentCandidates = $pdo->query("
    SELECT c.*, 
           COALESCE(p.name, c.position) as position_name 
    FROM candidates c 
    LEFT JOIN positions p ON c.position_id = p.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
")->fetchAll();

// Vote statistics for active election
$voteStats = [];
if ($activeElection) {
    // Try to get votes for the active election, fallback to all votes if election_id is null
    $voteQuery = "SELECT COUNT(*) FROM votes WHERE election_id = ?";
    $voteStmt = $pdo->prepare($voteQuery);
    $voteStmt->execute([$activeElection['id']]);
    $voteStats['total_votes'] = $voteStmt->fetchColumn();
    
    // If no votes found with election_id, try to get all votes (for backward compatibility)
    if ($voteStats['total_votes'] == 0) {
        $voteStats['total_votes'] = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
    }
    
    $voteStats['voter_turnout'] = $stats['total_voters'] > 0 ? 
        round(($voteStats['total_votes'] / $stats['total_voters']) * 100, 1) : 0;
}

// Function to get status badge
function getStatusBadge($status) {
    switch ($status) {
        case 'active':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>';
        case 'pending':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>';
        case 'expired':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Expired</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Closed</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo h(get_system_name($pdo)); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#0ea5e9'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
            <!-- Header -->
            <div class="mb-4 lg:mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Admin Dashboard</h1>
                <p class="text-sm lg:text-base text-slate-600 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>! Here's your system overview.</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Voters -->
                <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 border-l-4 border-blue-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Voters</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo number_format($stats['total_voters']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Total Candidates -->
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Candidates</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo number_format($stats['total_candidates']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Total Positions -->
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Positions</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo number_format($stats['total_positions']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Total Elections -->
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Elections</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo number_format($stats['total_elections']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Election Status -->
            <?php if ($activeElection): ?>
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl shadow-lg p-6 mb-8">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-green-800">Active Election</h3>
                                <p class="text-green-700"><?php echo htmlspecialchars($activeElection['title']); ?></p>
                                <p class="text-sm text-green-600">
                                    Ends: <?php echo date('M j, Y g:i A', strtotime($activeElection['end_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <?php if (isset($voteStats['total_votes'])): ?>
                                <p class="text-2xl font-bold text-green-800"><?php echo number_format($voteStats['total_votes']); ?></p>
                                <p class="text-sm text-green-600">Total Votes</p>
                                <p class="text-xs text-green-500"><?php echo $voteStats['voter_turnout']; ?>% Turnout</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-slate-50 border border-slate-200 rounded-xl shadow-lg p-6 mb-8">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-slate-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">No Active Election</h3>
                            <p class="text-slate-600">Create and activate an election to begin voting.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Recent Elections -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-slate-800">Recent Elections</h2>
                            <a href="manage_elections.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View All</a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recentElections)): ?>
                            <p class="text-slate-500 text-center py-4">No elections created yet.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recentElections as $election): ?>
                                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                                        <div>
                                            <h3 class="font-medium text-slate-900"><?php echo htmlspecialchars($election['title']); ?></h3>
                                            <p class="text-sm text-slate-600"><?php echo date('M j, Y', strtotime($election['created_at'])); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <?php echo getStatusBadge($election['status']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Voters -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-slate-800">Recent Voters</h2>
                            <a href="manage_voters.php" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View All</a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($recentVoters)): ?>
                            <p class="text-slate-500 text-center py-4">No voters registered yet.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recentVoters as $voter): ?>
                                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                                        <div>
                                            <h3 class="font-medium text-slate-900"><?php echo htmlspecialchars($voter['name']); ?></h3>
                                            <p class="text-sm text-slate-600">ID: <?php echo htmlspecialchars($voter['voter_id']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-slate-500"><?php echo date('M j', strtotime($voter['created_at'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mt-8 bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="manage_elections.php" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="font-medium text-blue-800">Create Election</span>
                    </a>
                    
                    <a href="manage_candidates.php" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="font-medium text-green-800">Add Candidate</span>
                    </a>
                    
                    <a href="manage_voters.php" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-purple-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        <span class="font-medium text-purple-800">Register Voter</span>
                    </a>
                    
                    <a href="view_results.php" class="flex items-center p-4 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6 text-orange-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="font-medium text-orange-800">View Results</span>
                    </a>
                </div>
            </div>
        </main>
</body>
</html>