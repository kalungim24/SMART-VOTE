<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
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
    }
}

// Get elections with detailed status information
$elections = $electionManager->getAllElectionsWithStatus();

// Get available positions
$positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll();

// Function to get status badge HTML with enhanced information
function getEnhancedStatusBadge($election) {
    $status = $election['current_status'];
    $isManuallyActivated = $election['is_manually_activated'] ?? false;
    $timeBasedStatus = $election['time_based_status'] ?? $status;
    
    $badgeClass = '';
    $badgeText = '';
    $icon = '';
    
    switch ($status) {
        case 'active':
            $badgeClass = 'bg-green-100 text-green-800';
            $badgeText = 'Active';
            $icon = 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z';
            
            if ($isManuallyActivated) {
                $badgeText = 'Active (Manual)';
                $badgeClass = 'bg-blue-100 text-blue-800';
            }
            break;
        case 'expired':
            $badgeClass = 'bg-red-100 text-red-800';
            $badgeText = 'Expired';
            $icon = 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z';
            break;
        case 'pending':
            $badgeClass = 'bg-yellow-100 text-yellow-800';
            $badgeText = 'Pending';
            $icon = 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z';
            break;
        default:
            $badgeClass = 'bg-slate-100 text-slate-800';
            $badgeText = 'Closed';
            $icon = 'M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z';
    }
    
    $statusMismatch = $status !== $timeBasedStatus;
    $mismatchIndicator = $statusMismatch ? ' ⚠️' : '';
    
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $badgeClass . '">
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="' . $icon . '" clip-rule="evenodd"></path>
        </svg>
        ' . $badgeText . $mismatchIndicator . '
    </span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Election Management - <?php echo h(get_system_name($pdo)); ?></title>
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
                <h1 class="text-3xl font-bold text-slate-800">Enhanced Election Management</h1>
                <p class="text-slate-600 mt-1">Advanced election status management with detailed controls.</p>
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

            <!-- Elections List with Enhanced Status -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800">Elections with Status Details</h2>
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Current Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Time-Based Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Time Remaining</th>
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
                                        <?php echo getEnhancedStatusBadge($election); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-slate-600">
                                            <?php echo ucfirst($election['time_based_status']); ?>
                                        </span>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?php if ($election['time_remaining']): ?>
                                            <div class="text-sm">
                                                <div class="font-medium"><?php echo $election['time_remaining']['formatted']; ?></div>
                                                <div class="text-xs text-slate-500">
                                                    <?php echo $election['time_remaining']['total_seconds']; ?> seconds
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-400">Ended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <!-- Activate Button -->
                                            <?php if ($election['can_activate']): ?>
                                                <form method="post" class="inline">
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
                                            <?php if ($election['can_close']): ?>
                                                <form method="post" class="inline">
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
                                            
                                            <!-- Status Info -->
                                            <?php if ($election['is_manually_activated']): ?>
                                                <span class="text-xs text-blue-600 font-medium">Manual</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Status Legend -->
            <div class="mt-6 bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-slate-800 mb-4">Status Legend</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">Active</span>
                            <span class="text-sm text-slate-600">Election is currently active and accepting votes</span>
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">Active (Manual)</span>
                            <span class="text-sm text-slate-600">Manually activated before start time</span>
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">Pending</span>
                            <span class="text-sm text-slate-600">Waiting for start time</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">Expired</span>
                            <span class="text-sm text-slate-600">Past end time</span>
                        </div>
                        <div class="flex items-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 mr-2">Closed</span>
                            <span class="text-sm text-slate-600">Manually closed</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-slate-600">⚠️</span>
                            <span class="text-sm text-slate-600 ml-1">Status differs from time-based calculation</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
</body>
</html>
