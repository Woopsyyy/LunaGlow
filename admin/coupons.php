<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action   = $_GET['action'] ?? 'list';
$couponId = sanitizeInt($_GET['id'] ?? 0);
$success  = '';
$error    = '';

// Fetch stats for order count (sidebar indicator)
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

// Handle delete
if ($action === 'delete' && $couponId) {
    dbQuery("DELETE FROM coupons WHERE id = ?", [$couponId]);
    setFlash('success', 'Coupon deleted successfully.');
    header('Location: ' . APP_URL . '/admin/coupons.php');
    exit;
}

// Handle toggle status
if ($action === 'toggle_status' && $couponId) {
    dbQuery("UPDATE coupons SET is_active = 1 - is_active WHERE id = ?", [$couponId]);
    setFlash('success', 'Coupon status updated.');
    header('Location: ' . APP_URL . '/admin/coupons.php');
    exit;
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $code         = strtoupper(sanitize(trim($_POST['code'] ?? '')));
        $discountType = sanitize($_POST['discount_type'] ?? 'percent');
        $value        = sanitizeFloat($_POST['discount_value'] ?? 0);
        $minOrder     = sanitizeFloat($_POST['min_order'] ?? 0);
        $maxUses      = sanitizeInt($_POST['max_uses'] ?? 100);
        $expiresAt    = sanitize($_POST['expires_at'] ?? '');
        $isActive     = isset($_POST['is_active']) ? 1 : 0;

        if (!$code || $value <= 0) {
            $error = 'Please enter a valid uppercase coupon code and discount value.';
        } else {
            // Check code uniqueness
            $checkSql = "SELECT COUNT(*) FROM coupons WHERE code = ?" . ($couponId ? " AND id != $couponId" : "");
            $count = dbFetchColumn($checkSql, [$code]);
            
            if ($count > 0) {
                $error = 'This coupon code already exists.';
            } else {
                $expiryVal = !empty($expiresAt) ? $expiresAt : null;
                
                if ($action === 'add') {
                    dbQuery("INSERT INTO coupons (code, discount_type, discount_value, min_order, max_uses, expires_at, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$code, $discountType, $value, $minOrder, $maxUses, $expiryVal, $isActive]);
                    setFlash('success', 'Coupon created successfully.');
                    header('Location: ' . APP_URL . '/admin/coupons.php');
                    exit;
                } elseif ($action === 'edit' && $couponId) {
                    dbQuery("UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?, min_order = ?, max_uses = ?, expires_at = ?, is_active = ? WHERE id = ?",
                        [$code, $discountType, $value, $minOrder, $maxUses, $expiryVal, $isActive, $couponId]);
                    setFlash('success', 'Coupon updated successfully.');
                    header('Location: ' . APP_URL . '/admin/coupons.php');
                    exit;
                }
            }
        }
    }
}

// Fetch coupon list
$coupons = dbFetchAll("SELECT * FROM coupons ORDER BY id DESC");

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Coupons — Luna Glow Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  :root { --sidebar-w: 260px; }
  body { background: var(--cream); }
  .admin-layout { display: flex; min-height: 100vh; }

  /* Sidebar styling */
  .admin-sidebar { background: var(--text-dark); position: fixed; top: 0; left: 0; width: var(--sidebar-w); height: 100vh; display: flex; flex-direction: column; overflow-y: auto; z-index: 1000; border-right: 1px solid rgba(255,255,255,.05); transition: transform var(--transition); }
  .admin-sidebar-logo { padding: 28px 24px; font-family: var(--font-serif); font-size: 1.4rem; font-weight: 700; color: var(--primary-light); border-bottom: 1px solid rgba(255,255,255,.06); display: block; text-decoration: none; }
  .admin-sidebar-logo span { display: block; font-family: var(--font-sans); font-size: .72rem; font-weight: 400; color: rgba(255,255,255,.4); margin-top: 4px; letter-spacing: 1px; text-transform: uppercase; }
  .sidebar-section { padding: 20px 16px 8px; font-size: .65rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,.3); }
  .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: .85rem; color: rgba(255,255,255,.6); text-decoration: none; border-radius: var(--radius-md); margin: 2px 10px; transition: var(--transition-fast); }
  .sidebar-link i { width: 18px; text-align: center; font-size: .9rem; }
  .sidebar-link:hover { background: rgba(255,255,255,.07); color: white; }
  .sidebar-link.active { background: rgba(212,120,154,.2); color: var(--primary-light); }
  .sidebar-link .badge-count { margin-left: auto; background: var(--primary); color: white; border-radius: var(--radius-pill); padding: 2px 8px; font-size: .65rem; font-weight: 700; }
  .admin-sidebar-footer { margin-top: auto; padding: 20px; border-top: 1px solid rgba(255,255,255,.06); }
  .admin-user-info { display: flex; align-items: center; gap: 12px; }
  .admin-avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(212,120,154,.3); display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-weight: 700; color: var(--primary-light); font-size: 1rem; }
  .admin-name { font-size: .82rem; font-weight: 600; color: white; }
  .admin-role { font-size: .72rem; color: rgba(255,255,255,.4); }

  /* Overlay and mobile styling */
  .admin-sidebar-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(42,31,40,.4); backdrop-filter: blur(4px); z-index: 999; opacity: 0; pointer-events: none; transition: opacity var(--transition); }
  .admin-sidebar-overlay.show { opacity: 1; pointer-events: auto; }

  .admin-mobile-header { display: none; align-items: center; justify-content: space-between; padding: 16px 24px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 900; width: 100%; box-shadow: var(--shadow-xs); }
  .mobile-toggle-btn { font-size: 1.25rem; color: var(--text-dark); background: none; border: none; cursor: pointer; padding: 8px; border-radius: var(--radius-sm); transition: var(--transition-fast); display: flex; align-items: center; justify-content: center; }
  .mobile-toggle-btn:hover { background: var(--primary-glow); color: var(--primary); }
  .mobile-logo { font-family: var(--font-serif); font-size: 1.25rem; font-weight: 700; color: var(--primary); }

  .admin-main { flex: 1; margin-left: var(--sidebar-w); padding: 32px; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }
  .admin-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
  .admin-topbar h1 { font-size: 1.8rem; color: var(--text-dark); }
  .admin-card { background: white; border-radius: var(--radius-xl); padding: 28px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); margin-bottom: 24px; }

  /* Coupons layout */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }
  
  .toggle-status-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: #fdf2f4; color: var(--primary); font-size: .85rem; border: none; cursor: pointer; text-decoration: none; transition: .2s; }
  .toggle-status-btn:hover { background: var(--primary); color: white; }
  .toggle-status-btn.off { background: #f3f4f6; color: #9ca3af; }
  .toggle-status-btn.off:hover { background: #9ca3af; color: white; }

  /* Form */
  .form-group { margin-bottom: 20px; }
  .form-label { display: block; font-size: .84rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; }
  .form-control { width: 100%; padding: 11px 16px; border-radius: var(--radius-md); border: 1.5px solid var(--border); font-family: inherit; font-size: .88rem; color: var(--text-dark); transition: .2s; }
  .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

  @media(max-width:992px) {
    .admin-sidebar { transform: translateX(-100%); }
    .admin-sidebar.show { transform: translateX(0); }
    .admin-main { margin-left: 0; padding: 24px 16px; }
    .admin-mobile-header { display: flex; }
    .admin-topbar { margin-top: 16px; }
  }
  @media(max-width:576px) {
    .admin-topbar { flex-direction: column; align-items: flex-start; gap: 12px; }
    .admin-topbar div { width: 100%; }
    .admin-topbar .btn { width: 100%; text-align: center; justify-content: center; }
    .form-row { grid-template-columns: 1fr; gap: 0; }
  }
  </style>
</head>
<body>
<div class="admin-sidebar-overlay" id="sidebarOverlay"></div>
<div class="admin-layout">
  <!-- ── Sidebar ─────────────────────────────────────── -->
  <nav class="admin-sidebar">
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="admin-sidebar-logo">
      ✦ Luna Glow <span>Admin Panel</span>
    </a>
    <div class="sidebar-section">Overview</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-link"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="<?= APP_URL ?>/admin/orders.php" class="sidebar-link"><i class="fa-solid fa-bag-shopping"></i> Orders
      <?php if ($pendingOrdersCount > 0): ?><span class="badge-count"><?= $pendingOrdersCount ?></span><?php endif; ?>
    </a>
    <div class="sidebar-section">Catalog</div>
    <a href="<?= APP_URL ?>/admin/products.php" class="sidebar-link"><i class="fa-solid fa-box"></i> Products</a>
    <a href="<?= APP_URL ?>/admin/categories.php" class="sidebar-link"><i class="fa-solid fa-tags"></i> Categories</a>
    <a href="<?= APP_URL ?>/admin/banners.php" class="sidebar-link"><i class="fa-solid fa-image"></i> Banners</a>
    <div class="sidebar-section">Marketing</div>
    <a href="<?= APP_URL ?>/admin/coupons.php" class="sidebar-link active"><i class="fa-solid fa-ticket"></i> Coupons</a>
    <a href="<?= APP_URL ?>/admin/reviews.php" class="sidebar-link"><i class="fa-regular fa-star"></i> Reviews</a>
    <a href="<?= APP_URL ?>/admin/newsletter.php" class="sidebar-link"><i class="fa-solid fa-envelope"></i> Newsletter</a>
    <div class="sidebar-section">Users</div>
    <a href="<?= APP_URL ?>/admin/users.php" class="sidebar-link"><i class="fa-solid fa-users"></i> Customers</a>
    <div class="sidebar-section">System</div>
    <a href="<?= APP_URL ?>/index.php" class="sidebar-link" target="_blank"><i class="fa-solid fa-store"></i> View Store</a>
    <a href="<?= APP_URL ?>/admin/logout.php" class="sidebar-link" style="color:rgba(239,68,68,.8)"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    <div class="admin-sidebar-footer">
      <div class="admin-user-info">
        <div class="admin-avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
        <div>
          <div class="admin-name"><?= sanitize($admin['name']) ?></div>
          <div class="admin-role"><?= sanitize($admin['role'] ?? 'Administrator') ?></div>
        </div>
      </div>
    </div>
  </nav>

  <!-- ── Main ───────────────────────────────────────── -->
  <main class="admin-main">
    <div class="admin-mobile-header">
      <button id="sidebarToggle" class="mobile-toggle-btn"><i class="fa-solid fa-bars"></i></button>
      <div class="mobile-logo">✦ Luna Glow</div>
      <div style="width: 32px;"></div>
    </div>
    <?= renderFlash() ?>

    <?php if ($action === 'list'): ?>
      <div class="admin-topbar">
        <div>
          <h1>Coupons</h1>
          <p>Create and edit discount codes to incentivize purchases.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/coupons.php?action=add" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Add Coupon
        </a>
      </div>

      <div class="admin-card">
        <?php if (empty($coupons)): ?>
          <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
            <i class="fa-solid fa-ticket" style="font-size:2rem;margin-bottom:12px;"></i>
            <p>No coupons currently configured.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Min. Purchase</th>
                <th>Usage</th>
                <th>Expiry</th>
                <th style="text-align:center;">Active</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($coupons as $c): 
                $isExpired = $c['expires_at'] && strtotime($c['expires_at']) < time();
                ?>
                <tr>
                  <td><code style="font-size:1rem;font-weight:700;color:var(--primary);"><?= sanitize($c['code']) ?></code></td>
                  <td style="font-weight:600;">
                    <?= $c['discount_type'] === 'percent' ? sanitize($c['discount_value']) . '%' : formatPrice($c['discount_value']) ?>
                  </td>
                  <td><?= formatPrice($c['min_order']) ?></td>
                  <td>
                    <span style="font-weight:600;"><?= $c['used_count'] ?></span> / 
                    <span style="color:var(--text-muted);font-size:.8rem;"><?= $c['max_uses'] ?></span>
                  </td>
                  <td>
                    <?php if ($isExpired): ?>
                      <span class="badge" style="background:#fef2f2;color:#ef4444;font-weight:600;">Expired</span>
                    <?php elseif ($c['expires_at']): ?>
                      <span style="font-size:.8rem;color:var(--text-body);"><?= formatDate($c['expires_at']) ?></span>
                    <?php else: ?>
                      <span style="font-size:.76rem;color:var(--text-muted);">Never Expires</span>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:center;">
                    <a href="?action=toggle_status&id=<?= $c['id'] ?>" class="toggle-status-btn <?= $c['is_active'] ? '' : 'off' ?>" title="Toggle Active">
                      <i class="fa-solid fa-power-off"></i>
                    </a>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:6px;">
                      <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                      <a href="?action=delete&id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('Are you sure you want to permanently delete this coupon?');" title="Delete"><i class="fa-regular fa-trash-can"></i></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
      <?php
      $title = $action === 'add' ? 'Add Coupon' : 'Edit Coupon';
      $cData = ['code' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_order' => 0, 'max_uses' => 100, 'expires_at' => '', 'is_active' => 1];
      if ($action === 'edit' && $couponId) {
          $loaded = dbFetchOne("SELECT * FROM coupons WHERE id = ?", [$couponId]);
          if ($loaded) $cData = $loaded;
      }
      ?>
      <div class="admin-topbar">
        <div>
          <h1><?= $title ?></h1>
          <p>Configure percentage or fixed discount bounds.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/coupons.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to Coupons
        </a>
      </div>

      <div class="admin-card" style="max-width:600px;">
        <?php if ($error): ?>
          <div class="flash-message flash-error" style="margin-bottom:20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="?action=<?= $action ?><?= $couponId ? '&id=' . $couponId : '' ?>" method="POST">
          <?= csrfField() ?>

          <div class="form-group">
            <label for="c_code" class="form-label">Coupon Code *</label>
            <input type="text" name="code" id="c_code" class="form-control" placeholder="e.g. GLOW20" value="<?= sanitize($cData['code']) ?>" required style="text-transform:uppercase;">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="c_type" class="form-label">Discount Type *</label>
              <select name="discount_type" id="c_type" class="form-control" style="padding:10px 16px;" required>
                <option value="percent" <?= $cData['discount_type'] === 'percent' ? 'selected' : '' ?>>Percentage (%)</option>
                <option value="fixed" <?= $cData['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount (₱)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="c_val" class="form-label">Discount Value *</label>
              <input type="number" name="discount_value" id="c_val" class="form-control" value="<?= $cData['discount_value'] ?>" step="0.01" min="0.01" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="c_min" class="form-label">Minimum Purchase Value (₱)</label>
              <input type="number" name="min_order" id="c_min" class="form-control" value="<?= $cData['min_order'] ?>" step="0.01" min="0">
            </div>
            <div class="form-group">
              <label for="c_uses" class="form-label">Total Usage Cap</label>
              <input type="number" name="max_uses" id="c_uses" class="form-control" value="<?= $cData['max_uses'] ?>" min="1" required>
            </div>
          </div>

          <div class="form-group">
            <label for="c_expiry" class="form-label">Expiry Date</label>
            <input type="date" name="expires_at" id="c_expiry" class="form-control" value="<?= $cData['expires_at'] ?>">
          </div>

          <div style="display:flex;align-items:center;gap:10px;margin:24px 0;">
            <input type="checkbox" name="is_active" id="c_active" value="1" <?= $cData['is_active'] ? 'checked' : '' ?>>
            <label for="c_active" style="font-size:.85rem;font-weight:600;color:var(--text-dark);cursor:pointer;user-select:none;">Coupon Active</label>
          </div>

          <div style="display:flex;gap:12px;margin-top:32px;">
            <button type="submit" class="btn btn-primary">Save Coupon</button>
            <a href="<?= APP_URL ?>/admin/coupons.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </main>
</div>

<script>
// Mobile Sidebar Toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.admin-sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (sidebarToggle && sidebar && sidebarOverlay) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.add('show');
    sidebarOverlay.classList.add('show');
  });

  sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    sidebarOverlay.classList.remove('show');
  });
}
</script>
</body>
</html>
