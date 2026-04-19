<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_role('admin');

$security = new SecurityManager($pdo);
$security->setSecurityHeaders();

$message = '';
$messageType = 'success';

// Get current time
$now = date('Y-m-d H:i:s');

// Update election statuses based on current time
// This ensures elections are properly managed
$activeElection = current_active_election($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$security->validateCSRFToken($csrfToken)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'error';
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'manage_elections'], 'CSRF token validation failed for election management');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');
        $selectedPositions = $_POST['positions'] ?? [];
        
        if (empty($title) || empty($start) || empty($end)) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } elseif (empty($selectedPositions)) {
            $message = 'Error: Please select at least one position for the election.';
            $messageType = 'error';
        } elseif ($start >= $end) {
            $message = 'Error: End date must be after start date.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Determine initial status based on current time
                $initialStatus = 'pending';
                if ($now >= $start && $now <= $end) {
                    $initialStatus = 'active';
                } elseif ($now > $end) {
                    $initialStatus = 'expired';
                }
                
                // Create election
                $stmt = $pdo->prepare("INSERT INTO elections (title, description, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$title, $description, $start, $end, $initialStatus]);
                $electionId = $pdo->lastInsertId();
                
                // Add positions to election
                $posStmt = $pdo->prepare("INSERT INTO election_positions (election_id, position_id) VALUES (?, ?)");
                foreach ($selectedPositions as $positionId) {
                    $posStmt->execute([$electionId, (int)$positionId]);
                }
                
                $pdo->commit();
                $message = 'Election created successfully.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error creating election: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
    } elseif ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        
        
        try {
            // Get current time for activation (not cached time)
            $currentTime = date('Y-m-d H:i:s');
            
            // Get election details
            $election = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
            $election->execute([$id]);
            $electionData = $election->fetch(PDO::FETCH_ASSOC);
            
            if (!$electionData) {
                $message = 'Election not found.';
                $messageType = 'error';
            } elseif ($currentTime > $electionData['end_date']) {
                $message = 'Cannot activate election: it has already ended.';
                $messageType = 'error';
            } else {
                $pdo->beginTransaction();
                
                // Close all other active elections
                $pdo->exec("UPDATE elections SET status = 'closed' WHERE status = 'active'");
                
                // Activate the selected election
                $stmt = $pdo->prepare("UPDATE elections SET status = 'active' WHERE id = ?");
                $stmt->execute([$id]);
                
                $pdo->commit();
                
                $startDate = date('M j, Y g:i A', strtotime($electionData['start_date']));
                $endDate = date('M j, Y g:i A', strtotime($electionData['end_date']));
                
                if ($currentTime < $electionData['start_date']) {
                    $message = "Election activated successfully. Voting will begin on {$startDate}.";
                } else {
                    $message = "Election activated successfully. Voting is now open until {$endDate}.";
                }
                $messageType = 'success';
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Error activating election: ' . $e->getMessage();
            $messageType = 'error';
        }
        
    } elseif ($action === 'close') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE elections SET status = 'closed' WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Election closed successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error closing election: ' . $e->getMessage();
            $messageType = 'error';
        }
        
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');
        $selectedPositions = $_POST['positions'] ?? [];
        
        if (empty($title) || empty($start) || empty($end)) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } elseif (empty($selectedPositions)) {
            $message = 'Error: Please select at least one position for the election.';
            $messageType = 'error';
        } elseif ($start >= $end) {
            $message = 'Error: End date must be after start date.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update election details
                $stmt = $pdo->prepare("UPDATE elections SET title = ?, description = ?, start_date = ?, end_date = ? WHERE id = ?");
                $stmt->execute([$title, $description, $start, $end, $id]);
                
                // Update positions
                $pdo->prepare("DELETE FROM election_positions WHERE election_id = ?")->execute([$id]);
                $posStmt = $pdo->prepare("INSERT INTO election_positions (election_id, position_id) VALUES (?, ?)");
                foreach ($selectedPositions as $positionId) {
                    $posStmt->execute([$id, (int)$positionId]);
                }
                
                $pdo->commit();
                $message = 'Election updated successfully.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error updating election: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $pdo->beginTransaction();
            
            // Get election title for confirmation message
            $election = $pdo->prepare("SELECT title FROM elections WHERE id = ?");
            $election->execute([$id]);
            $electionData = $election->fetch(PDO::FETCH_ASSOC);
            
            if ($electionData) {
                // Delete related data
                $pdo->prepare("DELETE FROM election_positions WHERE election_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM votes WHERE election_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM elections WHERE id = ?")->execute([$id]);
                
                $pdo->commit();
                $message = "Election '{$electionData['title']}' deleted successfully.";
                $messageType = 'success';
            } else {
                $pdo->rollBack();
                $message = 'Election not found.';
                $messageType = 'error';
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Error deleting election: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    }
}

// Get elections with their positions
$elections = $pdo->query("
    SELECT e.*, 
           GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', ') as position_names
    FROM elections e 
    LEFT JOIN election_positions ep ON e.id = ep.election_id 
    LEFT JOIN positions p ON ep.position_id = p.id 
    GROUP BY e.id 
    ORDER BY e.created_at DESC
")->fetchAll();

// Get available positions
$positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll();

// Handle edit action
$editElection = null;
$editPositions = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editElection = $pdo->prepare("SELECT * FROM elections WHERE id = ?");
    $editElection->execute([$editId]);
    $editElection = $editElection->fetch(PDO::FETCH_ASSOC);
    
    if ($editElection) {
        $editPositionsStmt = $pdo->prepare("SELECT position_id FROM election_positions WHERE election_id = ?");
        $editPositionsStmt->execute([$editId]);
        $editPositions = $editPositionsStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Function to get status badge HTML
function getStatusBadge($status) {
    switch ($status) {
        case 'active':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Active
            </span>';
        case 'expired':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                Expired
            </span>';
        case 'pending':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
                Pending
            </span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
                Closed
            </span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections - SmartVote</title>
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
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800">Manage Elections</h1>
                <p class="text-slate-600 mt-1">Create, activate, and manage election periods.</p>
                
                <!-- Current Active Election Status -->
                <?php if ($activeElection): ?>
                    <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-green-800">Active Election: <?php echo htmlspecialchars($activeElection['title']); ?></p>
                                <p class="text-xs text-green-600">Ends: <?php echo date('M j, Y g:i A', strtotime($activeElection['end_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-slate-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                            <p class="text-sm font-medium text-slate-800">No active election currently</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg border flex items-center <?php echo $messageType === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
                    <svg class="w-5 h-5 mr-2 <?php echo $messageType === 'error' ? 'text-red-600' : 'text-emerald-600'; ?>" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="<?php echo $messageType === 'error' ? 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z' : 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z'; ?>" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Create/Edit Election Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-slate-800">
                            <?php echo $editElection ? 'Edit Election' : 'Create New Election'; ?>
                        </h2>
                    </div>
                    <?php if ($editElection): ?>
                        <a href="manage_elections.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-slate-600 hover:bg-slate-700 rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editElection ? 'update' : 'create'; ?>">
                    <?php if ($editElection): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$editElection['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Election Title *</label>
                            <input required name="title" value="<?php echo $editElection ? htmlspecialchars($editElection['title']) : ''; ?>" 
                                   placeholder="e.g., Student Council Elections 2024" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                            <textarea name="description" placeholder="Brief description of the election..." rows="3" 
                                      class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"><?php echo $editElection ? htmlspecialchars($editElection['description']) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Start Date & Time *</label>
                            <input required type="datetime-local" name="start_date" 
                                   value="<?php echo $editElection ? date('Y-m-d\TH:i', strtotime($editElection['start_date'])) : ''; ?>" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">End Date & Time *</label>
                            <input required type="datetime-local" name="end_date" 
                                   value="<?php echo $editElection ? date('Y-m-d\TH:i', strtotime($editElection['end_date'])) : ''; ?>" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                    </div>

                    <!-- Position Selection -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-3">Select Positions for this Election *</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            <?php foreach ($positions as $position): ?>
                                <label class="flex items-center p-3 border border-slate-300 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors">
                                    <input type="checkbox" name="positions[]" value="<?php echo (int)$position['id']; ?>" 
                                           <?php echo ($editElection && in_array($position['id'], $editPositions)) ? 'checked' : ''; ?> 
                                           class="mr-3 text-brand focus:ring-brand rounded">
                                    <span class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($position['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($positions)): ?>
                            <p class="text-sm text-slate-500 mt-2">
                                No positions available. <a href="manage_positions.php" class="text-brand hover:underline">Create positions first</a>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <?php echo $editElection ? 'Update Election' : 'Create Election'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Elections List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800">Elections List</h2>
                        <div class="flex items-center text-sm text-slate-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <?php echo count($elections); ?> total elections
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Positions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($elections as $election): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <?php echo (int)$election['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($election['title']); ?></div>
                                            <?php if ($election['description']): ?>
                                                <div class="text-sm text-slate-500 max-w-xs truncate"><?php echo htmlspecialchars($election['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($election['position_names']): ?>
                                            <div class="flex flex-wrap gap-1">
                                                <?php foreach (explode(', ', $election['position_names']) as $position): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <?php echo htmlspecialchars(trim($position)); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-slate-400">No positions</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo getStatusBadge($election['status']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <div class="flex flex-col">
                                            <div class="text-xs">
                                                <div class="font-medium">Start:</div>
                                                <div><?php echo date('M j, Y g:i A', strtotime($election['start_date'])); ?></div>
                                            </div>
                                            <div class="text-xs mt-1">
                                                <div class="font-medium">End:</div>
                                                <div><?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <?php
                                            $status = $election['status'];
                                            $canActivate = ($status === 'pending' || $status === 'closed') && $now < $election['end_date'];
                                            ?>
                                            
                                            <!-- Activate Button -->
                                            <?php if ($canActivate): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$election['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-all duration-200" title="Activate Election">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Close Button -->
                                            <?php if ($status === 'active'): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$election['id']; ?>">
                                                    <input type="hidden" name="action" value="close">
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all duration-200" title="Close Election">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                        Close
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Edit Button -->
                                            <a href="?edit=<?php echo (int)$election['id']; ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-200" title="Edit Election">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            
                                            <!-- Delete Button -->
                                            <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this election? This action cannot be undone and will remove all associated votes and data.')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$election['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all duration-200" title="Delete Election">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
</body>
</html>
