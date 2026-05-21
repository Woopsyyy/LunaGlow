<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireAdmin();

$action   = $_GET['action'] ?? 'list';
$productId = sanitizeInt($_GET['id'] ?? 0);
$success  = '';
$error    = '';

// Handle quick-toggles or deletes
if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($action, ['toggle_featured', 'toggle_bestseller', 'toggle_new', 'delete'])) {
    if ($action === 'delete' && $productId) {
        $img = dbFetchColumn("SELECT image FROM products WHERE id = ?", [$productId]);
        if ($img && !str_starts_with($img, 'http') && file_exists(UPLOAD_PATH . $img)) {
            @unlink(UPLOAD_PATH . $img);
        }
        dbQuery("DELETE FROM products WHERE id = ?", [$productId]);
        setFlash('success', 'Product deleted successfully.');
        header('Location: ' . APP_URL . '/admin/products.php');
        exit;
    } elseif ($productId) {
        $col = match ($action) {
            'toggle_featured'   => 'is_featured',
            'toggle_bestseller' => 'is_bestseller',
            'toggle_new'        => 'is_new',
        };
        dbQuery("UPDATE products SET $col = 1 - $col WHERE id = ?", [$productId]);
        setFlash('success', 'Product status updated.');
        header('Location: ' . APP_URL . '/admin/products.php');
        exit;
    }
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = sanitize($_POST['name'] ?? '');
    $categoryId    = sanitizeInt($_POST['category_id'] ?? 0);
    $price         = sanitizeFloat($_POST['price'] ?? 0);
    $originalPrice = sanitizeFloat($_POST['original_price'] ?? 0);
    $description   = sanitize($_POST['description'] ?? '');
    $ingredients   = sanitize($_POST['ingredients'] ?? '');
    $deliveryInfo  = sanitize($_POST['delivery_info'] ?? '3-5 business days');
    $stock         = sanitizeInt($_POST['stock'] ?? 0);
    $isFeatured    = isset($_POST['is_featured']) ? 1 : 0;
    $isBestseller  = isset($_POST['is_bestseller']) ? 1 : 0;
    $isNew         = isset($_POST['is_new']) ? 1 : 0;
    
    if (!$name || !$categoryId || $price <= 0) {
        $error = 'Please fill in a valid product name, category, and price.';
    } else {
        $slug = slugify($name);
        
        // Handle image upload
        $imageName = $_POST['existing_image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $file = $_FILES['image'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'product_' . time() . '.' . $ext;
            
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
                // Ensure slug uniqueness
                $slugCount = dbFetchColumn("SELECT COUNT(*) FROM products WHERE slug = ?", [$slug]);
                if ($slugCount > 0) $slug .= '-' . time();
                
                dbQuery("INSERT INTO products (category_id, name, slug, price, original_price, description, ingredients, delivery_info, stock, image, is_featured, is_bestseller, is_new)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$categoryId, $name, $slug, $price, $originalPrice > 0 ? $originalPrice : null, $description, $ingredients, $deliveryInfo, $stock, $imageName, $isFeatured, $isBestseller, $isNew]);
                setFlash('success', 'Product created successfully.');
                header('Location: ' . APP_URL . '/admin/products.php');
                exit;
            } elseif ($action === 'edit' && $productId) {
                // Ensure slug uniqueness
                $slugCount = dbFetchColumn("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?", [$slug, $productId]);
                if ($slugCount > 0) $slug .= '-' . time();
                
                dbQuery("UPDATE products SET category_id = ?, name = ?, slug = ?, price = ?, original_price = ?, description = ?, ingredients = ?, delivery_info = ?, stock = ?, image = ?, is_featured = ?, is_bestseller = ?, is_new = ? WHERE id = ?",
                    [$categoryId, $name, $slug, $price, $originalPrice > 0 ? $originalPrice : null, $description, $ingredients, $deliveryInfo, $stock, $imageName, $isFeatured, $isBestseller, $isNew, $productId]);
                setFlash('success', 'Product updated successfully.');
                header('Location: ' . APP_URL . '/admin/products.php');
                exit;
            }
        }
    }
}

// Fetch stats and lists for sidebar/rendering
$pendingOrdersCount = (int) dbFetchColumn("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$categories         = dbFetchAll("SELECT * FROM categories ORDER BY name");

// Setup listing filters
$search = sanitize($_GET['search'] ?? '');
$catFilter = sanitize($_GET['category'] ?? '');
$stockFilter = sanitize($_GET['filter'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($catFilter) {
    $where .= " AND c.slug = ?";
    $params[] = $catFilter;
}
if ($stockFilter === 'low_stock') {
    $where .= " AND p.stock <= 5 AND p.stock > 0";
} elseif ($stockFilter === 'out_of_stock') {
    $where .= " AND p.stock = 0";
}

$sql = "SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY p.id DESC";
$pagination = paginate($sql, $params, PRODUCTS_PER_PAGE, $page);
$products = $pagination['rows'];

$admin = dbFetchOne("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Products — Luna Glow Admin</title>
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
  
  .filters-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
  .search-form { display: flex; flex: 1; min-width: 260px; border: 1.5px solid var(--border); border-radius: var(--radius-pill); padding: 6px 14px; background: white; }
  .search-form input { border: none; outline: none; flex: 1; font-size: .85rem; }
  .search-form button { background: none; border: none; color: var(--text-muted); cursor: pointer; }
  .filter-select { padding: 8px 16px; border-radius: var(--radius-pill); border: 1.5px solid var(--border); font-family: inherit; font-size: .85rem; outline: none; }

  /* Product styling */
  .admin-table { width: 100%; border-collapse: collapse; }
  .admin-table th { font-size: .7rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 10px 14px; border-bottom: 1px solid var(--border); text-align: left; }
  .admin-table td { padding: 14px 10px; border-bottom: 1px solid var(--border-light); font-size: .86rem; vertical-align: middle; }
  .admin-table tr:last-child td { border-bottom: none; }
  .admin-table img { width: 44px; height: 44px; border-radius: var(--radius-md); object-fit: cover; border: 1.5px solid var(--border-light); }
  
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
  .img-upload-preview { width: 120px; height: 120px; border-radius: var(--radius-lg); object-fit: cover; border: 1.5px solid var(--border-light); margin-bottom: 12px; display: block; }

  /* Pagination styling */
  .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 28px; }
  .page-btn { padding: 8px 16px; border-radius: var(--radius-pill); border: 1.5px solid var(--border); font-size: .85rem; background: white; text-decoration: none; color: var(--text-body); transition: .2s; }
  .page-btn:hover, .page-btn.active { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

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
    <a href="<?= APP_URL ?>/admin/products.php" class="sidebar-link active"><i class="fa-solid fa-box"></i> Products</a>
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
    
    <?php if ($action === 'list'): ?>
      <div class="admin-topbar">
        <div>
          <h1>Products</h1>
          <p>Browse and manage your boutique's inventory.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/products.php?action=add" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-plus"></i> Add Product
        </a>
      </div>

      <div class="admin-card">
        <!-- Filters -->
        <div class="filters-bar">
          <form class="search-form" method="GET" action="<?= APP_URL ?>/admin/products.php">
            <input type="text" name="search" placeholder="Search by name..." value="<?= sanitize($search) ?>">
            <?php if ($catFilter): ?><input type="hidden" name="category" value="<?= sanitize($catFilter) ?>"><?php endif; ?>
            <?php if ($stockFilter): ?><input type="hidden" name="filter" value="<?= sanitize($stockFilter) ?>"><?php endif; ?>
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
          </form>

          <select class="filter-select" onchange="location = this.value">
            <option value="<?= APP_URL ?>/admin/products.php?search=<?= urlencode($search) ?>">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= APP_URL ?>/admin/products.php?category=<?= urlencode($cat['slug']) ?>&search=<?= urlencode($search) ?>" <?= $catFilter === $cat['slug'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <select class="filter-select" onchange="location = this.value">
            <option value="<?= APP_URL ?>/admin/products.php?category=<?= urlencode($catFilter) ?>&search=<?= urlencode($search) ?>">All Stock Levels</option>
            <option value="<?= APP_URL ?>/admin/products.php?filter=low_stock&category=<?= urlencode($catFilter) ?>&search=<?= urlencode($search) ?>" <?= $stockFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock (<= 5)</option>
            <option value="<?= APP_URL ?>/admin/products.php?filter=out_of_stock&category=<?= urlencode($catFilter) ?>&search=<?= urlencode($search) ?>" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0)</option>
          </select>
        </div>

        <!-- Table -->
        <?php if (empty($products)): ?>
          <div style="text-align:center;padding:48px 24px;color:var(--text-muted);">
            <i class="fa-solid fa-box" style="font-size:2rem;margin-bottom:12px;"></i>
            <p>No products found.</p>
          </div>
        <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th style="text-align:center;">Featured</th>
                <th style="text-align:center;">Bestseller</th>
                <th style="text-align:center;">New</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><img src="<?= productImage($p['image']) ?>" alt=""></td>
                  <td>
                    <div style="font-weight:600;color:var(--text-dark);"><?= sanitize($p['name']) ?></div>
                    <div style="font-size:.74rem;color:var(--text-muted);margin-top:2px;">Slug: <?= sanitize($p['slug']) ?></div>
                  </td>
                  <td><?= sanitize($p['cat_name'] ?? 'Uncategorized') ?></td>
                  <td>
                    <div style="font-weight:600;"><?= formatPrice($p['price']) ?></div>
                    <?php if ($p['original_price'] > 0): ?>
                      <div style="font-size:.76rem;color:var(--text-muted);text-decoration:line-through;"><?= formatPrice($p['original_price']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span style="font-weight:600;color:<?= $p['stock'] === 0 ? 'var(--danger)' : ($p['stock'] <= 5 ? 'var(--warning)' : 'var(--success)') ?>;">
                      <?= $p['stock'] ?>
                    </span>
                  </td>
                  <td style="text-align:center;">
                    <a href="?action=toggle_featured&id=<?= $p['id'] ?>" class="toggle-status-btn <?= $p['is_featured'] ? '' : 'off' ?>" title="Toggle Featured">
                      <i class="fa-solid fa-star"></i>
                    </a>
                  </td>
                  <td style="text-align:center;">
                    <a href="?action=toggle_bestseller&id=<?= $p['id'] ?>" class="toggle-status-btn <?= $p['is_bestseller'] ? '' : 'off' ?>" title="Toggle Bestseller">
                      <i class="fa-solid fa-fire"></i>
                    </a>
                  </td>
                  <td style="text-align:center;">
                    <a href="?action=toggle_new&id=<?= $p['id'] ?>" class="toggle-status-btn <?= $p['is_new'] ? '' : 'off' ?>" title="Toggle New">
                      <i class="fa-solid fa-sparkles"></i>
                    </a>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:6px;">
                      <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                      <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('Are you sure you want to permanently delete this product?');" title="Delete"><i class="fa-regular fa-trash-can"></i></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <!-- Pagination -->
          <?php if ($pagination['pages'] > 1): ?>
            <div class="pagination">
              <?php for ($i = 1; $i <= $pagination['pages']; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($catFilter) ?>&filter=<?= urlencode($stockFilter) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>">
                  <?= $i ?>
                </a>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
      <?php
      $title = $action === 'add' ? 'Add Product' : 'Edit Product';
      $pData = [
          'name' => '', 'category_id' => '', 'price' => '', 'original_price' => '',
          'description' => '', 'ingredients' => '', 'delivery_info' => '3-5 business days',
          'stock' => 50, 'image' => '', 'is_featured' => 0, 'is_bestseller' => 0, 'is_new' => 1
      ];
      if ($action === 'edit' && $productId) {
          $loaded = dbFetchOne("SELECT * FROM products WHERE id = ?", [$productId]);
          if ($loaded) $pData = $loaded;
      }
      ?>
      <div class="admin-topbar">
        <div>
          <h1><?= $title ?></h1>
          <p>Configure details, prices, inventory, and status indicators.</p>
        </div>
        <a href="<?= APP_URL ?>/admin/products.php" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-arrow-left"></i> Back to Products
        </a>
      </div>

      <div class="admin-card" style="max-width:800px;">
        <?php if ($error): ?>
          <div class="flash-message flash-error" style="margin-bottom:20px;"><?= $error ?></div>
        <?php endif; ?>

        <form action="?action=<?= $action ?><?= $productId ? '&id=' . $productId : '' ?>" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="existing_image" value="<?= sanitize($pData['image']) ?>">

          <div class="form-group">
            <label for="p_name" class="form-label">Product Name *</label>
            <input type="text" name="name" id="p_name" class="form-control" value="<?= sanitize($pData['name']) ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="p_category" class="form-label">Category *</label>
              <select name="category_id" id="p_category" class="form-control" style="padding:10px 16px;" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= $pData['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="p_stock" class="form-label">Stock Quantity *</label>
              <input type="number" name="stock" id="p_stock" class="form-control" value="<?= $pData['stock'] ?>" min="0" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="p_price" class="form-label">Price *</label>
              <input type="number" name="price" id="p_price" class="form-control" value="<?= $pData['price'] ?>" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
              <label for="p_orig_price" class="form-label">Original Price (Optional, for sale badges)</label>
              <input type="number" name="original_price" id="p_orig_price" class="form-control" value="<?= $pData['original_price'] ?>" step="0.01" min="0">
            </div>
          </div>

          <div class="form-group">
            <label for="p_image" class="form-label">Product Image</label>
            <?php if ($pData['image']): ?>
              <img src="<?= productImage($pData['image']) ?>" class="img-upload-preview" id="imgPreview" alt="Preview">
            <?php else: ?>
              <img src="" class="img-upload-preview" id="imgPreview" style="display:none;" alt="Preview">
            <?php endif; ?>
            <input type="file" name="image" id="p_image" accept="image/*" onchange="previewImage(this)">
          </div>

          <div class="form-group">
            <label for="p_desc" class="form-label">Product Description</label>
            <textarea name="description" id="p_desc" class="form-control" style="min-height:120px;resize:vertical;"><?= sanitize($pData['description']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="p_ingredients" class="form-label">Ingredients</label>
            <textarea name="ingredients" id="p_ingredients" class="form-control" style="min-height:80px;resize:vertical;" placeholder="e.g., Vitamin E, Shea Butter..."><?= sanitize($pData['ingredients']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="p_delivery" class="form-label">Delivery Timeline</label>
            <input type="text" name="delivery_info" id="p_delivery" class="form-control" value="<?= sanitize($pData['delivery_info']) ?>">
          </div>

          <div style="display:flex;gap:24px;flex-wrap:wrap;margin:24px 0;">
            <div style="display:flex;align-items:center;gap:10px;">
              <input type="checkbox" name="is_featured" id="p_feat" value="1" <?= $pData['is_featured'] ? 'checked' : '' ?>>
              <label for="p_feat" style="font-size:.85rem;font-weight:600;color:var(--text-dark);cursor:pointer;user-select:none;">Featured</label>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <input type="checkbox" name="is_bestseller" id="p_best" value="1" <?= $pData['is_bestseller'] ? 'checked' : '' ?>>
              <label for="p_best" style="font-size:.85rem;font-weight:600;color:var(--text-dark);cursor:pointer;user-select:none;">Best Seller</label>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <input type="checkbox" name="is_new" id="p_new" value="1" <?= $pData['is_new'] ? 'checked' : '' ?>>
              <label for="p_new" style="font-size:.85rem;font-weight:600;color:var(--text-dark);cursor:pointer;user-select:none;">New Arrival</label>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:32px;">
            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="<?= APP_URL ?>/admin/products.php" class="btn btn-outline">Cancel</a>
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
