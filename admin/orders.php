<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action   = $_GET['action'] ?? 'list';
$orderId  = sanitizeInt($_GET['view'] ?? 0);
$success  = '';
$error    = '';

// Fetch stats for order count
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

// Handle status updates and tracking/reference details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $orderId) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $loadedOrder = dbFetchOne("SELECT * FROM orders WHERE id = ?", [$orderId]);
        if ($loadedOrder) {
            $newStatus = sanitize($_POST['status'] ?? '');
            $gcashRef  = sanitize($_POST['gcash_ref'] ?? '');
            $notes     = sanitize($_POST['notes'] ?? '');
            
            if (in_array($newStatus, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
                dbQuery("UPDATE orders SET status = ?, gcash_ref = ?, notes = ? WHERE id = ?", 
                    [$newStatus, $gcashRef, $notes, $orderId]);
                
                // If cancelled or returned, refund inventory stock back
                if ($newStatus === 'cancelled' && $loadedOrder['status'] !== 'cancelled') {
                    $items = dbFetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
                    foreach ($items as $item) {
                        if ($item['product_id']) {
                            dbQuery("UPDATE products SET stock = stock + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
                        }
                    }
                }
                // If it was cancelled previously and is now being reactivated, subtract from stock again
                if ($loadedOrder['status'] === 'cancelled' && $newStatus !== 'cancelled') {
                    $items = dbFetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
                    foreach ($items as $item) {
                        if ($item['product_id']) {
                            dbQuery("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?", [$item['quantity'], $item['product_id']]);
                        }
                    }
                }

                setFlash('success', 'Order #' . $loadedOrder['order_number'] . ' updated successfully.');
                header('Location: ' . APP_URL . '/admin/orders.php?view=' . $orderId);
                exit;
            } else {
                $error = 'Invalid order status value.';
            }
        }
    }
}

// Order View detail extraction
$order = null;
$orderItems = [];
if ($orderId) {
    $order = dbFetchOne("SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?", [$orderId]);
    if ($order) {
        $orderItems = dbFetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);
    }
}

// Paginated query
$statusFilter = sanitize($_GET['status'] ?? 'all');
$search       = sanitize($_GET['search'] ?? '');
$page         = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = "WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $where .= " AND o.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR o.shipping_name LIKE ? OR o.shipping_email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "SELECT o.*, u.name AS user_name FROM orders o LEFT JOIN users u ON u.id = o.user_id $where ORDER BY o.created_at DESC";
$pagination = paginate($sql, $params, 15, $page);
$orders = $pagination['rows'];

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Orders — Luna Glow Admin</title>
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
  
  .filters-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
  .search-form { display: flex; flex: 1; min-width: 260px; border: 1.5px solid var(--border); border-radius: var(--radius-pill); padding: 6px 14px; background: white; }
  .search-form input { border: none; outline: none; flex: 1; font-size: .85rem; }
  .search-form button { background: none; border: none; color: var(--text-muted); cursor: pointer; }
  .filter-select { padding: 8px 16px; border-radius: var(--radius-pill); border: 1.5px solid var(--border); font-family: inherit; font-size: .85rem; outline: none; }

  /* Orders layout */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }
  .admin-table img { width: 44px; height: 44px; border-radius: var(--radius-md); object-fit: cover; border: 1.5px solid var(--border-light); }

  .status-tab-bar { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1.5px solid var(--border-light); padding-bottom: 12px; overflow-x: auto; }
  .status-tab { padding: 6px 16px; border-radius: var(--radius-pill); font-size: .85rem; font-weight: 500; color: var(--text-muted); text-decoration: none; transition: .2s; white-space: nowrap; }
  .status-tab:hover { background: rgba(212,120,154,.08); color: var(--primary); }
  .status-tab.active { background: var(--primary); color: white; }

  /* Details layouts */
  .details-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 28px; }
  .details-label { font-size: .78rem; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; margin-bottom: 4px; }
  .details-val { font-size: .88rem; color: var(--text-dark); margin-bottom: 16px; }

  /* Pagination styling */
  .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 28px; }
  .page-btn { padding: 8px 16px; border-radius: var(--radius-pill); border: 1.5px solid var(--border); font-size: .85rem; background: white; text-decoration: none; color: var(--text-body); transition: .2s; }
  .page-btn:hover, .page-btn.active { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

  @media(max-width:992px) {
    .admin-sidebar { transform: translateX(-100%); }
    .admin-sidebar.show { transform: translateX(0); }
    .admin-main { margin-left: 0; padding: 24px 16px; }
    .admin-mobile-header { display: flex; }
    .admin-topbar { margin-top: 16px; }
    .details-grid { grid-template-columns: 1fr; }
  }
  @media(max-width:576px) {
    .admin-topbar { flex-direction: column; align-items: flex-start; gap: 12px; }
    .admin-topbar div { width: 100%; }
    .admin-topbar .btn { width: 100%; text-align: center; justify-content: center; }
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
    <a href="<?= APP_URL ?>/admin/orders.php" class="sidebar-link active"><i class="fa-solid fa-bag-shopping"></i> Orders
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

    <?php if (!$order): ?>
      <div class="admin-topbar">
        <div>
          <h1>Orders</h1>
          <p>Fulfill client beauty purchases, track COD, and verify GCash receipts.</p>
        </div>
      </div>

      <!-- Status tabs -->
      <div class="status-tab-bar">
        <a href="?status=all" class="status-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">All Orders</a>
        <a href="?status=pending" class="status-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?status=processing" class="status-tab <?= $statusFilter === 'processing' ? 'active' : '' ?>">Processing</a>
        <a href="?status=shipped" class="status-tab <?= $statusFilter === 'shipped' ? 'active' : '' ?>">Shipped</a>
        <a href="?status=delivered" class="status-tab <?= $statusFilter === 'delivered' ? 'active' : '' ?>">Delivered</a>
        <a href="?status=cancelled" class="status-tab <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
      </div>

      <div class="admin-card">
        <!-- Filters bar -->
        <div class="filters-bar">
          <form class="search-form" method="GET" action="<?= APP_URL ?>/admin/orders.php">
            <input type="text" name="search" placeholder="Search by number, buyer name..." value="<?= sanitize($search) ?>">
            <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>"><?php endif; ?>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
          </form>
        </div>

        <?php if (empty($orders)): ?>
          <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
            <i class="fa-solid fa-bag-shopping" style="font-size:2rem;margin-bottom:12px;"></i>
            <p>No orders found matching the filter.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order Number</th>
                <th>Recipient</th>
                <th>Payment</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><a href="?view=<?= $o['id'] ?>" style="font-weight:700;color:var(--primary);text-decoration:none;">#<?= sanitize($o['order_number']) ?></a></td>
                  <td>
                    <div style="font-weight:600;color:var(--text-dark);"><?= sanitize($o['shipping_name']) ?></div>
                    <div style="font-size:.76rem;color:var(--text-muted);"><?= sanitize($o['shipping_email']) ?></div>
                  </td>
                  <td>
                    <span style="font-weight:600;text-transform:uppercase;font-size:.74rem;color:#555;">
                      <?= sanitize($o['payment_method']) ?>
                    </span>
                  </td>
                  <td style="font-weight:600;"><?= formatPrice($o['total']) ?></td>
                  <td><?= getStatusBadge($o['status']) ?></td>
                  <td style="font-size:.8rem;color:var(--text-muted);"><?= formatDate($o['created_at']) ?></td>
                  <td style="text-align:right;">
                    <a href="?view=<?= $o['id'] ?>" class="btn btn-outline btn-sm">Manage</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <!-- Pagination -->
          <?php if ($pagination['pages'] > 1): ?>
            <div class="pagination">
              <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
                  <?= $i ?>
                </a>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <!-- Single Order details view -->
      <div class="admin-topbar">
        <div>
          <h1>Order Detail — #<?= sanitize($order['order_number']) ?></h1>
          <p>Received <?= formatDate($order['created_at'], 'M j, Y — g:i A') ?></p>
        </div>
        <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to Orders
        </a>
      </div>

      <?php if ($error): ?>
        <div class="flash-message flash-error" style="margin-bottom:20px;"><?= $error ?></div>
      <?php endif; ?>

      <div class="details-grid">
        <!-- Purchase items and general billing -->
        <div>
          <div class="admin-card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;color:var(--text-dark);">Items Purchased</h3>
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Price</th>
                  <th style="text-align:center;">Qty</th>
                  <th style="text-align:right;">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orderItems as $item): ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:12px;">
                        <img src="<?= productImage($item['product_image']) ?>" alt="">
                        <div>
                          <div style="font-weight:600;color:var(--text-dark);"><?= sanitize($item['product_name']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?= formatPrice($item['price']) ?></td>
                    <td style="text-align:center;font-weight:600;"><?= $item['quantity'] ?></td>
                    <td style="text-align:right;font-weight:600;"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <div style="margin-top:24px;border-top:1.5px solid var(--border-light);padding-top:20px;max-width:320px;margin-left:auto;">
              <div style="display:flex;justify-content:between;margin-bottom:8px;font-size:.85rem;color:var(--text-muted);">
                <span>Subtotal</span>
                <span style="margin-left:auto;font-weight:600;color:var(--text-dark);"><?= formatPrice($order['subtotal']) ?></span>
              </div>
              <?php if ($order['discount'] > 0): ?>
                <div style="display:flex;justify-content:between;margin-bottom:8px;font-size:.85rem;color:var(--text-muted);">
                  <span>Discount <?= $order['coupon_code'] ? '(Code: ' . sanitize($order['coupon_code']) . ')' : '' ?></span>
                  <span style="margin-left:auto;font-weight:600;color:var(--danger);">-<?= formatPrice($order['discount']) ?></span>
                </div>
              <?php endif; ?>
              <div style="display:flex;justify-content:between;margin-bottom:8px;font-size:.85rem;color:var(--text-muted);">
                <span>Shipping Fee</span>
                <span style="margin-left:auto;font-weight:600;color:var(--text-dark);"><?= formatPrice($order['shipping_fee']) ?></span>
              </div>
              <div style="display:flex;justify-content:between;margin-top:12px;border-top:1.5px solid var(--border);padding-top:12px;font-size:1.05rem;font-weight:700;color:var(--text-dark);">
                <span>Total Amount</span>
                <span style="margin-left:auto;color:var(--primary);"><?= formatPrice($order['total']) ?></span>
              </div>
            </div>
          </div>

          <div class="admin-card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;color:var(--text-dark);">Shipping Address</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
              <div>
                <div class="details-label">Recipient Name</div>
                <div class="details-val"><?= sanitize($order['shipping_name']) ?></div>
                
                <div class="details-label">Phone Number</div>
                <div class="details-val"><?= sanitize($order['shipping_phone']) ?></div>

                <div class="details-label">Email Address</div>
                <div class="details-val"><?= sanitize($order['shipping_email']) ?></div>
              </div>
              <div>
                <div class="details-label">Delivery Destination</div>
                <div class="details-val">
                  <?= sanitize($order['shipping_address']) ?><br>
                  <?= sanitize($order['shipping_city']) ?>, <?= sanitize($order['shipping_province']) ?><br>
                  <?= sanitize($order['shipping_zip']) ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Management controller -->
        <div>
          <div class="admin-card">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;color:var(--text-dark);">Fulfillment Status</h3>
            <form action="?view=<?= $order['id'] ?>" method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="update_status">

              <div class="form-group">
                <label class="form-label" for="o_status">Order Status</label>
                <select name="status" id="o_status" class="form-control" style="padding:10px 16px;">
                  <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                  <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped / In Transit</option>
                  <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                  <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="o_payment">Payment Method</label>
                <div style="font-size:.9rem;font-weight:600;text-transform:uppercase;color:var(--text-dark);">
                  <?= sanitize($order['payment_method']) ?>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="o_ref">GCash Reference ID</label>
                <input type="text" name="gcash_ref" id="o_ref" class="form-control" placeholder="e.g. 901234567890" value="<?= sanitize($order['gcash_ref'] ?? '') ?>">
                <small style="font-size:.76rem;color:var(--text-muted);display:block;margin-top:4px;">Applies to GCash payments verification only.</small>
              </div>

              <div class="form-group">
                <label class="form-label" for="o_notes">Fulfillment Tracking Notes</label>
                <textarea name="notes" id="o_notes" class="form-control" style="min-height:80px;resize:vertical;" placeholder="Courier details, tracking number..."><?= sanitize($order['notes'] ?? '') ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-full" style="margin-top:20px;">
                <i class="fa-solid fa-save"></i> Save Updates
              </button>
            </form>
          </div>

          <div class="admin-card" style="background:#fafafa;">
            <h3 style="font-size:.9rem;font-weight:700;margin-bottom:12px;color:var(--text-dark);">Customer Profile</h3>
            <?php if ($order['user_id']): ?>
              <div style="font-size:.86rem;font-weight:600;"><?= sanitize($order['user_name']) ?></div>
              <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px;"><?= sanitize($order['user_email']) ?></div>
              <a href="<?= APP_URL ?>/admin/users.php?view=<?= $order['user_id'] ?>" class="btn btn-outline btn-sm btn-full" style="text-align:center;justify-content:center;">View Client Profile</a>
            <?php else: ?>
              <div style="font-size:.84rem;color:var(--text-muted);">Guest Checkout Account</div>
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
