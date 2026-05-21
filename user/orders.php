<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$user   = getUserById($_SESSION['user_id']);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 10;
$total  = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE user_id = ?", [$_SESSION['user_id']]);
$pages  = max(1, (int) ceil($total / $per));
$offset = ($page - 1) * $per;
$orders = dbFetchAll("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT $per OFFSET $offset", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .dash-layout { display: grid; grid-template-columns: 260px 1fr; gap: 32px; padding: calc(var(--navbar-h)+40px) var(--section-px) var(--section-py); max-width: var(--container); margin: 0 auto; }
  .dash-sidebar { position: sticky; top: calc(var(--navbar-h)+16px); height: fit-content; }
  .dash-profile-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-xl); padding: 28px; color: white; text-align: center; margin-bottom: 14px; }
  .dash-avatar { width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-size: 1.6rem; font-weight: 700; margin: 0 auto 12px; }
  .dash-nav { background: white; border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; font-size: .88rem; color: var(--text-body); transition: .2s; text-decoration: none; border-bottom: 1px solid var(--border-light); }
  .dash-nav-item:last-child { border-bottom: none; }
  .dash-nav-item i { width: 18px; text-align: center; color: var(--text-muted); }
  .dash-nav-item:hover, .dash-nav-item.active { background: var(--primary-light); color: var(--primary); }
  .dash-nav-item:hover i, .dash-nav-item.active i { color: var(--primary); }
  .dash-nav-item.danger { color: var(--danger); } .dash-nav-item.danger i { color: var(--danger); } .dash-nav-item.danger:hover { background: #fef2f2; }
  .dash-card { background: white; border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .orders-table { width: 100%; border-collapse: collapse; }
  .orders-table th { font-size: .72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding-bottom: 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .orders-table td { padding: 16px 0; border-bottom: 1px solid var(--border-light); font-size: .88rem; vertical-align: middle; }
  .orders-table tr:last-child td { border-bottom: none; }
  @media(max-width:900px) { .dash-layout { grid-template-columns: 1fr; } .dash-sidebar { position: static; } }
  @media(max-width:640px) { .orders-table th:nth-child(3), .orders-table td:nth-child(3) { display: none; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../components/navbar.php'; ?>
<?php include __DIR__ . '/../components/cart-drawer.php'; ?>

<div class="dash-layout">
  <aside class="dash-sidebar">
    <div class="dash-profile-card">
      <div class="dash-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div style="font-family:var(--font-serif);font-size:1.1rem;font-weight:600;margin-bottom:4px;"><?= sanitize($user['name']) ?></div>
      <div style="font-size:.76rem;opacity:.8;"><?= sanitize($user['email']) ?></div>
    </div>
    <nav class="dash-nav">
      <a href="<?= APP_URL ?>/user/dashboard.php" class="dash-nav-item"><i class="fa-regular fa-user"></i> My Account</a>
      <a href="<?= APP_URL ?>/user/orders.php" class="dash-nav-item active"><i class="fa-regular fa-bag-shopping"></i> My Orders</a>
      <a href="<?= APP_URL ?>/user/wishlist.php" class="dash-nav-item"><i class="fa-regular fa-heart"></i> My Wishlist</a>
      <a href="<?= APP_URL ?>/tracking.php" class="dash-nav-item"><i class="fa-solid fa-truck"></i> Track Order</a>
      <a href="<?= APP_URL ?>/user/settings.php" class="dash-nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
      <a href="<?= APP_URL ?>/logout.php" class="dash-nav-item danger"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>
  </aside>
  <div>
    <div class="dash-card">
      <h2 style="font-family:var(--font-serif);font-size:1.6rem;margin-bottom:24px;">Order History</h2>
      <?php if (empty($orders)): ?>
      <div class="empty-state" style="padding:48px 0;">
        <div class="empty-state-icon"><i class="fa-regular fa-bag-shopping"></i></div>
        <h3>No orders yet</h3>
        <p>Your order history will appear here once you place an order.</p>
        <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary">Start Shopping</a>
      </div>
      <?php else: ?>
      <table class="orders-table">
        <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o):
          $itemCount = dbFetchColumn("SELECT SUM(quantity) FROM order_items WHERE order_id = ?", [$o['id']]);
        ?>
        <tr>
          <td><span style="font-weight:600;color:var(--primary);">#<?= sanitize($o['order_number']) ?></span></td>
          <td style="color:var(--text-muted);"><?= formatDate($o['created_at']) ?></td>
          <td><?= $itemCount ?> item<?= $itemCount != 1 ? 's' : '' ?></td>
          <td style="font-weight:600;"><?= formatPrice($o['total']) ?></td>
          <td><?= getStatusBadge($o['status']) ?></td>
          <td><a href="<?= APP_URL ?>/tracking.php?order=<?= urlencode($o['order_number']) ?>" class="btn btn-ghost btn-sm">Track</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($pages > 1): ?>
      <div class="pagination" style="margin-top:28px;">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?page=<?= $i ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
</body>
</html>
