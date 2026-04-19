<?php
// Optional simple voter self-registration (admin can still manage)
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voterId = trim($_POST['voter_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';
    if ($voterId && $name && $email) {
        // Require password input - no default passwords
        if (empty($passwordRaw)) {
            $message = 'Error: Password is required.';
        } elseif (strlen($passwordRaw) < 8) {
            $message = 'Error: Password must be at least 8 characters long.';
        } else {
            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO voters (voter_id, name, email, password, has_voted, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
            try { 
                $stmt->execute([$voterId, $name, $email, $password]); 
                $message = 'Registration successful. You can now login.'; 
            } catch (Throwable $e) { 
                $message = 'Registration failed. Ensure Voter ID/email are unique.'; 
            }
        }
    } else {
        $message = 'Please fill all fields.';
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
    tailwind.config = { theme: { extend: { colors: { brand: '#0ea5e9' }}} }
  </script>
  <link rel="icon" href="assets/images/favicon.png" />
  </head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
  <div class="w-full max-w-xl bg-white shadow rounded-xl p-4 sm:p-6">
    <h1 class="text-xl sm:text-2xl font-bold text-slate-800 text-center">SmartVote System – School Online Voting</h1>
    <p class="mt-2 text-sm sm:text-base text-center text-slate-600">Optional Voter Registration</p>
    <?php if ($message): ?><div class="mt-3 p-3 text-sm rounded <?php echo strpos($message, 'successful')!==false ? 'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-700'; ?>"><?php echo h($message); ?></div><?php endif; ?>
    <form method="post" class="mt-4 grid gap-3">
      <input name="voter_id" required placeholder="Voter ID" class="border rounded p-2" />
      <input name="name" required placeholder="Full Name" class="border rounded p-2" />
      <input type="email" name="email" required placeholder="Email" class="border rounded p-2" />
      <input type="password" name="password" required placeholder="Password" class="border rounded p-2" />
      <button class="bg-brand hover:bg-sky-600 text-white rounded py-2">Register</button>
      <div class="text-center text-sm text-slate-500 space-x-4">
        <a href="voter/index.php" class="hover:underline">Go to Voter Login</a>
      </div>
    </form>
  </div>
</body>
</html>

