<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/election_manager.php';
require_role('admin');

$message = '';
$messageType = 'success';

// Initialize election manager
$electionManager = new ElectionManager($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        $result = $electionManager->activateElection($id);
        
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
    } elseif ($action === 'close') {
        $id = (int)($_POST['id'] ?? 0);
        $result = $electionManager->closeElection($id);
        
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
    } elseif ($action === 'force_status') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        
        if (in_array($newStatus, ['active', 'pending', 'closed', 'expired'])) {
            try {
                $stmt = $pdo->prepare("UPDATE elections SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $id]);
                $message = "Election status changed to {$newStatus}";
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error changing status: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get elections with status information
$elections = $electionManager->getAllElectionsWithStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Status Control - <?php echo h(get_system_name($pdo)); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
            <div class="mb-8">
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Election Status Control</h1>
                <p class="text-slate-600 mt-1">Direct control over election statuses with detailed information.</p>
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

            <!-- Elections Status Control -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-xl font-semibold text-slate-800">Election Status Control Panel</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Current Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Time-Based Status</th>
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
                                        <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($election['title']); ?></div>
                                        <?php if ($election['description']): ?>
                                            <div class="text-sm text-slate-500 max-w-xs truncate"><?php echo htmlspecialchars($election['description']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php 
                                            switch($election['current_status']) {
                                                case 'active': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'expired': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-slate-100 text-slate-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($election['current_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-600">
                                            <?php echo ucfirst($election['time_based_status']); ?>
                                        </span>
                                        <?php if ($election['current_status'] !== $election['time_based_status']): ?>
                                            <span class="text-xs text-orange-600 ml-1">⚠️</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <div class="text-xs">
                                            <div>Start: <?php echo date('M j, Y g:i A', strtotime($election['start_date'])); ?></div>
                                            <div>End: <?php echo date('M j, Y g:i A', strtotime($election['end_date'])); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <!-- Quick Actions -->
                                            <?php if ($election['can_activate']): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo (int)$election['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded">
                                                        Activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($election['can_close']): ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="id" value="<?php echo (int)$election['id']; ?>">
                                                    <input type="hidden" name="action" value="close">
                                                    <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded">
                                                        Close
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <!-- Force Status Change -->
                                            <form method="post" class="inline">
                                                <input type="hidden" name="id" value="<?php echo (int)$election['id']; ?>">
                                                <input type="hidden" name="action" value="force_status">
                                                <select name="new_status" class="text-xs border border-slate-300 rounded px-1 py-1" onchange="this.form.submit()">
                                                    <option value="">Force Status</option>
                                                    <option value="pending" <?php echo $election['current_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="active" <?php echo $election['current_status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="closed" <?php echo $election['current_status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                    <option value="expired" <?php echo $election['current_status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                                </select>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Status Summary -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Active</p>
                            <p class="text-2xl font-bold text-slate-900">
                                <?php echo count(array_filter($elections, fn($e) => $e['current_status'] === 'active')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Pending</p>
                            <p class="text-2xl font-bold text-slate-900">
                                <?php echo count(array_filter($elections, fn($e) => $e['current_status'] === 'pending')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Expired</p>
                            <p class="text-2xl font-bold text-slate-900">
                                <?php echo count(array_filter($elections, fn($e) => $e['current_status'] === 'expired')); ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-slate-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Closed</p>
                            <p class="text-2xl font-bold text-slate-900">
                                <?php echo count(array_filter($elections, fn($e) => $e['current_status'] === 'closed')); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
</body>
</html>
