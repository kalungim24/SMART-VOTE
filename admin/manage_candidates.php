<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload_helper.php';
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
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'manage_candidates'], 'CSRF token validation failed for candidate management');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $positionId = (int)($_POST['position_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $symbol = $_FILES['symbol'] ?? null;
        $photo = $_FILES['photo'] ?? null;
        
        if (empty($name) || $positionId === 0) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Create candidate
                $stmt = $pdo->prepare("INSERT INTO candidates (name, position_id, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$name, $positionId, $description]);
                $candidateId = $pdo->lastInsertId();
                
                // Handle symbol upload
                if ($symbol && $symbol['error'] === UPLOAD_ERR_OK) {
                    $symbolResult = upload_candidate_symbol($symbol, $candidateId);
                    if ($symbolResult && is_array($symbolResult) && $symbolResult['success']) {
                        $pdo->prepare("UPDATE candidates SET symbol_path = ? WHERE id = ?")->execute([$symbolResult['url'], $candidateId]);
                    } else {
                        error_log('Symbol upload failed: ' . ($symbolResult['error'] ?? 'Unknown error'));
                    }
                } else if ($symbol && $symbol['error'] !== UPLOAD_ERR_NO_FILE) {
                    error_log('Symbol upload error code: ' . $symbol['error']);
                }

                // Handle photo upload
                if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
                    $photoResult = upload_candidate_photo($photo, $candidateId);
                    if ($photoResult && is_array($photoResult) && $photoResult['success']) {
                        $pdo->prepare("UPDATE candidates SET photo_path = ? WHERE id = ?")->execute([$photoResult['url'], $candidateId]);
                    } else {
                        error_log('Photo upload failed: ' . ($photoResult['error'] ?? 'Unknown error'));
                    }
                } else if ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
                    error_log('Photo upload error code: ' . $photo['error']);
                }
                
                $pdo->commit();
                $message = 'Candidate created successfully.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error creating candidate. Please try again.';
                $messageType = 'error';
                error_log("Candidate creation error: " . $e->getMessage());
            }
        }
        
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $positionId = (int)($_POST['position_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $symbol = $_FILES['symbol'] ?? null;
        $photo = $_FILES['photo'] ?? null;
        
        if (empty($name) || $positionId === 0) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Update candidate
                $stmt = $pdo->prepare("UPDATE candidates SET name = ?, position_id = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $positionId, $description, $id]);
                
                // Handle symbol upload
                if ($symbol && $symbol['error'] === UPLOAD_ERR_OK) {
                    $symbolResult = upload_candidate_symbol($symbol, $id);
                    if ($symbolResult && is_array($symbolResult) && $symbolResult['success']) {
                        $pdo->prepare("UPDATE candidates SET symbol_path = ? WHERE id = ?")->execute([$symbolResult['url'], $id]);
                    } else {
                        error_log('Symbol upload failed: ' . ($symbolResult['error'] ?? 'Unknown error'));
                    }
                } else if ($symbol && $symbol['error'] !== UPLOAD_ERR_NO_FILE) {
                    error_log('Symbol upload error code: ' . $symbol['error']);
                }

                // Handle photo upload
                if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
                    $photoResult = upload_candidate_photo($photo, $id);
                    if ($photoResult && is_array($photoResult) && $photoResult['success']) {
                        $pdo->prepare("UPDATE candidates SET photo_path = ? WHERE id = ?")->execute([$photoResult['url'], $id]);
                    } else {
                        error_log('Photo upload failed: ' . ($photoResult['error'] ?? 'Unknown error'));
                    }
                } else if ($photo && $photo['error'] !== UPLOAD_ERR_NO_FILE) {
                    error_log('Photo upload error code: ' . $photo['error']);
                }
                
                $pdo->commit();
                $message = 'Candidate updated successfully.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error updating candidate. Please try again.';
                $messageType = 'error';
                error_log("Candidate update error: " . $e->getMessage());
            }
        }
        
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            // Get candidate info for file cleanup
            $candidate = $pdo->prepare("SELECT symbol_path, photo_path FROM candidates WHERE id = ?");
            $candidate->execute([$id]);
            $candidateData = $candidate->fetch(PDO::FETCH_ASSOC);
            
            // Delete candidate
            $pdo->prepare("DELETE FROM candidates WHERE id = ?")->execute([$id]);
            
            // Clean up files - validate and sanitize file paths
            if ($candidateData) {
                if (!empty($candidateData['symbol_path'])) {
                    $symbolPath = realpath(__DIR__ . '/../' . $candidateData['symbol_path']);
                    // Ensure path is within uploads directory (security check)
                    if ($symbolPath && strpos($symbolPath, realpath(__DIR__ . '/../uploads')) === 0 && file_exists($symbolPath)) {
                        unlink($symbolPath);
                    }
                }
                if (!empty($candidateData['photo_path'])) {
                    $photoPath = realpath(__DIR__ . '/../' . $candidateData['photo_path']);
                    // Ensure path is within uploads directory (security check)
                    if ($photoPath && strpos($photoPath, realpath(__DIR__ . '/../uploads')) === 0 && file_exists($photoPath)) {
                        unlink($photoPath);
                    }
                }
            }
            
            $message = 'Candidate deleted successfully.';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error deleting candidate. Please try again.';
            $messageType = 'error';
            error_log("Candidate deletion error: " . $e->getMessage());
        }
    }
    }
}

// Get candidates with their positions
$candidates = $pdo->query("
    SELECT c.*, 
           COALESCE(p.name, c.position) as position_name 
    FROM candidates c 
    LEFT JOIN positions p ON c.position_id = p.id 
    ORDER BY c.created_at DESC
")->fetchAll();

// Get available positions
$positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll();

// Handle edit action
$editCandidate = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editCandidate = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
    $editCandidate->execute([$editId]);
    $editCandidate = $editCandidate->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates - SmartVote</title>
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
                <h1 class="text-3xl font-bold text-slate-800">Manage Candidates</h1>
                <p class="text-slate-600 mt-1">Add, edit, and manage election candidates.</p>
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

            <!-- Create/Edit Candidate Form -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-slate-800">
                            <?php echo $editCandidate ? 'Edit Candidate' : 'Add New Candidate'; ?>
                        </h2>
                    </div>
                    <?php if ($editCandidate): ?>
                        <a href="manage_candidates.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-slate-600 hover:bg-slate-700 rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>

                <form method="post" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editCandidate ? 'update' : 'create'; ?>">
                    <?php if ($editCandidate): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$editCandidate['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Candidate Name *</label>
                            <input required name="name" value="<?php echo $editCandidate ? htmlspecialchars($editCandidate['name']) : ''; ?>" 
                                   placeholder="Enter candidate name" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Position *</label>
                            <select required name="position_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                <option value="">Select Position</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?php echo (int)$position['id']; ?>" 
                                            <?php echo ($editCandidate && ($editCandidate['position_id'] == $position['id'] || $editCandidate['position'] == $position['name'])) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($position['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea name="description" placeholder="Brief description of the candidate..." rows="3" 
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"><?php echo $editCandidate ? htmlspecialchars($editCandidate['description']) : ''; ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Symbol/Logo</label>
                            <input type="file" name="symbol" accept="image/*" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                            <?php if ($editCandidate && $editCandidate['symbol_path']): ?>
                                <p class="text-xs text-slate-500 mt-1">Current: <?php echo basename($editCandidate['symbol_path']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Photo</label>
                            <input type="file" name="photo" accept="image/*" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                            <?php if ($editCandidate && $editCandidate['photo_path']): ?>
                                <p class="text-xs text-slate-500 mt-1">Current: <?php echo basename($editCandidate['photo_path']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <?php echo $editCandidate ? 'Update Candidate' : 'Add Candidate'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Candidates List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800">Candidates List</h2>
                        <div class="flex items-center text-sm text-slate-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <?php echo count($candidates); ?> total candidates
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Photo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Symbol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($candidates as $candidate): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($candidate['photo_path'] && file_exists($candidate['photo_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($candidate['photo_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($candidate['name']); ?>" 
                                                 class="w-12 h-12 rounded-full object-cover">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-slate-200 rounded-full flex items-center justify-center">
                                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($candidate['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($candidate['position_name'] ?? 'No Position'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($candidate['symbol_path'] && file_exists(__DIR__ . '/../' . $candidate['symbol_path'])): ?>
                                            <img src="../<?php echo htmlspecialchars($candidate['symbol_path']); ?>" 
                                                 alt="Symbol" 
                                                 class="w-8 h-8 object-contain">
                                        <?php else: ?>
                                            <span class="text-slate-400 text-sm">No symbol</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-slate-900 max-w-xs truncate">
                                            <?php echo htmlspecialchars($candidate['description'] ?: 'No description'); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="?edit=<?php echo (int)$candidate['id']; ?>" 
                                               class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-200" 
                                               title="Edit Candidate">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            
                                            <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this candidate? This action cannot be undone.')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$candidate['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all duration-200" title="Delete Candidate">
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