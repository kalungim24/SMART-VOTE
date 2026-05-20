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
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'manage_voters'], 'CSRF token validation failed for voter management');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $voterId = trim($_POST['voter_id'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if (empty($name) || empty($voterId)) {
                $message = 'Error: Please fill in all required fields.';
                $messageType = 'error';
            } else {
                try {
                    // Check if voter ID already exists
                    $existingVoter = $pdo->prepare("SELECT id FROM voters WHERE voter_id = ?");
                    $existingVoter->execute([$voterId]);
                    
                    if ($existingVoter->fetch()) {
                        $message = 'Error: Voter ID already exists.';
                        $messageType = 'error';
                    } else {
                        // Set default password for new voters
                        $tempPassword = '12345678';
                        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO voters (name, voter_id, email, phone, address, password, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$name, $voterId, $email, $phone, $address, $hashedPassword]);
                        
                        $message = 'Voter registered successfully. Default password: ' . htmlspecialchars($tempPassword) . ' (Please change on first login)';
                        $messageType = 'success';
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error registering voter. Please try again.';
                    $messageType = 'error';
                    error_log("Voter registration error: " . $e->getMessage());
                }
            }
            
        } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $voterId = trim($_POST['voter_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($name) || empty($voterId)) {
            $message = 'Error: Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                // Check if voter ID already exists (excluding current voter)
                $existingVoter = $pdo->prepare("SELECT id FROM voters WHERE voter_id = ? AND id != ?");
                $existingVoter->execute([$voterId, $id]);
                
                if ($existingVoter->fetch()) {
                    $message = 'Error: Voter ID already exists.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("UPDATE voters SET name = ?, voter_id = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$name, $voterId, $email, $phone, $address, $id]);
                    
                    $message = 'Voter updated successfully.';
                    $messageType = 'success';
                }
                
            } catch (Exception $e) {
                $message = 'Error updating voter. Please try again.';
                $messageType = 'error';
                error_log("Voter update error: " . $e->getMessage());
            }
        }
        
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $pdo->prepare("DELETE FROM voters WHERE id = ?")->execute([$id]);
            $message = 'Voter deleted successfully.';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error deleting voter. Please try again.';
            $messageType = 'error';
            error_log("Voter deletion error: " . $e->getMessage());
        }
    } elseif ($action === 'bulk_import') {
        $csvData = $_POST['csv_data'] ?? '';
        
        if (empty($csvData)) {
            $message = 'Error: Please provide CSV data.';
            $messageType = 'error';
        } else {
            try {
                $lines = explode("\n", trim($csvData));
                $imported = 0;
                $errors = [];
                
                $pdo->beginTransaction();
                
                foreach ($lines as $lineNumber => $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $data = str_getcsv($line);
                    if (count($data) < 2) {
                        $errors[] = "Line " . ($lineNumber + 1) . ": Insufficient data";
                        continue;
                    }
                    
                    $name = trim($data[0]);
                    $voterId = trim($data[1]);
                    $email = isset($data[2]) ? trim($data[2]) : '';
                    $phone = isset($data[3]) ? trim($data[3]) : '';
                    $address = isset($data[4]) ? trim($data[4]) : '';
                    
                    if (empty($name) || empty($voterId)) {
                        $errors[] = "Line " . ($lineNumber + 1) . ": Name and Voter ID required";
                        continue;
                    }
                    
                    // Check if voter ID already exists
                    $existingVoter = $pdo->prepare("SELECT id FROM voters WHERE voter_id = ?");
                    $existingVoter->execute([$voterId]);
                    
                    if ($existingVoter->fetch()) {
                        $errors[] = "Line " . ($lineNumber + 1) . ": Voter ID already exists";
                        continue;
                    }
                    
                    // Set default password for imported voters
                    $tempPassword = '12345678';
                    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO voters (name, voter_id, email, phone, address, password, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$name, $voterId, $email, $phone, $address, $hashedPassword]);
                    $imported++;
                }
                
                $pdo->commit();
                
                if ($imported > 0) {
                    $message = "Successfully imported {$imported} voter(s). Default password for all voters: 12345678 (Please change on first login).";
                    if (!empty($errors)) {
                        $message .= " Errors: " . implode(', ', array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $message .= " and " . (count($errors) - 5) . " more.";
                        }
                    }
                    $messageType = 'success';
                } else {
                    $message = 'No voters imported. ' . implode(', ', $errors);
                    $messageType = 'error';
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error importing voters: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    }
}

// Get voters with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$totalVoters = $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$totalPages = ceil($totalVoters / $limit);

$voters = $pdo->prepare("
    SELECT * FROM voters 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$voters->execute([$limit, $offset]);
$voters = $voters->fetchAll();

// Handle edit action
$editVoter = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editVoter = $pdo->prepare("SELECT * FROM voters WHERE id = ?");
    $editVoter->execute([$editId]);
    $editVoter = $editVoter->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voters - <?php echo h(get_system_name($pdo)); ?></title>
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
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Manage Voters</h1>
                <p class="text-sm lg:text-base text-slate-600 mt-1">Register, edit, and manage voter information.</p>
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

            <!-- Create/Edit Voter Form -->
            <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 mb-4 lg:mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-slate-800">
                            <?php echo $editVoter ? 'Edit Voter' : 'Register New Voter'; ?>
                        </h2>
                    </div>
                    <?php if ($editVoter): ?>
                        <a href="manage_voters.php" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-slate-600 hover:bg-slate-700 rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editVoter ? 'update' : 'create'; ?>">
                    <?php if ($editVoter): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$editVoter['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
                            <input required name="name" value="<?php echo $editVoter ? htmlspecialchars($editVoter['name']) : ''; ?>" 
                                   placeholder="Enter full name" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Voter ID *</label>
                            <input required name="voter_id" value="<?php echo $editVoter ? htmlspecialchars($editVoter['voter_id']) : ''; ?>" 
                                   placeholder="Enter unique voter ID" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" name="email" value="<?php echo $editVoter ? htmlspecialchars($editVoter['email'] ?? '') : ''; ?>" 
                                   placeholder="Enter email address" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                            <input type="tel" name="phone" value="<?php echo $editVoter ? htmlspecialchars($editVoter['phone'] ?? '') : ''; ?>" 
                                   placeholder="Enter phone number" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <textarea name="address" placeholder="Enter address" rows="2" 
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"><?php echo $editVoter ? htmlspecialchars($editVoter['address'] ?? '') : ''; ?></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <?php echo $editVoter ? 'Update Voter' : 'Register Voter'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bulk Import -->
            <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 mb-4 lg:mb-8">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-slate-800">Bulk Import Voters</h2>
                </div>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="bulk_import">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">CSV Data</label>
                        <textarea name="csv_data" rows="6" 
                                  placeholder="Enter CSV data (Name, Voter ID, Email, Phone, Address)&#10;John Doe, VTR001, john@example.com, +1234567890, 123 Main St&#10;Jane Smith, VTR002, jane@example.com, +1234567891, 456 Oak Ave"
                                  class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent font-mono text-sm"></textarea>
                        <p class="text-xs text-slate-500 mt-1">Format: Name, Voter ID, Email, Phone, Address (one per line)</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                            </svg>
                            Import Voters
                        </button>
                    </div>
                </form>
            </div>

            <!-- Voters List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800">Voters List</h2>
                        <div class="flex items-center text-sm text-slate-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <?php echo number_format($totalVoters); ?> total voters
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Voter ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Registered</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($voters as $voter): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <?php echo (int)$voter['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($voter['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($voter['voter_id']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?php echo htmlspecialchars($voter['email'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?php echo htmlspecialchars($voter['phone'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?php echo date('M j, Y', strtotime($voter['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="?edit=<?php echo (int)$voter['id']; ?>" 
                                               class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-200" 
                                               title="Edit Voter">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            
                                            <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this voter? This action cannot be undone.')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$voter['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all duration-200" title="Delete Voter">
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

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-slate-700">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalVoters); ?> of <?php echo number_format($totalVoters); ?> voters
                            </div>
                            <div class="flex items-center space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-2 text-sm font-medium text-slate-500 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-white bg-blue-600' : 'text-slate-500 bg-white border border-slate-300 hover:bg-slate-50'; ?> rounded-lg">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-2 text-sm font-medium text-slate-500 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
</body>
</html>