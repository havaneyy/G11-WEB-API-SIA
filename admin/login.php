<?php
require_once __DIR__ . '/../config.php';
$error = '';
// Change this local demo password before using the site beyond your own computer.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (hash_equals('admin123', (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['codecraft_admin'] = true;
        header('Location: products.php');
        exit;
    }
    $error = 'Incorrect password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CodeCraft Admin Login</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<main class="result-page">
    <h1>Admin Login</h1>
    <p>Local demo password: change this in admin/login.php before sharing the project.</p>
    <?php if ($error): ?>
        <p style="color:#d93025; font-weight:bold;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="post" style="max-width:320px;">
        <label style="display:block; font-weight:600; margin-bottom:12px;">Password
            <div style="position:relative; margin-top:6px; display:flex; align-items:center;">
                <input type="password" id="adminPassword" name="password" required style="width:100%; padding-right:42px; box-sizing:border-box;">
                <button type="button" onclick="toggleAdminPassword(this)" style="position:absolute; right:8px; background:transparent; border:none; color:#6b7280; cursor:pointer; padding:6px; font-size:16px; display:flex; align-items:center; justify-content:center;" title="Show password" aria-label="Toggle password visibility">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </label> 
        <button type="submit">Log in</button>
    </form>
    <p><a href="../index.php">Back to store</a></p>
</main>
<script>
function toggleAdminPassword(btn) {
    const input = document.getElementById('adminPassword');
    if (!input) return;
    const isNowText = (input.type === 'password');
    input.type = isNowText ? 'text' : 'password';
    const icon = btn.querySelector('i');
    if (icon) {
        if (isNowText) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
            btn.setAttribute('title', 'Hide password');
            btn.setAttribute('aria-label', 'Hide password');
        } else {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
            btn.setAttribute('title', 'Show password');
            btn.setAttribute('aria-label', 'Show password');
        }
    }
}
</script>
</body>
</html>
