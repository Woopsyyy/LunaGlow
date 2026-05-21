<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action   = $_GET['action'] ?? 'list';
$bannerId = sanitizeInt($_GET['id'] ?? 0);
$success  = '';
$error    = '';

// Fetch stats for order count (sidebar indicator)
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");

// Handle delete
if ($action === 'delete' && $bannerId) {
    $img = dbFetchColumn("SELECT image FROM banners WHERE id = ?", [$bannerId]);
    if ($img && !str_starts_with($img, 'http') && file_exists(UPLOAD_PATH . $img)) {
        @unlink(UPLOAD_PATH . $img);
    }
    dbQuery("DELETE FROM banners WHERE id = ?", [$bannerId]);
    setFlash('success', 'Banner deleted successfully.');
    header('Location: ' . APP_URL . '/admin/banners.php');
    exit;
}

// Handle toggle status
if ($action === 'toggle_status' && $bannerId) {
    dbQuery("UPDATE banners SET is_active = 1 - is_active WHERE id = ?", [$bannerId]);
    setFlash('success', 'Banner status updated.');
    header('Location: ' . APP_URL . '/admin/banners.php');
    exit;
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $title     = sanitize($_POST['title'] ?? '');
        $subtitle  = sanitize($_POST['subtitle'] ?? '');
        $badge     = sanitize($_POST['badge'] ?? '');
        $ctaText   = sanitize($_POST['cta_text'] ?? '');
        $ctaLink   = sanitize($_POST['cta_link'] ?? '');
        $sortOrder = sanitizeInt($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        // Image upload handling
        $imageName = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'banner_' . time() . '.' . $ext;

            if ($file['size'] <= MAX_FILE_SIZE && in_array($file['type'], ALLOWED_TYPES)) {
                if (!is_dir(UPLOAD_PATH)) {
                    mkdir(UPLOAD_PATH, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename)) {
                    if ($imageName && !str_starts_with($imageName, 'http') && file_exists(UPLOAD_PATH . $imageName)) {
                        @unlink(UPLOAD_PATH . $imageName);
                    }
                    $imageName = $filename;
                } else {
                    $error = 'Failed to save uploaded image file.';
                }
            } else {
                $error = 'Invalid image file. Only JPG, PNG, WEBP under 5MB are allowed.';
            }
        }

        if (empty($error)) {
            if ($action === 'add') {
                if (!$imageName) {
                    $error = 'Please upload a background image for the new banner.';
                } else {
                    dbQuery("INSERT INTO banners (title, subtitle, badge, cta_text, cta_link, image, sort_order, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [$title, $subtitle, $badge, $ctaText, $ctaLink, $imageName, $sortOrder, $isActive]);
                    setFlash('success', 'Banner created successfully.');
                    header('Location: ' . APP_URL . '/admin/banners.php');
                    exit;
                }
            } elseif ($action === 'edit' && $bannerId) {
                dbQuery("UPDATE banners SET title = ?, subtitle = ?, badge = ?, cta_text = ?, cta_link = ?, image = ?, sort_order = ?, is_active = ? WHERE id = ?",
                    [$title, $subtitle, $badge, $ctaText, $ctaLink, $imageName, $sortOrder, $isActive, $bannerId]);
                setFlash('success', 'Banner updated successfully.');
                header('Location: ' . APP_URL . '/admin/banners.php');
                exit;
            }
        }
    }
}

// Fetch banner list
$banners = dbFetchAll("SELECT * FROM banners ORDER BY sort_order, id DESC");

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Hero Banners — Luna Glow Admin</title>
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

  /* Banners custom styling */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }
  
  .banner-img-preview-table { width: 120px; height: 60px; object-fit: cover; border-radius: var(--radius-sm); border: 1.5px solid var(--border-light); }
  
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
  
  .img-upload-banner { width: 100%; max-width: 400px; height: 180px; object-fit: cover; border-radius: var(--radius-md); border: 1.5px solid var(--border-light); margin-bottom: 12px; display: block; background: #faf8f9; }

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
    <a href="<?= APP_URL ?>/admin/banners.php" class="sidebar-link active"><i class="fa-solid fa-image"></i> Banners</a>
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

    <?php if ($action === 'list'): ?>
      <div class="admin-topbar">
        <div>
          <h1>Hero Banners</h1>
          <p>Administer the landing page slideshow headers dynamically.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/banners.php?action=add" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Add Banner Slide
        </a>
      </div>

      <div class="admin-card">
        <?php if (empty($banners)): ?>
          <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
            <i class="fa-solid fa-image" style="font-size:2rem;margin-bottom:12px;"></i>
            <p>No banners slides currently exist.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Slide Background</th>
                <th>Badge & Title</th>
                <th>CTA Details</th>
                <th>Order</th>
                <th style="text-align:center;">Active</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($banners as $b): ?>
                <tr>
                  <td><img src="<?= productImage($b['image']) ?>" class="banner-img-preview-table" alt=""></td>
                  <td>
                    <?php if ($b['badge']): ?>
                      <span class="badge" style="background:var(--primary-glow);color:var(--primary);font-size:.68rem;padding:2px 8px;margin-bottom:4px;display:inline-block;font-weight:600;"><?= sanitize($b['badge']) ?></span>
                    <?php endif; ?>
                    <div style="font-weight:600;color:var(--text-dark);font-size:.92rem;"><?= sanitize($b['title']) ?></div>
                    <div style="color:var(--text-muted);font-size:.78rem;max-width:250px;"><?= sanitize(truncate($b['subtitle'], 70)) ?></div>
                  </td>
                  <td>
                    <?php if ($b['cta_text']): ?>
                      <div style="font-weight:500;font-size:.84rem;color:var(--text-dark);"><i class="fa-solid fa-arrow-pointer" style="font-size:.76rem;margin-right:4px;"></i> <?= sanitize($b['cta_text']) ?></div>
                      <div style="font-size:.74rem;color:var(--text-muted);max-width:180px;word-break:break-all;"><code><?= sanitize($b['cta_link']) ?></code></div>
                    <?php else: ?>
                      <span style="color:var(--text-muted);font-size:.78rem;">None</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-weight:600;"><?= $b['sort_order'] ?></td>
                  <td style="text-align:center;">
                    <a href="?action=toggle_status&id=<?= $b['id'] ?>" class="toggle-status-btn <?= $b['is_active'] ? '' : 'off' ?>" title="Toggle Active">
                      <i class="fa-solid fa-power-off"></i>
                    </a>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:6px;">
                      <a href="?action=edit&id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                      <a href="?action=delete&id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('Are you sure you want to permanently delete this banner slide? This will remove its background image asset.');" title="Delete"><i class="fa-regular fa-trash-can"></i></a>
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
      $title = $action === 'add' ? 'Add Banner Slide' : 'Edit Banner Slide';
      $bData = ['title' => '', 'subtitle' => '', 'badge' => '', 'cta_text' => '', 'cta_link' => '', 'image' => '', 'sort_order' => 0, 'is_active' => 1];
      if ($action === 'edit' && $bannerId) {
          $loaded = dbFetchOne("SELECT * FROM banners WHERE id = ?", [$bannerId]);
          if ($loaded) $bData = $loaded;
      }
      ?>
      <div class="admin-topbar">
        <div>
          <h1><?= $title ?></h1>
          <p>Set custom hero captions, call to action destinations, and backdrop slide graphics.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/banners.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to Banners
        </a>
      </div>

      <div class="admin-card" style="max-width:640px;">
        <?php if ($error): ?>
          <div class="flash-message flash-error" style="margin-bottom:20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="?action=<?= $action ?><?= $bannerId ? '&id=' . $bannerId : '' ?>" method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="existing_image" value="<?= sanitize($bData['image']) ?>">

          <div class="form-row">
            <div class="form-group">
              <label for="b_title" class="form-label">Slide Title</label>
              <input type="text" name="title" id="b_title" class="form-control" placeholder="e.g. Velvet Lip Matte Series" value="<?= sanitize($bData['title']) ?>">
            </div>
            <div class="form-group">
              <label for="b_badge" class="form-label">Badge Highlight Tag</label>
              <input type="text" name="badge" id="b_badge" class="form-control" placeholder="e.g. New Launch, 20% OFF" value="<?= sanitize($bData['badge']) ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="b_subtitle" class="form-label">Slide Subtitle / Description</label>
            <textarea name="subtitle" id="b_subtitle" class="form-control" style="min-height:70px;resize:vertical;" placeholder="A compelling subtext details layout description..."><?= sanitize($bData['subtitle']) ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="b_cta_text" class="form-label">CTA Button Label</label>
              <input type="text" name="cta_text" id="b_cta_text" class="form-control" placeholder="e.g. Shop Collection" value="<?= sanitize($bData['cta_text']) ?>">
            </div>
            <div class="form-group">
              <label for="b_cta_link" class="form-label">CTA Redirect Destination Link</label>
              <input type="text" name="cta_link" id="b_cta_link" class="form-control" placeholder="e.g. /shop.php?category=lipstick" value="<?= sanitize($bData['cta_link']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="b_sort" class="form-label">Sequence Sort Order</label>
              <input type="number" name="sort_order" id="b_sort" class="form-control" value="<?= $bData['sort_order'] ?>" min="0">
            </div>
            <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:12px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_active" id="b_active" value="1" <?= $bData['is_active'] ? 'checked' : '' ?>>
                <label for="b_active" style="font-size:.85rem;font-weight:600;color:var(--text-dark);cursor:pointer;user-select:none;">Slide Active / Published</label>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Backdrop Background Image *</label>
            <?php if ($bData['image']): ?>
              <img src="<?= productImage($bData['image']) ?>" class="img-upload-banner" id="bannerPreview" alt="Preview">
            <?php else: ?>
              <img src="" class="img-upload-banner" id="bannerPreview" style="display:none;" alt="Preview">
            <?php endif; ?>
            <input type="file" name="image" id="b_image" accept="image/*" onchange="previewBanner(this)" <?= $action === 'add' ? 'required' : '' ?>>
            <p style="font-size:.74rem;color:var(--text-muted);margin-top:6px;"><i class="fa-solid fa-circle-info"></i> Standard recommended resolution is 1920x800. Maximum size 5MB (JPG, PNG, WEBP).</p>
          </div>

          <div style="display:flex;gap:12px;margin-top:32px;">
            <button type="submit" class="btn btn-primary">Save Slide Banner</button>
            <a href="<?= APP_URL ?>/admin/banners.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>

      <script>
      function previewBanner(input) {
        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            const img = document.getElementById('bannerPreview');
            img.src = e.target.result;
            img.style.display = 'block';
          }
          reader.readAsDataURL(input.files[0]);
        }
      }
      </script>
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
