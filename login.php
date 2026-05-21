<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/user/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (loginUser($email, $password)) {
            $redirect = sanitize($_GET['redirect'] ?? APP_URL . '/user/dashboard.php');
            header('Location: ' . $redirect); exit;
        } else { $error = 'Invalid email or password.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .auth-page { min-height: 100vh; display: grid; grid-template-columns: 1fr 1fr; }
  .auth-visual {
    background: linear-gradient(160deg, #2a1f28 0%, #4a2a3a 60%, #d4789a 100%);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 60px; position: relative; overflow: hidden;
  }
  .auth-visual::before {
    content: '';
    position: absolute; top: -80px; right: -80px;
    width: 320px; height: 320px; border-radius: 50%;
    background: rgba(255,255,255,.04);
  }
  .auth-visual::after {
    content: '';
    position: absolute; bottom: -60px; left: -60px;
    width: 240px; height: 240px; border-radius: 50%;
    background: rgba(255,255,255,.03);
  }
  .auth-visual-logo { font-family: var(--font-serif); font-size: 2rem; color: white; font-weight: 700; margin-bottom: 24px; }
  .auth-visual-title { font-family: var(--font-serif); font-size: 2.4rem; color: white; line-height: 1.2; text-align: center; margin-bottom: 16px; }
  .auth-visual-sub { color: rgba(255,255,255,.65); text-align: center; font-size: .9rem; line-height: 1.7; }
  .auth-visual-img { width: 100%; max-width: 340px; border-radius: var(--radius-xl); margin-bottom: 32px; box-shadow: 0 30px 60px rgba(0,0,0,.3); }
  .auth-form-side { display: flex; align-items: center; justify-content: center; padding: 60px; background: var(--cream); }
  .auth-form-card { width: 100%; max-width: 420px; }
  .auth-form-card h1 { font-size: 2rem; margin-bottom: 6px; }
  .auth-form-card p { color: var(--text-muted); margin-bottom: 32px; font-size: .9rem; }
  .auth-error { background: #fef2f2; border: 1px solid #fca5a5; border-radius: var(--radius-md); padding: 12px 16px; color: #991b1b; font-size: .85rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
  .auth-links { text-align: center; margin-top: 24px; font-size: .88rem; color: var(--text-muted); }
  .auth-links a { color: var(--primary); font-weight: 500; }
  .password-wrapper { position: relative; }
  .password-wrapper .form-control { padding-right: 46px; }
  .password-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted); font-size: .9rem; background: none; border: none; }
  .password-toggle:hover { color: var(--primary); }
  @media(max-width:768px) { .auth-page { grid-template-columns: 1fr; } .auth-visual { display: none; } .auth-form-side { padding: 40px 24px; } }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-visual">
    <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=600&q=80" alt="Luna Glow Beauty" class="auth-visual-img">
    <div class="auth-visual-logo">✦ Luna Glow</div>
    <h2 class="auth-visual-title">Welcome back, beautiful.</h2>
    <p class="auth-visual-sub">Sign in to access your orders, wishlist, and exclusive member offers.</p>
  </div>
  <div class="auth-form-side">
    <div class="auth-form-card">
      <a href="<?= APP_URL ?>/index.php" style="color:var(--primary);font-size:.85rem;display:flex;align-items:center;gap:6px;margin-bottom:24px;">
        <i class="fa-solid fa-arrow-left"></i> Back to Luna Glow
      </a>
      <h1>Sign In</h1>
      <p>Enter your credentials to continue.</p>
      <?php if ($error): ?>
        <div class="auth-error"><i class="fa-solid fa-circle-xmark"></i><?= sanitize($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" required autofocus
                 value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Your password" required>
            <button type="button" class="password-toggle" onclick="togglePw()">
              <i class="fa-regular fa-eye" id="pwIcon"></i>
            </button>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:24px;">
          <a href="#" style="font-size:.82rem;color:var(--primary);">Forgot Password?</a>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg">Sign In</button>
      </form>
      <div class="auth-links">
        <p>Don't have an account? <a href="<?= APP_URL ?>/register.php">Create Account</a></p>
        <p style="margin-top:8px;"><a href="<?= APP_URL ?>/admin/login.php" style="color:var(--text-muted);font-size:.8rem;">Admin Login</a></p>
      </div>
    </div>
  </div>
</div>
<script>
function togglePw() {
  const inp = document.getElementById('passwordInput');
  const ico = document.getElementById('pwIcon');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fa-regular fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fa-regular fa-eye'; }
}
</script>
</body>
</html>
