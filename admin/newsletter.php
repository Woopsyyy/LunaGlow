<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action       = $_GET['action'] ?? 'list';
$subscriberId = sanitizeInt($_GET['id'] ?? 0);
$success      = '';
$error        = '';

// Fetch stats for order count (sidebar indicator)
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

// Handle CSV Export
if ($action === 'export') {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        header('Location: ' . APP_URL . '/admin/newsletter.php');
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lunaglow_subscribers_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Email Address', 'Date Subscribed']);
    
    $subscribers = dbFetchAll("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC");
    foreach ($subscribers as $sub) {
        fputcsv($output, [
            $sub['id'],
            $sub['email'],
            $sub['subscribed_at']
        ]);
    }
    fclose($output);
    exit;
}

// Handle Delete Subscriber
if ($action === 'delete' && $subscriberId) {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        dbQuery("DELETE FROM newsletter_subscribers WHERE id = ?", [$subscriberId]);
        setFlash('success', 'Subscriber removed successfully.');
    }
    header('Location: ' . APP_URL . '/admin/newsletter.php');
    exit;
}

// Pagination & Search setup
$search = sanitize($_GET['search'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 15;

$params = [];
$whereClause = "";
if ($search) {
    $whereClause = "WHERE email LIKE ?";
    $params[] = "%$search%";
}

$countSql = "SELECT COUNT(*) FROM newsletter_subscribers $whereClause";
$listSql  = "SELECT * FROM newsletter_subscribers $whereClause ORDER BY subscribed_at DESC";

$totalRows = (int) dbFetchColumn($countSql, $params);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$subscribers = dbFetchAll("$listSql LIMIT $perPage OFFSET $offset", $params);

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Newsletter Subscribers — Luna Glow Admin</title>
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

  /* Search row */
  .search-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
  .search-form { display: flex; align-items: center; gap: 8px; flex: 1; max-width: 400px; }
  .search-input { width: 100%; padding: 9px 16px; border-radius: var(--radius-md); border: 1.5px solid var(--border); font-family: inherit; font-size: .88rem; color: var(--text-dark); transition: .2s; }
  .search-input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }

  /* Table styling */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }

  /* Pagination styling */
  .pagination { display: flex; justify-content: center; gap: 6px; margin-top: 32px; }
  .page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 8px; border-radius: var(--radius-md); border: 1.5px solid var(--border); background: white; color: var(--text-dark); text-decoration: none; font-size: .85rem; font-weight: 600; transition: .2s; }
  .page-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-glow); }
  .page-link.active { background: var(--primary); border-color: var(--primary); color: white; }

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
    .search-row { flex-direction: column; align-items: stretch; }
    .search-form { max-width: 100%; }
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
    <a href="<?= APP_URL ?>/admin/coupons.php" class="sidebar-link"><i class="fa-solid fa-ticket"></i> Coupons</a>
    <a href="<?= APP_URL ?>/admin/reviews.php" class="sidebar-link"><i class="fa-regular fa-star"></i> Reviews</a>
    <a href="<?= APP_URL ?>/admin/newsletter.php" class="sidebar-link active"><i class="fa-solid fa-envelope"></i> Newsletter</a>
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

    <div class="admin-topbar">
      <div>
        <h1>Newsletter Signups</h1>
        <p>Monitor your shop's subscriber registry and export data for digital marketing campaigns.</p>
      </div>
      <a href="?action=export&csrf_token=<?= csrfToken() ?>" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-file-export"></i> Export CSV Registry
      </a>
    </div>

    <div class="admin-card">
      <div class="search-row">
        <form class="search-form" method="GET" action="">
          <input type="text" name="search" class="search-input" placeholder="Search by email address..." value="<?= sanitize($search) ?>">
          <button type="submit" class="btn btn-outline btn-sm" style="padding:9px 16px;"><i class="fa-solid fa-magnifying-glass"></i></button>
          <?php if ($search): ?>
            <a href="<?= APP_URL ?>/admin/newsletter.php" class="btn btn-ghost btn-sm" style="padding:9px 10px;" title="Reset"><i class="fa-solid fa-xmark"></i></a>
          <?php endif; ?>
        </form>
        <div style="font-size:.82rem;color:var(--text-muted);font-weight:500;">
          Total Subscribers: <span style="color:var(--text-dark);font-weight:600;"><?= $totalRows ?></span>
        </div>
      </div>

      <?php if (empty($subscribers)): ?>
        <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
          <i class="fa-regular fa-envelope" style="font-size:2rem;margin-bottom:12px;"></i>
          <p>No subscribers found matching the query.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Subscriber ID</th>
              <th>Email Address</th>
              <th>Subscription Timeline</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subscribers as $sub): ?>
              <tr>
                <td style="font-family:monospace;font-size:.84rem;color:var(--text-muted);">#SUB-<?= str_pad($sub['id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td style="font-weight:600;color:var(--text-dark);font-size:.9rem;"><?= sanitize($sub['email']) ?></td>
                <td>
                  <span style="font-size:.84rem;color:var(--text-body);"><?= formatDate($sub['subscribed_at'], 'M j, Y g:i A') ?></span>
                  <span style="font-size:.76rem;color:var(--text-muted);margin-left:6px;">(<?= timeAgo($sub['subscribed_at']) ?>)</span>
                </td>
                <td style="text-align:right;">
                  <a href="?action=delete&id=<?= $sub['id'] ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('Are you sure you want to permanently remove this subscriber from the mailing list?');" title="Remove Subscriber">
                    <i class="fa-regular fa-trash-can"></i> Remove
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" class="page-link <?= $page === $i ? 'active' : '' ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
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
