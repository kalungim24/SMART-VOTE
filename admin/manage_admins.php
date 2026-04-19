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
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'manage_admins'], 'CSRF token validation failed for admin management');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $fullname = trim($_POST['fullname'] ?? '');
            
            if (empty($username) || empty($password) || empty($fullname)) {
                $message = 'Error: Please fill in all required fields.';
                $messageType = 'error';
            } elseif (strlen($password) < 8) {
                $message = 'Error: Password must be at least 8 characters long.';
                $messageType = 'error';
            } else {
                try {
                    // Check if username already exists
                    $existingAdmin = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                    $existingAdmin->execute([$username]);
                    
                    if ($existingAdmin->fetch()) {
                        $message = 'Error: Username already exists.';
                        $messageType = 'error';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO admins (username, password, fullname, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$username, $hashedPassword, $fullname]);
                        
                        $message = 'Admin user created successfully.';
                        $messageType = 'success';
                        $security->logSecurityEvent('admin_created', 'medium', ['username' => $username, 'created_by' => $_SESSION['username'] ?? 'unknown'], 'New admin user created');
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error creating admin user. Please try again.';
                    $messageType = 'error';
                    error_log("Admin creation error: " . $e->getMessage());
                }
            }
            
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $fullname = trim($_POST['fullname'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($fullname)) {
                $message = 'Error: Please fill in all required fields.';
                $messageType = 'error';
            } else {
                try {
                    // Check if username already exists (excluding current admin)
                    $existingAdmin = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
                    $existingAdmin->execute([$username, $id]);
                    
                    if ($existingAdmin->fetch()) {
                        $message = 'Error: Username already exists.';
                        $messageType = 'error';
                    } else {
                        if (!empty($password)) {
                            if (strlen($password) < 8) {
                                $message = 'Error: Password must be at least 8 characters long.';
                                $messageType = 'error';
                            } else {
                                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("UPDATE admins SET username = ?, fullname = ?, password = ? WHERE id = ?");
                                $stmt->execute([$username, $fullname, $hashedPassword, $id]);
                                
                                $message = 'Admin user updated successfully (including password).';
                                $messageType = 'success';
                                $security->logSecurityEvent('admin_updated', 'medium', ['admin_id' => $id, 'updated_by' => $_SESSION['username'] ?? 'unknown'], 'Admin user updated');
                            }
                        } else {
                            $stmt = $pdo->prepare("UPDATE admins SET username = ?, fullname = ? WHERE id = ?");
                            $stmt->execute([$username, $fullname, $id]);
                            
                            $message = 'Admin user updated successfully.';
                            $messageType = 'success';
                            $security->logSecurityEvent('admin_updated', 'medium', ['admin_id' => $id, 'updated_by' => $_SESSION['username'] ?? 'unknown'], 'Admin user updated');
                        }
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error updating admin user. Please try again.';
                    $messageType = 'error';
                    error_log("Admin update error: " . $e->getMessage());
                }
            }
            
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            // Prevent deleting yourself
            if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
                $message = 'Error: You cannot delete your own account.';
                $messageType = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Admin user deleted successfully.';
                        $messageType = 'success';
                        $security->logSecurityEvent('admin_deleted', 'medium', ['admin_id' => $id, 'deleted_by' => $_SESSION['username'] ?? 'unknown'], 'Admin user deleted');
                    } else {
                        $message = 'Error: Admin user not found.';
                        $messageType = 'error';
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error deleting admin user. Please try again.';
                    $messageType = 'error';
                    error_log("Admin deletion error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get admins with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$totalAdmins = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
$totalPages = ceil($totalAdmins / $limit);

$admins = $pdo->prepare("
    SELECT * FROM admins 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$admins->execute([$limit, $offset]);
$admins = $admins->fetchAll();

// Handle edit action
$editAdmin = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editAdmin = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $editAdmin->execute([$editId]);
    $editAdmin = $editAdmin->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - SmartVote</title>
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
                <h1 class="text-2xl lg:text-3xl font-bold text-slate-800">Manage Admin Users</h1>
                <p class="text-sm lg:text-base text-slate-600 mt-1">Create, edit, and manage admin user accounts.</p>
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

            <!-- Create/Edit Admin Form -->
            <div class="bg-white rounded-xl shadow-lg p-4 lg:p-6 mb-4 lg:mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-slate-800">
                            <?php echo $editAdmin ? 'Edit Admin User' : 'Create New Admin User'; ?>
                        </h2>
                    </div>
                    <?php if ($editAdmin): ?>
                        <a href="manage_admins.php" class="inline-flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-slate-600 hover:bg-slate-700 rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="<?php echo $editAdmin ? 'update' : 'create'; ?>">
                    <?php if ($editAdmin): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$editAdmin['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username *</label>
                            <input required name="username" value="<?php echo $editAdmin ? htmlspecialchars($editAdmin['username']) : ''; ?>" 
                                   placeholder="Enter username" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
                            <input required name="fullname" value="<?php echo $editAdmin ? htmlspecialchars($editAdmin['fullname']) : ''; ?>" 
                                   placeholder="Enter full name" 
                                   class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Password <?php echo $editAdmin ? '(leave blank to keep current)' : '*'; ?>
                        </label>
                        <input type="password" <?php echo $editAdmin ? '' : 'required'; ?> name="password" 
                               placeholder="<?php echo $editAdmin ? 'Enter new password (min 8 characters)' : 'Enter password (min 8 characters)'; ?>" 
                               minlength="8"
                               class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                        <?php if ($editAdmin): ?>
                            <p class="text-xs text-slate-500 mt-1">Leave blank to keep the current password unchanged.</p>
                        <?php else: ?>
                            <p class="text-xs text-slate-500 mt-1">Password must be at least 8 characters long.</p>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <?php echo $editAdmin ? 'Update Admin' : 'Create Admin'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Admins List -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-slate-800">Admin Users List</h2>
                        <div class="flex items-center text-sm text-slate-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <?php echo number_format($totalAdmins); ?> total admin(s)
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Full Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Created At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($admins as $admin): ?>
                                <tr class="hover:bg-slate-50 transition-colors <?php echo (int)$admin['id'] === (int)($_SESSION['admin_id'] ?? 0) ? 'bg-blue-50' : ''; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <?php echo (int)$admin['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900">
                                            <?php echo htmlspecialchars($admin['username']); ?>
                                            <?php if ((int)$admin['id'] === (int)($_SESSION['admin_id'] ?? 0)): ?>
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?php echo htmlspecialchars($admin['fullname']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="?edit=<?php echo (int)$admin['id']; ?>" 
                                               class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-all duration-200" 
                                               title="Edit Admin">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit
                                            </a>
                                            
                                            <?php if ((int)$admin['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this admin user? This action cannot be undone.')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo (int)$admin['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-all duration-200" title="Delete Admin">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-slate-400 bg-slate-100 rounded-lg cursor-not-allowed" title="You cannot delete your own account">
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

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-slate-700">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalAdmins); ?> of <?php echo number_format($totalAdmins); ?> admin(s)
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

