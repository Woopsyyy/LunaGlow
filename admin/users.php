<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action     = $_GET['action'] ?? 'list';
$customerId = sanitizeInt($_GET['id'] ?? 0);
$success    = '';
$error      = '';

// Fetch stats for order count (sidebar indicator)
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

// Handle toggle active status
if ($action === 'toggle_status' && $customerId) {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        dbQuery("UPDATE users SET is_active = 1 - is_active WHERE id = ?", [$customerId]);
        setFlash('success', 'Customer account status updated successfully.');
    }
    $red = ($_GET['redirect'] ?? '') === 'view' ? 'users.php?action=view&id=' . $customerId : 'users.php';
    header('Location: ' . APP_URL . '/admin/' . $red);
    exit;
}

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);

// Detail View Action
if ($action === 'view' && $customerId) {
    $customer = dbFetchOne("
        SELECT u.*, 
               COUNT(o.id) AS total_orders, 
               COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total ELSE 0 END), 0) AS total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ", [$customerId]);

    if (!$customer) {
        setFlash('error', 'Customer not found.');
        header('Location: ' . APP_URL . '/admin/users.php');
        exit;
    }

    $addresses = dbFetchAll("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC", [$customerId]);
    $orders    = dbFetchAll("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC", [$customerId]);
} else {
    $action = 'list';
    // Pagination & Search setup
    $search = sanitize($_GET['search'] ?? '');
    $page = max(1, sanitizeInt($_GET['page'] ?? 1));
    $perPage = 15;

    $params = [];
    $whereClause = "";
    if ($search) {
        $whereClause = "WHERE u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?";
        $params = ["%$search%", "%$search%", "%$search%"];
    }

    $countSql = "SELECT COUNT(*) FROM users u $whereClause";
    $listSql  = "SELECT u.*, 
                        COUNT(o.id) AS total_orders, 
                        COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total ELSE 0 END), 0) AS total_spent
                 FROM users u
                 LEFT JOIN orders o ON o.user_id = u.id
                 $whereClause
                 GROUP BY u.id
                 ORDER BY u.created_at DESC";

    $totalRows = (int) dbFetchColumn($countSql, $params);
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $customers = dbFetchAll("$listSql LIMIT $perPage OFFSET $offset", $params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Customers — Luna Glow Admin</title>
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

  .user-avatar-list { width: 40px; height: 40px; border-radius: 50%; background: #fce8ed; display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-weight: 700; color: var(--primary); font-size: 1rem; border: 1.5px solid var(--border-light); }

  .toggle-status-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: #f0fdf4; color: #22c55e; font-size: .85rem; border: none; cursor: pointer; text-decoration: none; transition: .2s; }
  .toggle-status-btn:hover { background: #22c55e; color: white; }
  .toggle-status-btn.off { background: #fef2f2; color: #ef4444; }
  .toggle-status-btn.off:hover { background: #ef4444; color: white; }

  /* Two Column Detail Layout */
  .detail-grid { display: grid; grid-template-columns: 320px 1fr; gap: 28px; }
  
  .profile-header-card { text-align: center; padding: 32px 24px; border-bottom: 1.5px solid var(--border-light); margin-bottom: 20px; }
  .profile-avatar-large { width: 80px; height: 80px; border-radius: 50%; background: #fce8ed; display: inline-flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-weight: 700; color: var(--primary); font-size: 2.2rem; border: 2px solid var(--border-light); margin-bottom: 16px; }
  .profile-name { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); }
  .profile-email { font-size: .84rem; color: var(--text-muted); margin-top: 4px; }
  
  .profile-meta-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed var(--border-light); font-size: .82rem; }
  .profile-meta-row:last-child { border-bottom: none; }
  .profile-meta-label { color: var(--text-muted); font-weight: 500; }
  .profile-meta-value { color: var(--text-dark); font-weight: 600; }

  .stat-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
  .stat-mini-card { background: var(--cream); border-radius: var(--radius-md); padding: 14px; text-align: center; border: 1.5px solid var(--border-light); }
  .stat-mini-num { font-family: var(--font-serif); font-size: 1.25rem; font-weight: 700; color: var(--text-dark); }
  .stat-mini-lbl { font-size: .72rem; color: var(--text-muted); font-weight: 500; margin-top: 2px; }

  .address-badge-default { background: var(--primary-light); color: var(--primary); font-size: .68rem; font-weight: 700; padding: 2px 8px; border-radius: var(--radius-pill); display: inline-block; margin-bottom: 6px; }

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
    .detail-grid { grid-template-columns: 1fr; }
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
    <a href="<?= APP_URL ?>/admin/newsletter.php" class="sidebar-link"><i class="fa-solid fa-envelope"></i> Newsletter</a>
    <div class="sidebar-section">Users</div>
    <a href="<?= APP_URL ?>/admin/users.php" class="sidebar-link active"><i class="fa-solid fa-users"></i> Customers</a>
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
          <h1>Customers Directory</h1>
          <p>Inspect active shopper timelines, lifecycle spent totals, and toggle login permissions.</p>
        </div>
      </div>

      <div class="admin-card">
        <div class="search-row">
          <form class="search-form" method="GET" action="">
            <input type="text" name="search" class="search-input" placeholder="Search by name, email or phone..." value="<?= sanitize($search) ?>">
            <button type="submit" class="btn btn-outline btn-sm" style="padding:9px 16px;"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if ($search): ?>
              <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-ghost btn-sm" style="padding:9px 10px;" title="Reset"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
          </form>
          <div style="font-size:.82rem;color:var(--text-muted);font-weight:500;">
            Registered Customers: <span style="color:var(--text-dark);font-weight:600;"><?= $totalRows ?></span>
          </div>
        </div>

        <?php if (empty($customers)): ?>
          <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
            <i class="fa-solid fa-users" style="font-size:2rem;margin-bottom:12px;"></i>
            <p>No customers found matching the search criteria.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Contact Particulars</th>
                <th>Joined timeline</th>
                <th>Total Orders</th>
                <th>Total Spent</th>
                <th style="text-align:center;">Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $c): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:12px;">
                      <div class="user-avatar-list"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                      <div>
                        <div style="font-weight:600;color:var(--text-dark);"><?= sanitize($c['name']) ?></div>
                        <div style="font-size:.76rem;color:var(--text-muted);">ID: #CUST-<?= str_pad($c['id'], 5, '0', STR_PAD_LEFT) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight:500;font-size:.84rem;color:var(--text-dark);"><?= sanitize($c['email']) ?></div>
                    <div style="font-size:.78rem;color:var(--text-muted);"><?= sanitize($c['phone'] ?: 'No Phone') ?></div>
                  </td>
                  <td>
                    <div style="font-size:.84rem;color:var(--text-body);"><?= formatDate($c['created_at']) ?></div>
                    <div style="font-size:.74rem;color:var(--text-muted);margin-top:2px;"><?= timeAgo($c['created_at']) ?></div>
                  </td>
                  <td style="font-weight:600;font-size:.9rem;"><?= $c['total_orders'] ?> orders</td>
                  <td style="font-weight:600;color:var(--primary);font-size:.92rem;"><?= formatPrice((float) $c['total_spent']) ?></td>
                  <td style="text-align:center;">
                    <a href="?action=toggle_status&id=<?= $c['id'] ?>&csrf_token=<?= csrfToken() ?>" class="toggle-status-btn <?= $c['is_active'] ? '' : 'off' ?>" title="<?= $c['is_active'] ? 'Block Customer' : 'Unblock Customer' ?>">
                      <i class="fa-solid fa-<?= $c['is_active'] ? 'user-check' : 'user-slash' ?>"></i>
                    </a>
                  </td>
                  <td style="text-align:right;">
                    <a href="?action=view&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" style="padding:6px 12px;font-size:.78rem;">
                      <i class="fa-solid fa-eye"></i> View Profile
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

    <?php elseif ($action === 'view' && $customerId): ?>
      <div class="admin-topbar">
        <div>
          <h1>Customer Profile</h1>
          <p>Deep-dive customer purchase histories, shipping registers, and log states.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to Directory
        </a>
      </div>

      <div class="detail-grid">
        <!-- Profile Column -->
        <div>
          <div class="admin-card" style="padding: 0;">
            <div class="profile-header-card">
              <div class="profile-avatar-large"><?= strtoupper(substr($customer['name'], 0, 1)) ?></div>
              <div class="profile-name"><?= sanitize($customer['name']) ?></div>
              <div class="profile-email"><?= sanitize($customer['email']) ?></div>
              <div style="margin-top:14px;">
                <?php if ($customer['is_active']): ?>
                  <span class="badge" style="background:#f0fdf4;color:#22c55e;font-weight:600;"><i class="fa-solid fa-circle-check"></i> Account Active</span>
                <?php else: ?>
                  <span class="badge" style="background:#fef2f2;color:#ef4444;font-weight:600;"><i class="fa-solid fa-user-slash"></i> Account Blocked</span>
                <?php endif; ?>
              </div>
            </div>
            
            <div style="padding: 0 24px 28px;">
              <div class="stat-mini-grid">
                <div class="stat-mini-card">
                  <div class="stat-mini-num"><?= $customer['total_orders'] ?></div>
                  <div class="stat-mini-lbl">All Orders</div>
                </div>
                <div class="stat-mini-card">
                  <div class="stat-mini-num" style="color:var(--primary);"><?= formatPrice((float) $customer['total_spent']) ?></div>
                  <div class="stat-mini-lbl">Total Spent</div>
                </div>
              </div>

              <div class="profile-meta-row">
                <div class="profile-meta-label">Customer ID</div>
                <div class="profile-meta-value">#CUST-<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?></div>
              </div>
              <div class="profile-meta-row">
                <div class="profile-meta-label">Phone Number</div>
                <div class="profile-meta-value"><?= sanitize($customer['phone'] ?: 'Not Configured') ?></div>
              </div>
              <div class="profile-meta-row">
                <div class="profile-meta-label">Member Since</div>
                <div class="profile-meta-value"><?= formatDate($customer['created_at']) ?></div>
              </div>
              <div class="profile-meta-row">
                <div class="profile-meta-label">Timeline Lifespan</div>
                <div class="profile-meta-value"><?= timeAgo($customer['created_at']) ?></div>
              </div>

              <div style="margin-top:28px;">
                <a href="?action=toggle_status&id=<?= $customer['id'] ?>&redirect=view&csrf_token=<?= csrfToken() ?>" class="btn <?= $customer['is_active'] ? 'btn-outline' : 'btn-primary' ?>" style="width:100%;text-align:center;justify-content:center;color:<?= $customer['is_active'] ? 'var(--danger)' : '' ?>;border-color:<?= $customer['is_active'] ? 'rgba(239,68,68,.2)' : '' ?>;">
                  <i class="fa-solid fa-<?= $customer['is_active'] ? 'user-slash' : 'user-check' ?>"></i>
                  <?= $customer['is_active'] ? 'Block Customer Account' : 'Unblock Customer Account' ?>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- History & Address Column -->
        <div>
          <!-- Addresses Card -->
          <div class="admin-card">
            <div class="admin-card-title" style="margin-bottom:16px;"><i class="fa-solid fa-address-book" style="margin-right:8px;color:var(--primary);"></i> Shipping Address Directory</div>
            <?php if (empty($addresses)): ?>
              <div style="color:var(--text-muted);font-size:.84rem;padding:12px 0;">No addresses configured by this shopper.</div>
            <?php else: ?>
              <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:16px;">
                <?php foreach ($addresses as $addr): ?>
                  <div style="background:var(--cream);border:1.5px solid var(--border-light);border-radius:var(--radius-lg);padding:18px;position:relative;">
                    <?php if ($addr['is_default']): ?>
                      <div class="address-badge-default">Primary / Default</div>
                    <?php endif; ?>
                    <div style="font-weight:700;color:var(--text-dark);font-size:.88rem;"><?= sanitize($addr['full_name']) ?> <span style="font-size:.76rem;font-weight:600;color:var(--text-muted);">(<?= sanitize($addr['label']) ?>)</span></div>
                    <div style="font-size:.8rem;color:var(--text-body);margin-top:6px;line-height:1.4;">
                      <?= sanitize($addr['address_line1']) ?><br>
                      <?php if ($addr['address_line2']): ?><?= sanitize($addr['address_line2']) ?><br><?php endif; ?>
                      <?= sanitize($addr['city']) ?>, <?= sanitize($addr['province']) ?> <?= sanitize($addr['zip_code']) ?>
                    </div>
                    <div style="font-size:.78rem;color:var(--text-muted);margin-top:8px;"><i class="fa-solid fa-phone" style="font-size:.7rem;"></i> <?= sanitize($addr['phone']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Purchase History Card -->
          <div class="admin-card">
            <div class="admin-card-title" style="margin-bottom:16px;"><i class="fa-solid fa-bag-shopping" style="margin-right:8px;color:var(--primary);"></i> Purchase Chronology</div>
            <?php if (empty($orders)): ?>
              <div style="color:var(--text-muted);font-size:.84rem;padding:12px 0;text-align:center;">No orders placed yet.</div>
            <?php else: ?>
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order Number</th>
                    <th>Date Placed</th>
                    <th>Payment Method</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $ord): ?>
                    <tr>
                      <td><code style="font-size:.9rem;font-weight:700;color:var(--text-dark);"><?= sanitize($ord['order_number']) ?></code></td>
                      <td>
                        <div style="font-size:.84rem;color:var(--text-body);"><?= formatDate($ord['created_at']) ?></div>
                        <div style="font-size:.74rem;color:var(--text-muted);"><?= timeAgo($ord['created_at']) ?></div>
                      </td>
                      <td style="text-transform:uppercase;font-size:.78rem;font-weight:600;color:var(--text-muted);"><?= sanitize($ord['payment_method']) ?></td>
                      <td style="font-weight:700;color:var(--primary);font-size:.88rem;"><?= formatPrice((float) $ord['total']) ?></td>
                      <td><?= getStatusBadge($ord['status']) ?></td>
                      <td style="text-align:right;">
                        <a href="<?= APP_URL ?>/admin/orders.php?search=<?= urlencode($ord['order_number']) ?>" class="btn btn-ghost btn-sm" title="Inspect Order Details">
                          <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
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
