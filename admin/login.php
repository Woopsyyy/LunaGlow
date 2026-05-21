<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isAdmin()) { header('Location: ' . APP_URL . '/admin/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $admin    = dbFetchOne("SELECT * FROM admins WHERE email = ? AND is_active = 1", [$email]);
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['admin_name']  = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role']  = $admin['role'];
            header('Location: ' . APP_URL . '/admin/dashboard.php'); exit;
        } else { $error = 'Invalid credentials.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  body { background: var(--text-dark); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .admin-login-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 52px 44px;
    max-width: 420px; width: 100%;
    box-shadow: 0 40px 80px rgba(0,0,0,.3);
    margin: 20px;
  }
  .admin-login-logo { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 700; color: var(--primary); text-align: center; margin-bottom: 6px; }
  .admin-login-badge { text-align: center; font-size: .72rem; letter-spacing: 3px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 36px; }
  .admin-error { background: #fef2f2; border: 1px solid #fca5a5; border-radius: var(--radius-md); padding: 12px 16px; color: #991b1b; font-size: .84rem; margin-bottom: 20px; }
  .back-to-store { text-align: center; margin-top: 20px; font-size: .82rem; color: var(--text-muted); }
  .back-to-store a { color: var(--primary); }
  </style>
</head>
<body>
<div class="admin-login-card">
  <div class="admin-login-logo">✦ Luna Glow</div>
  <div class="admin-login-badge">Admin Portal</div>
  <?php if ($error): ?>
    <div class="admin-error"><i class="fa-solid fa-lock"></i> <?= sanitize($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label class="form-label">Admin Email</label>
      <input type="email" name="email" class="form-control" required autofocus placeholder="admin@lunaglow.com"
             value="<?= sanitize($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required placeholder="Your admin password">
    </div>
    <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:8px;">
      <i class="fa-solid fa-lock"></i> Sign In to Admin
    </button>
  </form>
  <div class="back-to-store"><a href="<?= APP_URL ?>/index.php"><i class="fa-solid fa-arrow-left"></i> Back to Store</a></div>
  <p style="text-align:center;font-size:.76rem;color:var(--text-muted);margin-top:20px;">Default: admin@lunaglow.com / admin123</p>
</div>
</body>
</html>
