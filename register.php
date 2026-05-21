<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/user/dashboard.php'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $name     = sanitize($_POST['name'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $phone    = sanitize($_POST['phone'] ?? '');

        if (!$name)                        $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (strlen($password) < 6)         $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm)        $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $result = registerUser($name, $email, $password, $phone);
            if ($result['success']) {
                loginUser($email, $password);
                setFlash('success', "Welcome to Luna Glow, $name! 🌸");
                header('Location: ' . APP_URL . '/user/dashboard.php'); exit;
            } else { $errors[] = $result['error']; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — Luna Glow</title>
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
  .auth-visual-img { width: 100%; max-width: 340px; border-radius: var(--radius-xl); margin-bottom: 32px; box-shadow: 0 30px 60px rgba(0,0,0,.3); }
  .auth-visual-logo { font-family: var(--font-serif); font-size: 2rem; color: white; font-weight: 700; margin-bottom: 20px; }
  .auth-visual-title { font-family: var(--font-serif); font-size: 2.2rem; color: white; line-height: 1.2; text-align: center; margin-bottom: 12px; }
  .auth-visual-sub { color: rgba(255,255,255,.65); text-align: center; font-size: .88rem; line-height: 1.7; }
  .auth-form-side { display: flex; align-items: center; justify-content: center; padding: 60px; background: var(--cream); overflow-y: auto; }
  .auth-form-card { width: 100%; max-width: 440px; }
  .auth-form-card h1 { font-size: 1.9rem; margin-bottom: 6px; }
  .auth-form-card > p { color: var(--text-muted); margin-bottom: 28px; font-size: .9rem; }
  .auth-error-list { background: #fef2f2; border: 1px solid #fca5a5; border-radius: var(--radius-md); padding: 14px 16px; color: #991b1b; font-size: .84rem; margin-bottom: 20px; }
  .auth-error-list li { margin-bottom: 4px; }
  .auth-links { text-align: center; margin-top: 20px; font-size: .88rem; color: var(--text-muted); }
  .auth-links a { color: var(--primary); font-weight: 500; }
  .password-wrapper { position: relative; }
  .password-wrapper .form-control { padding-right: 46px; }
  .password-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted); font-size: .9rem; background: none; border: none; }
  .perks { display: flex; flex-direction: column; gap: 10px; margin-top: 24px; }
  .perk { display: flex; align-items: center; gap: 10px; font-size: .82rem; color: rgba(255,255,255,.75); }
  .perk i { color: #fce8ed; font-size: .9rem; }
  @media(max-width:768px) { .auth-page { grid-template-columns: 1fr; } .auth-visual { display: none; } .auth-form-side { padding: 40px 24px; } }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-visual">
    <img src="https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=600&q=80" alt="Luna Glow" class="auth-visual-img">
    <div class="auth-visual-logo">✦ Luna Glow</div>
    <h2 class="auth-visual-title">Join the Glow Community</h2>
    <p class="auth-visual-sub">Create your free account and unlock a world of premium beauty.</p>
    <div class="perks">
      <div class="perk"><i class="fa-solid fa-heart"></i><span>Save your wishlist across devices</span></div>
      <div class="perk"><i class="fa-solid fa-truck"></i><span>Track your orders in real-time</span></div>
      <div class="perk"><i class="fa-solid fa-tag"></i><span>Access exclusive member discounts</span></div>
      <div class="perk"><i class="fa-solid fa-star"></i><span>Leave reviews and earn rewards</span></div>
    </div>
  </div>
  <div class="auth-form-side">
    <div class="auth-form-card">
      <a href="<?= APP_URL ?>/index.php" style="color:var(--primary);font-size:.85rem;display:flex;align-items:center;gap:6px;margin-bottom:24px;">
        <i class="fa-solid fa-arrow-left"></i> Back to Luna Glow
      </a>
      <h1>Create Account</h1>
      <p>Join Luna Glow for a premium beauty experience.</p>
      <?php if (!empty($errors)): ?>
        <ul class="auth-error-list">
          <?php foreach ($errors as $e): ?><li><i class="fa-solid fa-circle-xmark"></i> <?= sanitize($e) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" placeholder="Your full name" required
                 value="<?= sanitize($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" required
                 value="<?= sanitize($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="09XX XXX XXXX"
                 value="<?= sanitize($_POST['phone'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password *</label>
            <div class="password-wrapper">
              <input type="password" name="password" id="pw1" class="form-control" placeholder="Min 6 characters" required>
              <button type="button" class="password-toggle" onclick="togglePw('pw1','ico1')">
                <i id="ico1" class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <div class="password-wrapper">
              <input type="password" name="confirm_password" id="pw2" class="form-control" placeholder="Repeat password" required>
              <button type="button" class="password-toggle" onclick="togglePw('pw2','ico2')">
                <i id="ico2" class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>
        </div>
        <div style="margin-bottom:20px;">
          <label style="display:flex;align-items:flex-start;gap:10px;font-size:.82rem;cursor:pointer;">
            <input type="checkbox" required style="margin-top:3px;accent-color:var(--primary);">
            <span style="color:var(--text-muted);">I agree to the <a href="#" style="color:var(--primary);">Terms of Service</a> and <a href="#" style="color:var(--primary);">Privacy Policy</a>.</span>
          </label>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg">Create Account</button>
      </form>
      <div class="auth-links">
        Already have an account? <a href="<?= APP_URL ?>/login.php">Sign In</a>
      </div>
    </div>
  </div>
</div>
<script>
function togglePw(id, ico) {
  const i = document.getElementById(id);
  const e = document.getElementById(ico);
  if (i.type === 'password') { i.type = 'text'; e.className = 'fa-regular fa-eye-slash'; }
  else { i.type = 'password'; e.className = 'fa-regular fa-eye'; }
}
</script>
</body>
</html>
