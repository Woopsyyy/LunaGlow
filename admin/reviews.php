<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$tab      = $_GET['tab'] ?? 'pending';
$action   = $_GET['action'] ?? '';
$reviewId = sanitizeInt($_GET['id'] ?? 0);
$success  = '';
$error    = '';

// Helper to recalculate average ratings and count for a product
function recalculateProductRatings(int $productId): void {
    $stats = dbFetchOne("
        SELECT 
            COUNT(*) AS cnt,
            COALESCE(AVG(rating), 4.50) AS avg_rating
        FROM reviews
        WHERE product_id = ? AND is_approved = 1
    ", [$productId]);

    $cnt = (int) $stats['cnt'];
    $avg = (float) $stats['avg_rating'];

    dbQuery("UPDATE products SET rating = ?, review_count = ? WHERE id = ?", [$avg, $cnt, $productId]);
}

// Fetch stats for order count (sidebar indicator)
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

// Handle approval
if ($action === 'approve' && $reviewId) {
    // Verify CSRF or since it's a GET action, let's verify csrf from a query parameter or just proceed with confirmation
    // The implementation plan says: "Every form and destructive quick-action link will include a CSRF token checks"
    // So let's check for csrf_token in GET request for action triggers
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $review = dbFetchOne("SELECT product_id FROM reviews WHERE id = ?", [$reviewId]);
        if ($review) {
            dbQuery("UPDATE reviews SET is_approved = 1 WHERE id = ?", [$reviewId]);
            recalculateProductRatings((int) $review['product_id']);
            setFlash('success', 'Review approved successfully.');
        }
    }
    header('Location: ' . APP_URL . '/admin/reviews.php?tab=' . $tab);
    exit;
}

// Handle reject / delete
if ($action === 'delete' && $reviewId) {
    if (!verifyCsrf($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $review = dbFetchOne("SELECT product_id FROM reviews WHERE id = ?", [$reviewId]);
        if ($review) {
            dbQuery("DELETE FROM reviews WHERE id = ?", [$reviewId]);
            recalculateProductRatings((int) $review['product_id']);
            setFlash('success', 'Review rejected and deleted.');
        }
    }
    header('Location: ' . APP_URL . '/admin/reviews.php?tab=' . $tab);
    exit;
}

// Pagination setup
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage = 15;

if ($tab === 'approved') {
    $countSql = "SELECT COUNT(*) FROM reviews WHERE is_approved = 1";
    $listSql  = "SELECT r.*, p.name AS prod_name, p.image AS prod_image, p.slug AS prod_slug 
                 FROM reviews r 
                 JOIN products p ON p.id = r.product_id 
                 WHERE r.is_approved = 1 
                 ORDER BY r.created_at DESC";
} else {
    $tab = 'pending';
    $countSql = "SELECT COUNT(*) FROM reviews WHERE is_approved = 0";
    $listSql  = "SELECT r.*, p.name AS prod_name, p.image AS prod_image, p.slug AS prod_slug 
                 FROM reviews r 
                 JOIN products p ON p.id = r.product_id 
                 WHERE r.is_approved = 0 
                 ORDER BY r.created_at DESC";
}

$totalRows = (int) dbFetchColumn($countSql);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$reviews = dbFetchAll("$listSql LIMIT $perPage OFFSET $offset");

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Moderation & Reviews — Luna Glow Admin</title>
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

  /* Tabs styling */
  .tab-nav { display: flex; gap: 8px; border-bottom: 1.5px solid var(--border-light); margin-bottom: 24px; padding-bottom: 2px; }
  .tab-btn { padding: 10px 20px; font-size: .88rem; font-weight: 600; color: var(--text-muted); text-decoration: none; border-bottom: 2.5px solid transparent; transition: .2s; }
  .tab-btn:hover { color: var(--primary); }
  .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
  
  /* Table styling */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 16px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: top; }
  .admin-table tr:last-child td { border-bottom: none; }
  
  .review-prod-img { width: 50px; height: 50px; object-fit: cover; border-radius: var(--radius-md); border: 1.5px solid var(--border-light); }
  .review-stars { color: #f59e0b; font-size: .8rem; display: flex; gap: 2px; }
  .review-body { font-size: .82rem; color: var(--text-body); line-height: 1.5; margin-top: 6px; }

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
    <a href="<?= APP_URL ?>/admin/reviews.php" class="sidebar-link active"><i class="fa-regular fa-star"></i> Reviews</a>
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

    <div class="admin-topbar">
      <div>
        <h1>Reviews Moderation</h1>
        <p>Review customer feedback, approve ratings, and manage public reviews listings.</p>
      </div>
    </div>

    <!-- Tabs Nav -->
    <div class="tab-nav">
      <a href="?tab=pending" class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>">
        Pending Submissions
      </a>
      <a href="?tab=approved" class="tab-btn <?= $tab === 'approved' ? 'active' : '' ?>">
        Approved Reviews
      </a>
    </div>

    <div class="admin-card">
      <?php if (empty($reviews)): ?>
        <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
          <i class="fa-regular fa-star" style="font-size:2rem;margin-bottom:12px;"></i>
          <p>No reviews found in this queue.</p>
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width:70px;">Product</th>
              <th style="width:180px;">Details</th>
              <th>Reviewer & Rating</th>
              <th>Review Content</th>
              <th style="text-align:right;width:160px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reviews as $r): ?>
              <tr>
                <td>
                  <a href="<?= APP_URL ?>/product.php?slug=<?= $r['prod_slug'] ?>" target="_blank">
                    <img src="<?= productImage($r['prod_image']) ?>" class="review-prod-img" alt="">
                  </a>
                </td>
                <td>
                  <a href="<?= APP_URL ?>/product.php?slug=<?= $r['prod_slug'] ?>" target="_blank" style="text-decoration:none;font-weight:600;color:var(--text-dark);font-size:.84rem;">
                    <?= sanitize($r['prod_name']) ?>
                  </a>
                  <div style="font-size:.76rem;color:var(--text-muted);margin-top:2px;">Submitted: <?= formatDate($r['created_at'], 'M j, Y g:i A') ?></div>
                </td>
                <td>
                  <div style="font-weight:600;color:var(--text-dark);"><?= sanitize($r['reviewer_name'] ?: 'Anonymous') ?></div>
                  <div class="review-stars" style="margin-top:4px;">
                    <?= starRating((float) $r['rating']) ?>
                  </div>
                </td>
                <td>
                  <div style="font-weight:600;color:var(--text-dark);font-size:.85rem;"><?= sanitize($r['title']) ?></div>
                  <div class="review-body"><?= nl2br(sanitize($r['body'])) ?></div>
                </td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:6px;">
                    <?php if ($r['is_approved'] == 0): ?>
                      <a href="?action=approve&id=<?= $r['id'] ?>&tab=<?= $tab ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-primary btn-sm" style="padding:6px 14px;font-size:.78rem;" title="Approve Review">
                        <i class="fa-solid fa-check"></i> Approve
                      </a>
                      <a href="?action=delete&id=<?= $r['id'] ?>&tab=<?= $tab ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:rgba(239,68,68,.2);background:none;padding:6px 10px;font-size:.78rem;" onclick="return confirm('Are you sure you want to reject and delete this review?');" title="Reject Review">
                        <i class="fa-solid fa-xmark"></i>
                      </a>
                    <?php else: ?>
                      <a href="?action=delete&id=<?= $r['id'] ?>&tab=<?= $tab ?>&csrf_token=<?= csrfToken() ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:rgba(239,68,68,.2);background:none;padding:6px 10px;font-size:.78rem;" onclick="return confirm('Are you sure you want to permanently delete this approved review? This will update the average star calculations.');" title="Delete Review">
                        <i class="fa-regular fa-trash-can"></i> Delete
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?tab=<?= $tab ?>&page=<?= $i ?>" class="page-link <?= $page === $i ? 'active' : '' ?>">
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
