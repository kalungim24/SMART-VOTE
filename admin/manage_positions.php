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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$security->validateCSRFToken($csrfToken)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'error';
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'manage_positions'], 'CSRF token validation failed for position management');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $maxCandidates = (int)($_POST['max_candidates'] ?? 1);
        
        if (empty($name)) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                // Check if position name already exists
                $existingPosition = $pdo->prepare("SELECT id FROM positions WHERE name = ?");
                $existingPosition->execute([$name]);
                
                if ($existingPosition->fetch()) {
                    $message = 'Error: Position name already exists.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO positions (name, description, max_candidates, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$name, $description, $maxCandidates]);
                    
                    $message = 'Position created successfully.';
                    $messageType = 'success';
                }
                
            } catch (Exception $e) {
                $message = 'Error creating position: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $maxCandidates = (int)($_POST['max_candidates'] ?? 1);
        
        if (empty($name)) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                // Check if position name already exists (excluding current position)
                $existingPosition = $pdo->prepare("SELECT id FROM positions WHERE name = ? AND id != ?");
                $existingPosition->execute([$name, $id]);
                
                if ($existingPosition->fetch()) {
                    $message = 'Error: Position name already exists.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("UPDATE positions SET name = ?, description = ?, max_candidates = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $maxCandidates, $id]);
                    
                    $message = 'Position updated successfully.';
                    $messageType = 'success';
                }
                
            } catch (Exception $e) {
                $message = 'Error updating position: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            // Check if position is used in any elections
            $electionCheck = $pdo->prepare("SELECT COUNT(*) FROM election_positions WHERE position_id = ?");
            $electionCheck->execute([$id]);
            $electionCount = $electionCheck->fetchColumn();
            
            if ($electionCount > 0) {
                $message = 'Error: Cannot delete position. It is used in ' . $electionCount . ' election(s).';
                $messageType = 'error';
            } else {
                // Check if position has candidates
                $candidateCheck = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE position_id = ?");
                $candidateCheck->execute([$id]);
                $candidateCount = $candidateCheck->fetchColumn();
                
                if ($candidateCount > 0) {
                    $message = 'Error: Cannot delete position. It has ' . $candidateCount . ' candidate(s).';
                    $messageType = 'error';
                } else {
                    $pdo->prepare("DELETE FROM positions WHERE id = ?")->execute([$id]);
                    $message = 'Position deleted successfully.';
                    $messageType = 'success';
                }
            }
            
        } catch (Exception $e) {
            $message = 'Error deleting position: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    }
}

// Get positions
$positions = $pdo->query("
    SELECT p.*, 
           COUNT(c.id) as candidate_count,
           COUNT(ep.election_id) as election_count
    FROM positions p 
    LEFT JOIN candidates c ON p.id = c.position_id 
    LEFT JOIN election_positions ep ON p.id = ep.position_id 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
")->fetchAll();

// Handle edit action
$editPosition = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editPosition = $pdo->prepare("SELECT * FROM positions WHERE id = ?");
    $editPosition->execute([$editId]);
    $editPosition = $editPosition->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - SmartVote</title>
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
                <h1 class="text-3xl font-bold text-slate-800">Manage Positions</h1>
                <p class="text-slate-600 mt-1">Create and manage election positions.</p>
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

            <!-- Create/Edit Position Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-slate-800">
                            <?php echo $editPosition ? 'Edit Position' : 'Create New Position'; ?>
                        </h2>
                    </div>
                    <?php if ($editPosition): ?>
                        <a href="manage_positions.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-slate-600 hover:bg-slate-700 rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editPosition ? 'update' : 'create'; ?>">
                    <?php if ($editPosition): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$editPosition['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Position Name *</label>
                            <input required name="name" value="<?php echo $editPosition ? htmlspecialchars($editPosition['name']) : ''; ?>" 
                                   placeholder="e.g., President, Secretary, Treasurer" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Max Candidates</label>
                            <input type="number" name="max_candidates" min="1" max="10" 
                                   value="<?php echo $editPosition ? (int)($editPosition['max_candidates'] ?? 1) : 1; ?>" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea name="description" placeholder="Brief description of the position..." rows="3" 
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"><?php echo $editPosition ? htmlspecialchars($editPosition['description'] ?? '') : ''; ?></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <?php echo $editPosition ? 'Update Position' : 'Create Position'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Positions List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800">Positions List</h2>
                        <div class="flex items-center text-sm text-slate-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <?php echo count($positions); ?> total positions
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Position Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Max Candidates</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Candidates</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Elections</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($positions as $position): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <?php echo (int)$position['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($position['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-slate-600 max-w-xs truncate">
                                            <?php echo htmlspecialchars($position['description'] ?? 'No description'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo (int)($position['max_candidates'] ?? 1); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo (int)$position['candidate_count'] > 0 ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800'; ?>">
                                            <?php echo (int)$position['candidate_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo (int)$position['election_count'] > 0 ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-800'; ?>">
                                            <?php echo (int)$position['election_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="?edit=<?php echo (int)$position['id']; ?>" 
                                               class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-200" 
                                               title="Edit Position">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            
                                            <?php if ((int)$position['candidate_count'] === 0 && (int)$position['election_count'] === 0): ?>
                                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this position? This action cannot be undone.')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$position['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all duration-200" title="Delete Position">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-400 bg-slate-100 rounded-lg" title="Cannot delete - has candidates or elections">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                    Protected
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Positions</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo count($positions); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Positions with Candidates</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo count(array_filter($positions, fn($p) => (int)$p['candidate_count'] > 0)); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Positions in Elections</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo count(array_filter($positions, fn($p) => (int)$p['election_count'] > 0)); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
</body>
</html>