<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin(APP_URL . '/user/dashboard.php');

$user    = getUserById($_SESSION['user_id']);
$orders  = dbFetchAll("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", [$_SESSION['user_id']]);
$wishlist = dbFetchAll("SELECT p.*, c.name AS cat_name FROM wishlist_items wi JOIN products p ON p.id = wi.product_id LEFT JOIN categories c ON c.id = p.category_id WHERE wi.user_id = ? ORDER BY wi.created_at DESC LIMIT 4", [$_SESSION['user_id']]);
$stats = [
    'orders'   => dbFetchColumn("SELECT COUNT(*) FROM orders WHERE user_id = ?", [$_SESSION['user_id']]),
    'wishlist' => dbFetchColumn("SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?", [$_SESSION['user_id']]),
    'delivered'=> dbFetchColumn("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'", [$_SESSION['user_id']]),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .dash-layout { display: grid; grid-template-columns: 260px 1fr; gap: 32px; padding: calc(var(--navbar-h)+40px) var(--section-px) var(--section-py); max-width: var(--container); margin: 0 auto; }
  .dash-sidebar { position: sticky; top: calc(var(--navbar-h)+16px); height: fit-content; }
  .dash-profile-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius: var(--radius-xl); padding: 32px; color: white; text-align: center; margin-bottom: 16px; }
  .dash-avatar { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,.25); display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-size: 2rem; font-weight: 700; margin: 0 auto 16px; border: 3px solid rgba(255,255,255,.4); }
  .dash-name { font-family: var(--font-serif); font-size: 1.3rem; font-weight: 600; margin-bottom: 4px; }
  .dash-email { font-size: .78rem; opacity: .8; }
  .dash-nav { background: white; border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; font-size: .88rem; color: var(--text-body); transition: .2s; text-decoration: none; border-bottom: 1px solid var(--border-light); }
  .dash-nav-item:last-child { border-bottom: none; }
  .dash-nav-item i { width: 18px; text-align: center; color: var(--text-muted); }
  .dash-nav-item:hover, .dash-nav-item.active { background: var(--primary-light); color: var(--primary); }
  .dash-nav-item:hover i, .dash-nav-item.active i { color: var(--primary); }
  .dash-nav-item.danger { color: var(--danger); }
  .dash-nav-item.danger i { color: var(--danger); }
  .dash-nav-item.danger:hover { background: #fef2f2; }

  .dash-main { }
  .dash-card { background: white; border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); margin-bottom: 24px; }
  .dash-card-title { font-family: var(--font-serif); font-size: 1.4rem; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
  .dash-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
  .stat-card { background: white; border-radius: var(--radius-lg); padding: 24px; text-align: center; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .stat-icon { width: 48px; height: 48px; border-radius: 50%; background: var(--primary-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: var(--primary); font-size: 1.1rem; }
  .stat-num { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 700; color: var(--text-dark); }
  .stat-label { font-size: .78rem; color: var(--text-muted); margin-top: 4px; }

  .order-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 16px 0; border-bottom: 1px solid var(--border-light); }
  .order-row:last-child { border-bottom: none; }
  .order-num-link { font-weight: 600; color: var(--primary); font-size: .9rem; text-decoration: none; }
  .order-num-link:hover { text-decoration: underline; }
  .order-meta { font-size: .8rem; color: var(--text-muted); margin-top: 2px; }

  .wish-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
  @media(max-width:1100px) { .wish-grid { grid-template-columns: repeat(2, 1fr); } }
  @media(max-width:900px) { .dash-layout { grid-template-columns: 1fr; } .dash-sidebar { position: static; } .dash-stats { grid-template-columns: 1fr 1fr; } }
  @media(max-width:600px) { .dash-stats { grid-template-columns: 1fr; } .wish-grid { grid-template-columns: repeat(2, 1fr); } }
  </style>
</head>
<body>
<?php include __DIR__ . '/../components/navbar.php'; ?>
<?php include __DIR__ . '/../components/cart-drawer.php'; ?>
<?= renderFlash() ?>

<div class="dash-layout">
  <!-- Sidebar -->
  <aside class="dash-sidebar">
    <div class="dash-profile-card">
      <div class="dash-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div class="dash-name"><?= sanitize($user['name']) ?></div>
      <div class="dash-email"><?= sanitize($user['email']) ?></div>
    </div>
    <nav class="dash-nav">
      <a href="<?= APP_URL ?>/user/dashboard.php" class="dash-nav-item active"><i class="fa-regular fa-user"></i> My Account</a>
      <a href="<?= APP_URL ?>/user/orders.php" class="dash-nav-item"><i class="fa-regular fa-bag-shopping"></i> My Orders</a>
      <a href="<?= APP_URL ?>/user/wishlist.php" class="dash-nav-item"><i class="fa-regular fa-heart"></i> My Wishlist</a>
      <a href="<?= APP_URL ?>/tracking.php" class="dash-nav-item"><i class="fa-solid fa-truck"></i> Track Order</a>
      <a href="<?= APP_URL ?>/user/settings.php" class="dash-nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
      <a href="<?= APP_URL ?>/logout.php" class="dash-nav-item danger"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>
  </aside>

  <!-- Main -->
  <div class="dash-main">
    <!-- Stats -->
    <div class="dash-stats">
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-bag-shopping"></i></div>
        <div class="stat-num"><?= $stats['orders'] ?></div>
        <div class="stat-label">Total Orders</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-heart"></i></div>
        <div class="stat-num"><?= $stats['wishlist'] ?></div>
        <div class="stat-label">Wishlist Items</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-num"><?= $stats['delivered'] ?></div>
        <div class="stat-label">Delivered</div>
      </div>
    </div>

    <!-- Recent Orders -->
    <div class="dash-card">
      <div class="dash-card-title">
        <span>Recent Orders</span>
        <a href="<?= APP_URL ?>/user/orders.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <?php if (empty($orders)): ?>
      <div class="empty-state" style="padding:32px 0;">
        <div class="empty-state-icon"><i class="fa-regular fa-bag-shopping"></i></div>
        <h3>No orders yet</h3>
        <p>Start shopping and your orders will appear here.</p>
        <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary btn-sm">Shop Now</a>
      </div>
      <?php else: ?>
      <?php foreach ($orders as $o): ?>
      <div class="order-row">
        <div>
          <a href="<?= APP_URL ?>/tracking.php?order=<?= urlencode($o['order_number']) ?>" class="order-num-link">
            #<?= sanitize($o['order_number']) ?>
          </a>
          <div class="order-meta"><?= formatDate($o['created_at']) ?> · <?= formatPrice($o['total']) ?></div>
        </div>
        <?= getStatusBadge($o['status']) ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Wishlist Preview -->
    <?php if (!empty($wishlist)): ?>
    <div class="dash-card">
      <div class="dash-card-title">
        <span>My Wishlist</span>
        <a href="<?= APP_URL ?>/user/wishlist.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="wish-grid">
        <?php foreach ($wishlist as $p): ?>
        <div class="product-card" style="box-shadow:none;border:1px solid var(--border-light);">
          <div class="product-card-image" style="height:160px;">
            <img src="<?= productImage($p['image']) ?>" alt="<?= sanitize($p['name']) ?>">
            <button class="product-card-wishlist active" onclick="toggleWishlist(<?= $p['id'] ?>, this)">
              <i class="fa-solid fa-heart"></i>
            </button>
          </div>
          <div class="product-card-body" style="padding:14px;">
            <a href="<?= APP_URL ?>/product.php?id=<?= $p['id'] ?>">
              <div class="product-card-name" style="font-size:.9rem;"><?= sanitize($p['name']) ?></div>
            </a>
            <div class="product-card-price" style="margin-top:6px;">
              <span class="price-current" style="font-size:.9rem;"><?= formatPrice($p['price']) ?></span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Links -->
    <div class="dash-card" style="background:linear-gradient(135deg,var(--beige),var(--cream));">
      <h3 style="font-size:1.2rem;margin-bottom:16px;">Quick Actions</h3>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary"><i class="fa-solid fa-sparkles"></i> Shop Now</a>
        <a href="<?= APP_URL ?>/tracking.php" class="btn btn-outline"><i class="fa-solid fa-truck"></i> Track Order</a>
        <a href="<?= APP_URL ?>/contact.php" class="btn btn-white"><i class="fa-regular fa-envelope"></i> Get Help</a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script src="<?= APP_URL ?>/assets/js/wishlist.js"></script>
</body>
</html>
