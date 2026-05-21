<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$user     = getUserById($_SESSION['user_id']);
$wishlist = dbFetchAll("
    SELECT p.*, c.name AS cat_name
    FROM wishlist_items wi
    JOIN products p ON p.id = wi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE wi.user_id = ?
    ORDER BY wi.created_at DESC
", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Wishlist — Luna Glow</title>
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
  @media(max-width:900px) { .dash-layout { grid-template-columns: 1fr; } .dash-sidebar { position: static; } }
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
      <a href="<?= APP_URL ?>/user/orders.php" class="dash-nav-item"><i class="fa-regular fa-bag-shopping"></i> My Orders</a>
      <a href="<?= APP_URL ?>/user/wishlist.php" class="dash-nav-item active"><i class="fa-regular fa-heart"></i> My Wishlist</a>
      <a href="<?= APP_URL ?>/tracking.php" class="dash-nav-item"><i class="fa-solid fa-truck"></i> Track Order</a>
      <a href="<?= APP_URL ?>/user/settings.php" class="dash-nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
      <a href="<?= APP_URL ?>/logout.php" class="dash-nav-item danger"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </nav>
  </aside>
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
      <h2 style="font-family:var(--font-serif);font-size:1.6rem;">My Wishlist</h2>
      <span style="color:var(--text-muted);font-size:.85rem;"><?= count($wishlist) ?> saved items</span>
    </div>

    <?php if (empty($wishlist)): ?>
    <div style="background:white;border-radius:var(--radius-xl);padding:64px 32px;text-align:center;border:1px solid var(--border-light);">
      <div class="empty-state-icon" style="margin:0 auto 20px;"><i class="fa-regular fa-heart"></i></div>
      <h3>Your wishlist is empty</h3>
      <p style="margin-bottom:24px;color:var(--text-muted);">Save products you love for later by clicking the heart icon.</p>
      <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary">Explore Products</a>
    </div>
    <?php else: ?>
    <div class="products-grid" id="wishlistGrid">
      <?php foreach ($wishlist as $p): ?>
      <div class="product-card" id="wish-card-<?= $p['id'] ?>">
        <div class="product-card-image">
          <img src="<?= productImage($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
          <button class="product-card-wishlist active" onclick="removeFromWishlist(<?= $p['id'] ?>, this)" title="Remove">
            <i class="fa-solid fa-heart"></i>
          </button>
          <div class="product-card-hover">
            <button class="btn btn-primary btn-full btn-sm" onclick="addToCart(<?= $p['id'] ?>, this)">
              <i class="fa-solid fa-bag-shopping"></i> Add to Bag
            </button>
          </div>
        </div>
        <div class="product-card-body">
          <div class="product-card-category"><?= sanitize($p['cat_name'] ?? '') ?></div>
          <a href="<?= APP_URL ?>/product.php?id=<?= $p['id'] ?>">
            <h3 class="product-card-name"><?= sanitize($p['name']) ?></h3>
          </a>
          <div class="product-card-price">
            <span class="price-current"><?= formatPrice($p['price']) ?></span>
            <?php if ($p['original_price'] > 0): ?><span class="price-original"><?= formatPrice($p['original_price']) ?></span><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script src="<?= APP_URL ?>/assets/js/wishlist.js"></script>
<script>
async function removeFromWishlist(id, btn) {
  const card = document.getElementById('wish-card-' + id);
  card.style.opacity = '.4'; card.style.pointerEvents = 'none';
  const res = await fetch('/xampp/Project/LunaGlow/api/wishlist.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'remove', product_id: id})
  });
  const d = await res.json();
  if (d.success) { card.remove(); showToast('Removed from wishlist.'); }
  else { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; showToast(d.message, 'error'); }
}
</script>
</body>
</html>
