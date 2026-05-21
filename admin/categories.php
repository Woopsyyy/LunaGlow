<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action     = $_GET['action'] ?? 'list';
$categoryId = sanitizeInt($_GET['id'] ?? 0);
$success    = '';
$error      = '';

// Handle Delete Category
if ($action === 'delete' && $categoryId) {
    $img = dbFetchColumn("SELECT image FROM categories WHERE id = ?", [$categoryId]);
    if ($img && !str_starts_with($img, 'http') && file_exists(UPLOAD_PATH . $img)) {
        @unlink(UPLOAD_PATH . $img);
    }
    dbQuery("DELETE FROM categories WHERE id = ?", [$categoryId]);
    setFlash('success', 'Category deleted successfully.');
    header('Location: ' . APP_URL . '/admin/categories.php');
    exit;
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $sortOrder   = sanitizeInt($_POST['sort_order'] ?? 0);
    
    if (!$name) {
        $error = 'Please enter a valid category name.';
    } else {
        $slug = slugify($name);
        
        // Handle image upload
        $imageName = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'category_' . time() . '.' . $ext;
            
            if ($file['size'] <= MAX_FILE_SIZE && in_array($file['type'], ALLOWED_TYPES)) {
                if (!is_dir(UPLOAD_PATH)) {
                    mkdir(UPLOAD_PATH, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], UPLOAD_PATH . $filename)) {
                    if ($imageName && !str_starts_with($imageName, 'http') && file_exists(UPLOAD_PATH . $imageName)) {
                        @unlink(UPLOAD_PATH . $imageName);
                    }
                    $imageName = $filename;
                }
            } else {
                $error = 'Invalid image file. Only JPG, PNG, WEBP under 5MB are allowed.';
            }
        }
        
        if (empty($error)) {
            if ($action === 'add') {
                // Check slug uniqueness
                $slugCount = dbFetchColumn("SELECT COUNT(*) FROM categories WHERE slug = ?", [$slug]);
                if ($slugCount > 0) $slug .= '-' . time();

                dbQuery("INSERT INTO categories (name, slug, description, image, sort_order) VALUES (?, ?, ?, ?, ?)",
                    [$name, $slug, $description, $imageName, $sortOrder]);
                setFlash('success', 'Category created successfully.');
                header('Location: ' . APP_URL . '/admin/categories.php');
                exit;
            } elseif ($action === 'edit' && $categoryId) {
                // Check slug uniqueness
                $slugCount = dbFetchColumn("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?", [$slug, $categoryId]);
                if ($slugCount > 0) $slug .= '-' . time();

                dbQuery("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, sort_order = ? WHERE id = ?",
                    [$name, $slug, $description, $imageName, $sortOrder, $categoryId]);
                setFlash('success', 'Category updated successfully.');
                header('Location: ' . APP_URL . '/admin/categories.php');
                exit;
            }
        }
    }
}

// Fetch stats and lists for rendering
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$categories         = dbFetchAll("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id 
    GROUP BY c.id 
    ORDER BY c.sort_order, c.name
");

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Categories — Luna Glow Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  :root { --sidebar-w: 260px; }
  body { background: var(--cream); }
  .admin-layout { display: flex; min-height: 100vh; }

  /* Sidebar styling matching dashboard */
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

  /* ── Sidebar Overlay ── */
  .admin-sidebar-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(42,31,40,.4);
    backdrop-filter: blur(4px);
    z-index: 999;
    opacity: 0; pointer-events: none;
    transition: opacity var(--transition);
  }
  .admin-sidebar-overlay.show { opacity: 1; pointer-events: auto; }

  /* ── Mobile Header ── */
  .admin-mobile-header {
    display: none;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0;
    z-index: 900;
    width: 100%;
    box-shadow: var(--shadow-xs);
  }
  .mobile-toggle-btn {
    font-size: 1.25rem;
    color: var(--text-dark);
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: var(--radius-sm);
    transition: var(--transition-fast);
    display: flex; align-items: center; justify-content: center;
  }
  .mobile-toggle-btn:hover { background: var(--primary-glow); color: var(--primary); }
  .mobile-logo { font-family: var(--font-serif); font-size: 1.25rem; font-weight: 700; color: var(--primary); }

  .admin-main { flex: 1; margin-left: var(--sidebar-w); padding: 32px; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }
  .admin-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
  .admin-topbar h1 { font-size: 1.8rem; color: var(--text-dark); }
  .admin-card { background: white; border-radius: var(--radius-xl); padding: 28px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); margin-bottom: 24px; }
  
  /* Category styling */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }
  .admin-table img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 1.5px solid var(--border-light); }

  /* Form */
  .form-group { margin-bottom: 20px; }
  .form-label { display: block; font-size: .84rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; }
  .form-control { width: 100%; padding: 11px 16px; border-radius: var(--radius-md); border: 1.5px solid var(--border); font-family: inherit; font-size: .88rem; color: var(--text-dark); transition: .2s; }
  .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px var(--primary-glow); }
  .img-upload-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 1.5px solid var(--border-light); margin-bottom: 12px; display: block; }

  /* Responsive breakpoints */
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
    <a href="<?= APP_URL ?>/admin/categories.php" class="sidebar-link active"><i class="fa-solid fa-tags"></i> Categories</a>
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
    
    <?php if ($action === 'list'): ?>
      <div class="admin-topbar">
        <div>
          <h1>Categories</h1>
          <p>Organize makeup items into distinct catalog segments.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/categories.php?action=add" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Add Category
        </a>
      </div>

      <div class="admin-card">
        <?php if (empty($categories)): ?>
          <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
            <i class="fa-solid fa-tags" style="font-size:2rem;margin-bottom:12px;"></i>
            <p>No categories found.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Thumbnail</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Sort Order</th>
                <th>Products Count</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $cat): ?>
                <tr>
                  <td><img src="<?= productImage($cat['image']) ?>" alt=""></td>
                  <td style="font-weight:600;color:var(--text-dark);"><?= sanitize($cat['name']) ?></td>
                  <td><code><?= sanitize($cat['slug']) ?></code></td>
                  <td style="max-width:300px;font-size:.82rem;color:var(--text-muted);"><?= sanitize(truncate($cat['description'] ?? '', 80)) ?></td>
                  <td style="font-weight:600;"><?= $cat['sort_order'] ?></td>
                  <td><span class="badge" style="background:var(--primary-light);color:var(--primary);font-weight:600;"><?= $cat['product_count'] ?> products</span></td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:6px;">
                      <a href="?action=edit&id=<?= $cat['id'] ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                      <a href="?action=delete&id=<?= $cat['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('Are you sure you want to permanently delete this category? All products under it will be set to Uncategorized.');" title="Delete"><i class="fa-regular fa-trash-can"></i></a>
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
      $title = $action === 'add' ? 'Add Category' : 'Edit Category';
      $catData = ['name' => '', 'description' => '', 'image' => '', 'sort_order' => 0];
      if ($action === 'edit' && $categoryId) {
          $loaded = dbFetchOne("SELECT * FROM categories WHERE id = ?", [$categoryId]);
          if ($loaded) $catData = $loaded;
      }
      ?>
      <div class="admin-topbar">
        <div>
          <h1><?= $title ?></h1>
          <p>Set category details, sorting prioritization, and catalog graphics.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/categories.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to Categories
        </a>
      </div>

      <div class="admin-card" style="max-width:600px;">
        <?php if ($error): ?>
          <div class="flash-message flash-error" style="margin-bottom:20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="?action=<?= $action ?><?= $categoryId ? '&id=' . $categoryId : '' ?>" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="existing_image" value="<?= sanitize($catData['image']) ?>">

          <div class="form-group">
            <label for="c_name" class="form-label">Category Name *</label>
            <input type="text" name="name" id="c_name" class="form-control" value="<?= sanitize($catData['name']) ?>" required>
          </div>

          <div class="form-group">
            <label for="c_sort" class="form-label">Sort Order</label>
            <input type="number" name="sort_order" id="c_sort" class="form-control" value="<?= $catData['sort_order'] ?>" min="0">
          </div>

          <div class="form-group">
            <label for="c_image" class="form-label">Category Image / Thumbnail</label>
            <?php if ($catData['image']): ?>
              <img src="<?= productImage($catData['image']) ?>" class="img-upload-preview" id="imgPreview" alt="Preview">
            <?php else: ?>
              <img src="" class="img-upload-preview" id="imgPreview" style="display:none;" alt="Preview">
            <?php endif; ?>
            <input type="file" name="image" id="c_image" accept="image/*" onchange="previewImage(this)">
          </div>

          <div class="form-group">
            <label for="c_desc" class="form-label">Description</label>
            <textarea name="description" id="c_desc" class="form-control" style="min-height:100px;resize:vertical;"><?= sanitize($catData['description']) ?></textarea>
          </div>

          <div style="display:flex;gap:12px;margin-top:32px;">
            <button type="submit" class="btn btn-primary">Save Category</button>
            <a href="<?= APP_URL ?>/admin/categories.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>

      <script>
      function previewImage(input) {
        if (input.files && input.files[0]) {
          const reader = new FileReader();
          reader.onload = function(e) {
            const img = document.getElementById('imgPreview');
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
