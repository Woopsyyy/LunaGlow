<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireAdmin();

// Dashboard stats
$stats = [
    'revenue'    => dbFetchColumn("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'"),
    'orders'     => dbFetchColumn("SELECT COUNT(*) FROM orders"),
    'products'   => dbFetchColumn("SELECT COUNT(*) FROM products"),
    'users'      => dbFetchColumn("SELECT COUNT(*) FROM users"),
    'pending'    => dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
    'low_stock'  => dbFetchColumn("SELECT COUNT(*) FROM products WHERE stock <= 5 AND stock > 0"),
    'out_stock'  => dbFetchColumn("SELECT COUNT(*) FROM products WHERE stock = 0"),
];

$recentOrders  = dbFetchAll("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");
$topProducts   = dbFetchAll("SELECT p.*, c.name AS cat_name, COALESCE(SUM(oi.quantity),0) AS units_sold FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN order_items oi ON oi.product_id = p.id GROUP BY p.id ORDER BY units_sold DESC LIMIT 5");
$recentUsers   = dbFetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$admin         = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);

// Revenue for last 7 days (for chart)
$revenueData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $rev = dbFetchColumn("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'", [$d]);
    $revenueData[] = ['date' => date('D', strtotime($d)), 'revenue' => (float) $rev];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  :root { --sidebar-w: 260px; }
  body { background: #f5f0f5; }
  .admin-layout { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; }

  /* ── Sidebar ──────────────────────────────────────── */
  .admin-sidebar {
    background: var(--text-dark);
    position: fixed; top: 0; left: 0;
    width: var(--sidebar-w);
    height: 100vh;
    display: flex; flex-direction: column;
    overflow-y: auto;
    z-index: 100;
    border-right: 1px solid rgba(255,255,255,.05);
  }
  .admin-sidebar-logo {
    padding: 28px 24px;
    font-family: var(--font-serif);
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary-light);
    border-bottom: 1px solid rgba(255,255,255,.06);
    display: block;
    text-decoration: none;
  }
  .admin-sidebar-logo span { display: block; font-family: var(--font-sans); font-size: .72rem; font-weight: 400; color: rgba(255,255,255,.4); margin-top: 4px; letter-spacing: 1px; text-transform: uppercase; }
  .sidebar-section { padding: 20px 16px 8px; font-size: .65rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,.3); }
  .sidebar-link {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 20px;
    font-size: .85rem;
    color: rgba(255,255,255,.6);
    text-decoration: none;
    border-radius: var(--radius-md);
    margin: 2px 10px;
    transition: .2s;
  }
  .sidebar-link i { width: 18px; text-align: center; font-size: .9rem; }
  .sidebar-link:hover { background: rgba(255,255,255,.07); color: white; }
  .sidebar-link.active { background: rgba(212,120,154,.2); color: var(--primary-light); }
  .sidebar-link .badge-count { margin-left: auto; background: var(--primary); color: white; border-radius: var(--radius-pill); padding: 2px 8px; font-size: .65rem; font-weight: 700; }
  .admin-sidebar-footer { margin-top: auto; padding: 20px; border-top: 1px solid rgba(255,255,255,.06); }
  .admin-user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
  .admin-avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(212,120,154,.3); display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-weight: 700; color: var(--primary-light); font-size: 1rem; }
  .admin-name { font-size: .82rem; font-weight: 600; color: white; }
  .admin-role { font-size: .72rem; color: rgba(255,255,255,.4); }

  /* ── Main Content ─────────────────────────────────── */
  .admin-main { margin-left: var(--sidebar-w); padding: 32px; min-height: 100vh; }
  .admin-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
  .admin-topbar h1 { font-size: 1.8rem; color: var(--text-dark); }
  .admin-topbar p { font-size: .84rem; color: var(--text-muted); margin-top: 2px; }

  /* ── Stat Cards ───────────────────────────────────── */
  .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
  .admin-stat { background: white; border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); position: relative; overflow: hidden; }
  .admin-stat::after { content: ''; position: absolute; top: 0; right: 0; width: 80px; height: 80px; border-radius: 0 0 0 100%; background: var(--stat-color, var(--primary-light)); opacity: .5; }
  .stat-icon-box { width: 48px; height: 48px; border-radius: var(--radius-md); background: var(--stat-color, var(--primary-light)); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--stat-text, var(--primary)); margin-bottom: 16px; }
  .stat-label { font-size: .76rem; color: var(--text-muted); font-weight: 500; margin-bottom: 4px; }
  .stat-value { font-family: var(--font-serif); font-size: 1.8rem; font-weight: 700; color: var(--text-dark); }
  .stat-change { font-size: .75rem; margin-top: 6px; }
  .stat-change.up { color: var(--success); } .stat-change.warn { color: var(--warning); } .stat-change.danger { color: var(--danger); }

  /* ── Admin Cards ──────────────────────────────────── */
  .admin-card { background: white; border-radius: var(--radius-xl); padding: 28px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); margin-bottom: 24px; }
  .admin-card-title { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
  .admin-card-title a { font-size: .8rem; font-weight: 500; color: var(--primary); text-decoration: none; }

  /* ── Table ────────────────────────────────────────── */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 0 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 0; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }
  .admin-table img { width: 40px; height: 40px; border-radius: var(--radius-md); object-fit: cover; }

  /* ── Two Column ───────────────────────────────────── */
  .two-col { display: grid; grid-template-columns: 1.4fr 1fr; gap: 24px; }

  @media(max-width:1200px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
  @media(max-width:900px) { .admin-sidebar { transform: translateX(-100%); } .admin-main { margin-left: 0; } .two-col { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<div class="admin-layout">
  <!-- ── Sidebar ─────────────────────────────────────── -->
  <nav class="admin-sidebar">
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="admin-sidebar-logo">
      ✦ Luna Glow <span>Admin Panel</span>
    </a>
    <div class="sidebar-section">Overview</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="sidebar-link active"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="<?= APP_URL ?>/admin/orders.php" class="sidebar-link"><i class="fa-solid fa-bag-shopping"></i> Orders
      <?php if ($stats['pending'] > 0): ?><span class="badge-count"><?= $stats['pending'] ?></span><?php endif; ?>
    </a>
    <div class="sidebar-section">Catalog</div>
    <a href="<?= APP_URL ?>/admin/products.php" class="sidebar-link"><i class="fa-solid fa-box"></i> Products</a>
    <a href="<?= APP_URL ?>/admin/categories.php" class="sidebar-link"><i class="fa-solid fa-tags"></i> Categories</a>
    <a href="<?= APP_URL ?>/admin/banners.php" class="sidebar-link"><i class="fa-solid fa-image"></i> Banners</a>
    <div class="sidebar-section">Marketing</div>
    <a href="<?= APP_URL ?>/admin/coupons.php" class="sidebar-link"><i class="fa-solid fa-ticket"></i> Coupons</a>
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
          <div class="admin-role"><?= sanitize($admin['role']) ?></div>
        </div>
      </div>
    </div>
  </nav>

  <!-- ── Main ───────────────────────────────────────── -->
  <main class="admin-main">
    <div class="admin-topbar">
      <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= sanitize($admin['name']) ?>! Here's what's happening today.</p>
      </div>
      <div style="display:flex;gap:10px;">
        <a href="<?= APP_URL ?>/admin/products.php?action=add" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Add Product
        </a>
        <a href="<?= APP_URL ?>/admin/orders.php?status=pending" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-bell"></i> <?= $stats['pending'] ?> Pending
        </a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
      <div class="admin-stat" style="--stat-color:#fce8ed;--stat-text:var(--primary);">
        <div class="stat-icon-box"><i class="fa-solid fa-peso-sign"></i></div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value"><?= formatPrice((float) $stats['revenue']) ?></div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> From all time</div>
      </div>
      <div class="admin-stat" style="--stat-color:#eff6ff;--stat-text:#3b82f6;">
        <div class="stat-icon-box" style="color:#3b82f6;"><i class="fa-solid fa-bag-shopping"></i></div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-value"><?= $stats['orders'] ?></div>
        <div class="stat-change warn"><i class="fa-solid fa-clock"></i> <?= $stats['pending'] ?> pending</div>
      </div>
      <div class="admin-stat" style="--stat-color:#f0fdf4;--stat-text:#22c55e;">
        <div class="stat-icon-box" style="color:#22c55e;"><i class="fa-solid fa-box"></i></div>
        <div class="stat-label">Products</div>
        <div class="stat-value"><?= $stats['products'] ?></div>
        <div class="stat-change <?= $stats['out_stock'] > 0 ? 'danger' : 'up' ?>">
          <i class="fa-solid fa-<?= $stats['out_stock'] > 0 ? 'triangle-exclamation' : 'circle-check' ?>"></i>
          <?= $stats['out_stock'] ?> out of stock
        </div>
      </div>
      <div class="admin-stat" style="--stat-color:#fdf4ff;--stat-text:#a855f7;">
        <div class="stat-icon-box" style="color:#a855f7;"><i class="fa-solid fa-users"></i></div>
        <div class="stat-label">Customers</div>
        <div class="stat-value"><?= $stats['users'] ?></div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> Registered users</div>
      </div>
    </div>

    <div class="two-col">
      <!-- Revenue Chart -->
      <div class="admin-card">
        <div class="admin-card-title">Revenue — Last 7 Days</div>
        <canvas id="revenueChart" height="100"></canvas>
      </div>

      <!-- Recent Users -->
      <div class="admin-card">
        <div class="admin-card-title">
          New Customers <a href="<?= APP_URL ?>/admin/users.php">View All</a>
        </div>
        <?php foreach ($recentUsers as $u): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light);">
          <div class="admin-avatar" style="font-size:.8rem;"><?= strtoupper(substr($u['name'],0,1)) ?></div>
          <div>
            <div style="font-size:.85rem;font-weight:600;color:var(--text-dark);"><?= sanitize($u['name']) ?></div>
            <div style="font-size:.76rem;color:var(--text-muted);"><?= sanitize($u['email']) ?></div>
          </div>
          <div style="margin-left:auto;font-size:.72rem;color:var(--text-muted);"><?= timeAgo($u['created_at']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="two-col">
      <!-- Recent Orders -->
      <div class="admin-card">
        <div class="admin-card-title">
          Recent Orders <a href="<?= APP_URL ?>/admin/orders.php">View All</a>
        </div>
        <table class="admin-table">
          <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/admin/orders.php?view=<?= $o['id'] ?>" style="color:var(--primary);font-weight:600;font-size:.85rem;">#<?= sanitize($o['order_number']) ?></a><br><span style="font-size:.76rem;color:var(--text-muted);"><?= formatDate($o['created_at']) ?></span></td>
            <td style="font-size:.84rem;"><?= sanitize($o['shipping_name']) ?></td>
            <td style="font-weight:600;"><?= formatPrice($o['total']) ?></td>
            <td><?= getStatusBadge($o['status']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Top Products -->
      <div class="admin-card">
        <div class="admin-card-title">
          Top Products <a href="<?= APP_URL ?>/admin/products.php">View All</a>
        </div>
        <table class="admin-table">
          <thead><tr><th>Product</th><th>Stock</th><th>Sold</th></tr></thead>
          <tbody>
          <?php foreach ($topProducts as $p): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <img src="<?= sanitize($p['image']) ?>" alt="">
                <div>
                  <div style="font-size:.84rem;font-weight:500;color:var(--text-dark);"><?= sanitize($p['name']) ?></div>
                  <div style="font-size:.74rem;color:var(--text-muted);"><?= formatPrice($p['price']) ?></div>
                </div>
              </div>
            </td>
            <td><span style="color:<?= $p['stock'] < 5 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:600;font-size:.84rem;"><?= $p['stock'] ?></span></td>
            <td style="font-size:.84rem;font-weight:600;"><?= $p['units_sold'] ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if ($stats['low_stock'] > 0 || $stats['out_stock'] > 0): ?>
    <div class="admin-card" style="border-left:4px solid var(--warning);">
      <div class="admin-card-title" style="color:var(--warning);">
        <span><i class="fa-solid fa-triangle-exclamation"></i> Stock Alerts</span>
        <a href="<?= APP_URL ?>/admin/products.php?filter=low_stock">Manage</a>
      </div>
      <p style="font-size:.88rem;color:var(--text-body);">
        <?php if ($stats['out_stock']): ?><span style="color:var(--danger);font-weight:700;"><?= $stats['out_stock'] ?> products out of stock.</span> <?php endif; ?>
        <?php if ($stats['low_stock']): ?><?= $stats['low_stock'] ?> products with 5 or fewer units remaining.<?php endif; ?>
      </p>
    </div>
    <?php endif; ?>
  </main>
</div>

<script>
// Revenue Chart
const revenueData = <?= json_encode($revenueData) ?>;
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: revenueData.map(d => d.date),
    datasets: [{
      label: 'Revenue (₱)',
      data: revenueData.map(d => d.revenue),
      backgroundColor: 'rgba(212,120,154,.2)',
      borderColor: 'rgba(212,120,154,.8)',
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 11 } } },
      y: { grid: { color: '#f0e0e8' }, ticks: { font: { family: 'Poppins', size: 11 }, callback: v => '₱' + v.toLocaleString() } }
    }
  }
});
</script>
</body>
</html>
