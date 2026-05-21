<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

// Filters
$categorySlug = sanitize($_GET['category'] ?? '');
$filter       = sanitize($_GET['filter'] ?? '');
$search       = sanitize($_GET['q'] ?? '');
$sort         = sanitize($_GET['sort'] ?? 'newest');
$priceMin     = (float) ($_GET['price_min'] ?? 0);
$priceMax     = (float) ($_GET['price_max'] ?? 9999);
$page         = max(1, (int) ($_GET['page'] ?? 1));

// Build query
$where  = ['p.price >= ? AND p.price <= ?'];
$params = [$priceMin, $priceMax];

if ($categorySlug) {
    $cat = dbFetchOne('SELECT * FROM categories WHERE slug = ?', [$categorySlug]);
    if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat['id']; }
}
if ($filter === 'featured')   { $where[] = 'p.is_featured = 1'; }
if ($filter === 'bestseller') { $where[] = 'p.is_bestseller = 1'; }
if ($filter === 'new')        { $where[] = 'p.is_new = 1'; }
if ($filter === 'sale')       { $where[] = 'p.original_price > 0'; }
if ($search) { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$orderBy = match($sort) {
    'price_low'  => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'rating'     => 'p.rating DESC',
    'popular'    => 'p.review_count DESC',
    default      => 'p.created_at DESC',
};

$whereStr = 'WHERE ' . implode(' AND ', $where);
$baseSql  = "SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id $whereStr ORDER BY $orderBy";

$total      = (int) dbFetchColumn("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id $whereStr", $params);
$perPage    = PRODUCTS_PER_PAGE;
$pages      = max(1, (int) ceil($total / $perPage));
$page       = min($page, $pages);
$offset     = ($page - 1) * $perPage;
$products   = dbFetchAll("$baseSql LIMIT $perPage OFFSET $offset", $params);
$categories = dbFetchAll("SELECT * FROM categories ORDER BY sort_order");

$pageTitle = $categorySlug ? ($cat['name'] ?? 'Shop') : ($filter ? ucfirst($filter) . ' Products' : 'Shop All');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($pageTitle) ?> — Luna Glow</title>
  <meta name="description" content="Shop Luna Glow's premium makeup and skincare collection.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .shop-layout { display: grid; grid-template-columns: 260px 1fr; gap: 40px; padding: 0 var(--section-px) var(--section-py); }
  .shop-sidebar { position: sticky; top: calc(var(--navbar-h) + 20px); height: fit-content; }
  .filter-card { background: white; border-radius: var(--radius-lg); padding: 28px; margin-bottom: 20px; box-shadow: var(--shadow-xs); border: 1px solid var(--border-light); }
  .filter-title { font-size: .78rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--primary); margin-bottom: 18px; }
  .filter-category { display: flex; flex-direction: column; gap: 4px; }
  .filter-category a {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 14px; border-radius: var(--radius-md); font-size: .85rem;
    color: var(--text-body); transition: .2s; text-decoration: none;
  }
  .filter-category a:hover, .filter-category a.active { background: var(--primary-light); color: var(--primary); }
  .filter-category a span { font-size: .76rem; background: var(--beige); padding: 2px 8px; border-radius: var(--radius-pill); }
  .filter-category a.active span { background: var(--primary); color: white; }

  /* Price range */
  .price-range-inputs { display: flex; gap: 8px; align-items: center; }
  .price-range-inputs input { width: 80px; padding: 8px 10px; border: 1.5px solid var(--border); border-radius: var(--radius-md); font-size: .82rem; text-align: center; outline: none; }
  .price-range-inputs input:focus { border-color: var(--primary); }
  .price-range-inputs span { color: var(--text-muted); font-size: .8rem; }

  /* Shop header */
  .shop-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 28px; }
  .shop-header-left h1 { font-size: 1.8rem; margin-bottom: 2px; }
  .shop-header-left p { font-size: .82rem; color: var(--text-muted); }
  .shop-controls { display: flex; align-items: center; gap: 12px; }
  .sort-select { padding: 9px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-pill); font-family: var(--font-sans); font-size: .85rem; outline: none; cursor: pointer; background: white; }
  .sort-select:focus { border-color: var(--primary); }
  .view-toggle { display: flex; gap: 4px; }
  .view-btn { width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; border: 1.5px solid var(--border); background: white; color: var(--text-muted); cursor: pointer; transition: .2s; font-size: .85rem; }
  .view-btn.active, .view-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

  /* Active filters */
  .active-filters { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
  .filter-tag { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; background: var(--primary-light); color: var(--primary); border-radius: var(--radius-pill); font-size: .78rem; font-weight: 500; text-decoration: none; }
  .filter-tag i { font-size: .65rem; cursor: pointer; }
  .clear-all { font-size: .78rem; color: var(--text-muted); text-decoration: none; }
  .clear-all:hover { color: var(--danger); }

  /* Products list view */
  .products-grid.list { grid-template-columns: 1fr; }
  .products-grid.list .product-card { display: grid; grid-template-columns: 200px 1fr; }
  .products-grid.list .product-card-image { height: 180px; }
  .products-grid.list .product-card-hover { transform: none; position: static; padding: 0; margin-top: 12px; }
  .products-grid.list .product-card-hover .btn { width: auto; }

  @media (max-width: 900px) {
    .shop-layout { grid-template-columns: 1fr; }
    .shop-sidebar { position: static; }
  }
  @media (max-width: 600px) {
    .products-grid.list .product-card { grid-template-columns: 1fr; }
    .products-grid.list .product-card-image { height: 220px; }
  }
  </style>
</head>
<body>
<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>

<!-- Page Hero -->
<div class="page-hero">
  <div class="breadcrumb" style="justify-content:center;">
    <a href="<?= APP_URL ?>/index.php">Home</a>
    <span class="breadcrumb-sep"><i class="fa-solid fa-chevron-right" style="font-size:.6rem;"></i></span>
    <span><?= sanitize($pageTitle) ?></span>
  </div>
  <h1><?= sanitize($pageTitle) ?></h1>
  <p><?= $total ?> <?= $total === 1 ? 'product' : 'products' ?> available</p>
</div>

<div class="shop-layout">
  <!-- ── Sidebar ─────────────────────────────────────────── -->
  <aside class="shop-sidebar">
    <form id="filterForm" method="GET" action="shop.php">
      <?php if ($search): ?><input type="hidden" name="q" value="<?= sanitize($search) ?>"><?php endif; ?>
      <input type="hidden" name="sort" value="<?= sanitize($sort) ?>" id="sortHidden">

      <!-- Categories -->
      <div class="filter-card">
        <div class="filter-title">Categories</div>
        <div class="filter-category">
          <a href="<?= APP_URL ?>/shop.php" class="<?= !$categorySlug && !$filter ? 'active' : '' ?>">
            All Products <span><?= dbFetchColumn('SELECT COUNT(*) FROM products') ?></span>
          </a>
          <?php foreach ($categories as $c): ?>
          <?php $cnt = dbFetchColumn('SELECT COUNT(*) FROM products WHERE category_id = ?', [$c['id']]); ?>
          <a href="<?= APP_URL ?>/shop.php?category=<?= urlencode($c['slug']) ?>"
             class="<?= $categorySlug === $c['slug'] ? 'active' : '' ?>">
            <?= sanitize($c['name']) ?> <span><?= $cnt ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Collections -->
      <div class="filter-card">
        <div class="filter-title">Collections</div>
        <div class="filter-category">
          <?php foreach (['featured' => 'Featured', 'bestseller' => 'Best Sellers', 'new' => 'New Arrivals', 'sale' => 'On Sale'] as $k => $v): ?>
          <a href="<?= APP_URL ?>/shop.php?filter=<?= $k ?>" class="<?= $filter === $k ? 'active' : '' ?>">
            <?= $v ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Price Range -->
      <div class="filter-card">
        <div class="filter-title">Price Range</div>
        <div class="price-range-inputs">
          <input type="number" name="price_min" value="<?= $priceMin ?: '' ?>" placeholder="Min" min="0">
          <span>—</span>
          <input type="number" name="price_max" value="<?= $priceMax < 9999 ? $priceMax : '' ?>" placeholder="Max" min="0">
        </div>
        <button type="submit" class="btn btn-primary btn-sm btn-full" style="margin-top:14px;">Apply Filter</button>
      </div>
    </form>
  </aside>

  <!-- ── Main Content ─────────────────────────────────────── -->
  <div>
    <div class="shop-header">
      <div class="shop-header-left">
        <h1><?= sanitize($pageTitle) ?></h1>
        <p>Showing <?= $total ?> results</p>
      </div>
      <div class="shop-controls">
        <select class="sort-select" id="sortSelect" onchange="applySort(this.value)">
          <option value="newest"     <?= $sort==='newest'     ? 'selected':'' ?>>Newest</option>
          <option value="popular"    <?= $sort==='popular'    ? 'selected':'' ?>>Most Popular</option>
          <option value="rating"     <?= $sort==='rating'     ? 'selected':'' ?>>Top Rated</option>
          <option value="price_low"  <?= $sort==='price_low'  ? 'selected':'' ?>>Price: Low to High</option>
          <option value="price_high" <?= $sort==='price_high' ? 'selected':'' ?>>Price: High to Low</option>
        </select>
        <div class="view-toggle">
          <button class="view-btn active" id="gridBtn" onclick="setView('grid')" title="Grid view"><i class="fa-solid fa-grid-2"></i></button>
          <button class="view-btn" id="listBtn" onclick="setView('list')" title="List view"><i class="fa-solid fa-list"></i></button>
        </div>
      </div>
    </div>

    <!-- Active Filters -->
    <?php if ($categorySlug || $filter || $search || $priceMin || $priceMax < 9999): ?>
    <div class="active-filters">
      <span style="font-size:.78rem;color:var(--text-muted);">Active filters:</span>
      <?php if ($categorySlug): ?><a href="<?= APP_URL ?>/shop.php" class="filter-tag"><?= sanitize($cat['name'] ?? '') ?> <i class="fa-solid fa-xmark"></i></a><?php endif; ?>
      <?php if ($filter): ?><a href="<?= APP_URL ?>/shop.php" class="filter-tag"><?= ucfirst($filter) ?> <i class="fa-solid fa-xmark"></i></a><?php endif; ?>
      <?php if ($search): ?><span class="filter-tag">Search: "<?= sanitize($search) ?>" <a href="<?= APP_URL ?>/shop.php" style="color:inherit;"><i class="fa-solid fa-xmark"></i></a></span><?php endif; ?>
      <a href="<?= APP_URL ?>/shop.php" class="clear-all">Clear all</a>
    </div>
    <?php endif; ?>

    <!-- Products Grid -->
    <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa-regular fa-face-sad-cry"></i></div>
      <h3>No products found</h3>
      <p>Try a different filter or search term.</p>
      <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary">Browse All Products</a>
    </div>
    <?php else: ?>
    <div class="products-grid" id="productsGrid">
      <?php foreach ($products as $p): ?>
      <div class="product-card" data-product-id="<?= $p['id'] ?>">
        <div class="product-card-image">
          <img src="<?= productImage($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
          <div class="product-card-badges">
            <?php if ($p['is_new']): ?><span class="badge badge-new">New</span><?php endif; ?>
            <?php if ($p['is_bestseller']): ?><span class="badge badge-bestseller">Best Seller</span><?php endif; ?>
            <?php if ($p['original_price'] > 0): ?><span class="badge badge-sale">Sale</span><?php endif; ?>
          </div>
          <button class="product-card-wishlist" onclick="toggleWishlist(<?= $p['id'] ?>, this)">
            <i class="fa-regular fa-heart"></i>
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
          <div class="product-card-stars">
            <span class="stars">
              <?php for($i=1;$i<=5;$i++) echo $i<=round($p['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
            </span>
            <span class="stars-count">(<?= number_format($p['review_count']) ?>)</span>
          </div>
          <div class="product-card-price">
            <span class="price-current"><?= formatPrice($p['price']) ?></span>
            <?php if ($p['original_price'] > 0): ?>
              <span class="price-original"><?= formatPrice($p['original_price']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1, $page-2); $i <= min($pages, $page+2); $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script src="<?= APP_URL ?>/assets/js/wishlist.js"></script>
<script>
function applySort(value) {
  document.getElementById('sortHidden').value = value;
  const url = new URL(window.location);
  url.searchParams.set('sort', value);
  window.location = url;
}
function setView(v) {
  const grid = document.getElementById('productsGrid');
  document.getElementById('gridBtn').classList.toggle('active', v === 'grid');
  document.getElementById('listBtn').classList.toggle('active', v === 'list');
  grid.classList.toggle('list', v === 'list');
  localStorage.setItem('shopView', v);
}
// Restore view
const savedView = localStorage.getItem('shopView');
if (savedView) setView(savedView);
</script>
</body>
</html>
