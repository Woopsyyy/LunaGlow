<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/shop.php'); exit; }

$product = dbFetchOne("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?", [$id]);
if (!$product) { header('Location: ' . APP_URL . '/shop.php'); exit; }

$images   = dbFetchAll("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order", [$id]);
$reviews  = dbFetchAll("SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC LIMIT 6", [$id]);
$related  = dbFetchAll("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.category_id = ? AND p.id != ? LIMIT 4", [$product['category_id'], $id]);

// Check if in wishlist
$inWishlist = false;
if (isLoggedIn()) {
    $inWishlist = (bool) dbFetchColumn("SELECT COUNT(*) FROM wishlist_items WHERE user_id = ? AND product_id = ?", [$_SESSION['user_id'], $id]);
}
$discount = $product['original_price'] > 0 ? round((1 - $product['price'] / $product['original_price']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($product['name']) ?> — Luna Glow</title>
  <meta name="description" content="<?= sanitize(truncate($product['description'], 150)) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .product-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 72px;
    padding: calc(var(--navbar-h) + 48px) var(--section-px) var(--section-py);
    max-width: var(--container);
    margin: 0 auto;
  }
  /* Gallery */
  .gallery-main {
    position: relative;
    border-radius: var(--radius-xl);
    overflow: hidden;
    aspect-ratio: 1;
    background: var(--beige);
    cursor: zoom-in;
  }
  .gallery-main img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform .4s ease;
    user-select: none;
  }
  .gallery-main:hover img { transform: scale(1.05); }
  .gallery-discount-badge {
    position: absolute; top: 20px; left: 20px;
    background: var(--danger); color: white;
    padding: 6px 14px;
    border-radius: var(--radius-pill);
    font-size: .78rem; font-weight: 700;
  }
  .gallery-thumbs { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; }
  .gallery-thumb {
    width: 72px; height: 72px;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 2px solid transparent;
    cursor: pointer;
    transition: .2s;
    background: var(--beige);
  }
  .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .gallery-thumb.active, .gallery-thumb:hover { border-color: var(--primary); }

  /* Product Info */
  .product-info-category {
    font-size: .72rem; font-weight: 700;
    letter-spacing: 2px; text-transform: uppercase;
    color: var(--primary); margin-bottom: 10px;
    text-decoration: none; display: inline-block;
  }
  .product-info-category:hover { opacity: .75; }
  .product-info h1 {
    font-size: clamp(1.8rem, 3vw, 2.6rem);
    margin-bottom: 14px;
  }
  .product-rating-row { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
  .product-review-count { font-size: .84rem; color: var(--primary); text-decoration: underline; cursor: pointer; }
  .product-stock { font-size: .82rem; color: var(--success); font-weight: 500; }
  .product-stock.low { color: var(--warning); }
  .product-stock.out { color: var(--danger); }
  .product-price-row { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
  .product-price { font-size: 2.2rem; font-family: var(--font-serif); font-weight: 700; color: var(--text-dark); }
  .product-original { font-size: 1.2rem; color: var(--text-muted); text-decoration: line-through; }
  .product-save { background: var(--primary-light); color: var(--primary-dark); padding: 4px 12px; border-radius: var(--radius-pill); font-size: .78rem; font-weight: 700; }
  .product-desc { font-size: .92rem; line-height: 1.8; color: var(--text-body); margin-bottom: 28px; }

  /* Quantity & Actions */
  .qty-section { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
  .qty-label { font-size: .82rem; font-weight: 600; color: var(--text-dark); width: 70px; }
  .qty-control {
    display: flex; align-items: center; gap: 0;
    border: 1.5px solid var(--border); border-radius: var(--radius-pill);
    overflow: hidden;
  }
  .qty-control button {
    width: 40px; height: 42px;
    background: var(--beige);
    border: none; cursor: pointer;
    font-size: 1rem; color: var(--text-dark);
    transition: .2s;
    display: flex; align-items: center; justify-content: center;
  }
  .qty-control button:hover { background: var(--primary-light); color: var(--primary); }
  .qty-control span { width: 44px; text-align: center; font-weight: 600; font-size: .95rem; }
  .product-actions { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
  .btn-add-cart { flex: 1; min-width: 200px; }
  .btn-wishlist { width: 52px; height: 52px; border-radius: 50%; border: 1.5px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: var(--text-muted); transition: .2s; flex-shrink: 0; }
  .btn-wishlist:hover, .btn-wishlist.active { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

  /* Info accordion */
  .product-accordion { border-top: 1px solid var(--border); }
  .accordion-item { border-bottom: 1px solid var(--border); }
  .accordion-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 0; cursor: pointer;
    font-size: .88rem; font-weight: 600; color: var(--text-dark);
    user-select: none;
  }
  .accordion-header i { transition: .3s; font-size: .75rem; color: var(--text-muted); }
  .accordion-item.open .accordion-header i { transform: rotate(180deg); }
  .accordion-body { display: none; padding-bottom: 16px; font-size: .88rem; line-height: 1.8; color: var(--text-body); }
  .accordion-item.open .accordion-body { display: block; }

  /* Trust icons */
  .product-trust { display: flex; gap: 20px; padding: 20px 0; border-top: 1px solid var(--border); flex-wrap: wrap; }
  .trust-icon { display: flex; align-items: center; gap: 8px; font-size: .78rem; color: var(--text-muted); }
  .trust-icon i { color: var(--primary); }

  /* Reviews */
  .review-summary { display: flex; align-items: center; gap: 48px; padding: 32px; background: white; border-radius: var(--radius-xl); border: 1px solid var(--border-light); margin-bottom: 28px; }
  .review-big-num { font-size: 4rem; font-family: var(--font-serif); font-weight: 700; color: var(--text-dark); line-height: 1; }
  .review-stars-big { font-size: 1.4rem; margin-bottom: 6px; }
  .review-total { font-size: .84rem; color: var(--text-muted); }
  .review-bars { flex: 1; display: flex; flex-direction: column; gap: 6px; }
  .review-bar-row { display: flex; align-items: center; gap: 10px; font-size: .78rem; }
  .review-bar-row span:first-child { width: 30px; text-align: right; }
  .review-bar { flex: 1; height: 6px; background: var(--beige); border-radius: 3px; overflow: hidden; }
  .review-bar-fill { height: 100%; background: var(--gold); border-radius: 3px; }

  .review-card { background: white; border-radius: var(--radius-lg); padding: 24px; border: 1px solid var(--border-light); margin-bottom: 16px; }
  .review-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
  .review-meta { display: flex; align-items: center; gap: 10px; }
  .review-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-light), var(--beige)); display: flex; align-items: center; justify-content: center; font-family: var(--font-serif); font-weight: 700; color: var(--primary); }
  .review-author { font-size: .88rem; font-weight: 600; color: var(--text-dark); }
  .review-date { font-size: .76rem; color: var(--text-muted); }
  .review-title { font-size: .9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 6px; }
  .review-body { font-size: .86rem; line-height: 1.7; color: var(--text-body); }

  /* Write review */
  .write-review { background: var(--beige); border-radius: var(--radius-xl); padding: 32px; margin-top: 32px; }

  @media (max-width: 900px) {
    .product-layout { grid-template-columns: 1fr; gap: 32px; padding-top: calc(var(--navbar-h) + 24px); }
    .review-summary { flex-direction: column; gap: 24px; }
  }
  </style>
</head>
<body>
<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>

<div class="product-layout">
  <!-- ── Gallery ─────────────────────────────────────────── -->
  <div>
    <div class="gallery-main" id="mainGallery">
      <img src="<?= sanitize($product['image']) ?>" alt="<?= sanitize($product['name']) ?>" id="mainImage">
      <?php if ($discount > 0): ?>
        <div class="gallery-discount-badge">-<?= $discount ?>%</div>
      <?php endif; ?>
    </div>
    <?php if (!empty($images)): ?>
    <div class="gallery-thumbs">
      <div class="gallery-thumb active" onclick="switchImage('<?= sanitize($product['image']) ?>', this)">
        <img src="<?= sanitize($product['image']) ?>" alt="Main">
      </div>
      <?php foreach ($images as $img): ?>
      <div class="gallery-thumb" onclick="switchImage('<?= sanitize($img['image']) ?>', this)">
        <img src="<?= sanitize($img['image']) ?>" alt="Gallery">
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Product Info ─────────────────────────────────────── -->
  <div class="product-info">
    <div class="breadcrumb">
      <a href="<?= APP_URL ?>/index.php">Home</a>
      <span class="breadcrumb-sep"><i class="fa-solid fa-chevron-right" style="font-size:.6rem;"></i></span>
      <a href="<?= APP_URL ?>/shop.php">Shop</a>
      <span class="breadcrumb-sep"><i class="fa-solid fa-chevron-right" style="font-size:.6rem;"></i></span>
      <a href="<?= APP_URL ?>/shop.php?category=<?= urlencode($product['cat_slug'] ?? '') ?>"><?= sanitize($product['cat_name'] ?? '') ?></a>
    </div>

    <a href="<?= APP_URL ?>/shop.php?category=<?= urlencode($product['cat_slug'] ?? '') ?>" class="product-info-category">
      <?= sanitize($product['cat_name'] ?? 'Product') ?>
    </a>
    <h1><?= sanitize($product['name']) ?></h1>

    <div class="product-rating-row">
      <span class="stars">
        <?php for($i=1;$i<=5;$i++) echo $i<=round($product['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
      </span>
      <span style="font-size:.88rem;font-weight:600;"><?= number_format($product['rating'],1) ?></span>
      <span class="product-review-count" onclick="document.getElementById('reviews').scrollIntoView({behavior:'smooth'})">
        <?= number_format($product['review_count']) ?> reviews
      </span>
      <span class="product-stock <?= $product['stock'] < 5 ? 'low' : '' ?> <?= $product['stock'] < 1 ? 'out' : '' ?>">
        <?php if ($product['stock'] < 1): ?>Out of Stock
        elseif ($product['stock'] < 5): ?>Only <?= $product['stock'] ?> left!
        <?php else: ?><i class="fa-solid fa-circle-check"></i> In Stock
        <?php endif; ?>
      </span>
    </div>

    <div class="product-price-row">
      <span class="product-price"><?= formatPrice($product['price']) ?></span>
      <?php if ($product['original_price'] > 0): ?>
        <span class="product-original"><?= formatPrice($product['original_price']) ?></span>
        <span class="product-save">Save <?= $discount ?>%</span>
      <?php endif; ?>
    </div>

    <p class="product-desc"><?= nl2br(sanitize($product['description'])) ?></p>

    <!-- Quantity -->
    <div class="qty-section">
      <span class="qty-label">Quantity</span>
      <div class="qty-control">
        <button onclick="changeQty(-1)"><i class="fa-solid fa-minus"></i></button>
        <span id="qtyValue">1</span>
        <button onclick="changeQty(1)"><i class="fa-solid fa-plus"></i></button>
      </div>
    </div>

    <!-- Actions -->
    <div class="product-actions">
      <button class="btn btn-primary btn-add-cart" id="addCartBtn" <?= $product['stock'] < 1 ? 'disabled' : '' ?>
              onclick="addToCartQty(<?= $id ?>, document.getElementById('qtyValue').textContent, this)">
        <i class="fa-solid fa-bag-shopping"></i>
        <?= $product['stock'] < 1 ? 'Out of Stock' : 'Add to Bag' ?>
      </button>
      <button class="btn-wishlist <?= $inWishlist ? 'active' : '' ?>" id="wishlistBtn"
              onclick="toggleWishlist(<?= $id ?>, this)" title="Add to Wishlist">
        <i class="fa-<?= $inWishlist ? 'solid' : 'regular' ?> fa-heart"></i>
      </button>
    </div>

    <!-- Trust -->
    <div class="product-trust">
      <div class="trust-icon"><i class="fa-solid fa-truck-fast"></i><span>Free shipping over &#8369;2,000</span></div>
      <div class="trust-icon"><i class="fa-solid fa-rotate-left"></i><span>30-day returns</span></div>
      <div class="trust-icon"><i class="fa-solid fa-leaf"></i><span>Cruelty-free</span></div>
      <div class="trust-icon"><i class="fa-solid fa-shield-halved"></i><span>Secure checkout</span></div>
    </div>

    <!-- Accordion -->
    <div class="product-accordion">
      <?php if ($product['ingredients']): ?>
      <div class="accordion-item">
        <div class="accordion-header" onclick="this.parentElement.classList.toggle('open')">
          <span><i class="fa-regular fa-list-alt" style="margin-right:8px;color:var(--primary);"></i> Key Ingredients</span>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="accordion-body"><?= sanitize($product['ingredients']) ?></div>
      </div>
      <?php endif; ?>
      <div class="accordion-item open">
        <div class="accordion-header" onclick="this.parentElement.classList.toggle('open')">
          <span><i class="fa-solid fa-truck" style="margin-right:8px;color:var(--primary);"></i> Delivery Info</span>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="accordion-body">
          Standard delivery: <strong><?= sanitize($product['delivery_info']) ?></strong><br>
          Free shipping on orders over &#8369;2,000.<br>
          Cash on Delivery and GCash accepted.
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" onclick="this.parentElement.classList.toggle('open')">
          <span><i class="fa-solid fa-rotate-left" style="margin-right:8px;color:var(--primary);"></i> Returns Policy</span>
          <i class="fa-solid fa-chevron-down"></i>
        </div>
        <div class="accordion-body">
          We offer hassle-free returns within 30 days of delivery. Item must be unused and in original packaging.
          Contact us at <a href="mailto:<?= CONTACT_EMAIL ?>" style="color:var(--primary);"><?= CONTACT_EMAIL ?></a>.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ REVIEWS ══════════════════════════════════════════════ -->
<section class="section" id="reviews" style="padding-top:48px;">
  <div class="section-header left">
    <span class="section-label">Customer Reviews</span>
    <h2 class="section-title">What People Are Saying</h2>
  </div>

  <!-- Rating Summary -->
  <?php if (!empty($reviews)): ?>
  <div class="review-summary">
    <div style="text-align:center;">
      <div class="review-big-num"><?= number_format($product['rating'],1) ?></div>
      <div class="review-stars-big stars">
        <?php for($i=1;$i<=5;$i++) echo $i<=round($product['rating']) ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
      </div>
      <div class="review-total"><?= number_format($product['review_count']) ?> reviews</div>
    </div>
    <div class="review-bars">
      <?php
      $starCounts = array_fill(1, 5, 0);
      foreach ($reviews as $rv) $starCounts[$rv['rating']] = ($starCounts[$rv['rating']] ?? 0) + 1;
      for ($s = 5; $s >= 1; $s--):
        $pct = $product['review_count'] > 0 ? ($starCounts[$s] / $product['review_count']) * 100 : 0;
      ?>
      <div class="review-bar-row">
        <span><?= $s ?>★</span>
        <div class="review-bar"><div class="review-bar-fill" style="width:<?= round($pct) ?>%"></div></div>
        <span style="color:var(--text-muted);width:24px;"><?= $starCounts[$s] ?></span>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Review Cards -->
  <?php foreach ($reviews as $rv): ?>
  <div class="review-card">
    <div class="review-header">
      <div class="review-meta">
        <div class="review-avatar"><?= strtoupper(substr($rv['reviewer_name'], 0, 1)) ?></div>
        <div>
          <div class="review-author"><?= sanitize($rv['reviewer_name']) ?></div>
          <div class="review-date"><?= formatDate($rv['created_at'], 'M j, Y') ?></div>
        </div>
      </div>
      <span class="stars" style="font-size:.85rem;">
        <?php for($i=1;$i<=5;$i++) echo $i<=$rv['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
      </span>
    </div>
    <?php if ($rv['title']): ?>
      <div class="review-title"><?= sanitize($rv['title']) ?></div>
    <?php endif; ?>
    <div class="review-body"><?= sanitize($rv['body']) ?></div>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fa-regular fa-star"></i></div>
    <h3>No reviews yet</h3>
    <p>Be the first to review this product!</p>
  </div>
  <?php endif; ?>

  <!-- Write a Review -->
  <?php if (isLoggedIn()): ?>
  <div class="write-review">
    <h3 style="font-size:1.3rem;margin-bottom:20px;">Write a Review</h3>
    <form id="reviewForm">
      <input type="hidden" name="product_id" value="<?= $id ?>">
      <div class="form-group">
        <label class="form-label">Your Rating</label>
        <div class="star-picker" id="starPicker" style="font-size:1.5rem;color:var(--gold);display:flex;gap:6px;cursor:pointer;">
          <?php for($i=1;$i<=5;$i++): ?>
          <i class="fa-regular fa-star" data-rating="<?= $i ?>" onclick="setRating(<?= $i ?>)"></i>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="ratingInput" value="5">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Review Title</label>
          <input type="text" name="title" class="form-control" placeholder="Sum it up in a few words">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Your Review</label>
        <textarea name="body" class="form-control" placeholder="Share your experience with this product…" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i> Submit Review
      </button>
      <p id="reviewMsg" style="margin-top:10px;font-size:.85rem;"></p>
    </form>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:32px;background:white;border-radius:var(--radius-lg);border:1px solid var(--border-light);margin-top:20px;">
    <p style="margin-bottom:16px;color:var(--text-muted);">Sign in to write a review.</p>
    <a href="<?= APP_URL ?>/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary">Sign In to Review</a>
  </div>
  <?php endif; ?>
</section>

<!-- ══ RELATED ══════════════════════════════════════════════ -->
<?php if (!empty($related)): ?>
<section class="section" style="padding-top:48px;">
  <div class="section-header">
    <span class="section-label">You May Also Like</span>
    <h2 class="section-title">Related Products</h2>
  </div>
  <div class="products-grid">
    <?php foreach ($related as $p): ?>
    <div class="product-card">
      <div class="product-card-image">
        <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
        <button class="product-card-wishlist" onclick="toggleWishlist(<?= $p['id'] ?>, this)"><i class="fa-regular fa-heart"></i></button>
        <div class="product-card-hover">
          <button class="btn btn-primary btn-full btn-sm" onclick="addToCart(<?= $p['id'] ?>, this)">
            <i class="fa-solid fa-bag-shopping"></i> Add to Bag
          </button>
        </div>
      </div>
      <div class="product-card-body">
        <div class="product-card-category"><?= sanitize($p['cat_name'] ?? '') ?></div>
        <a href="<?= APP_URL ?>/product.php?id=<?= $p['id'] ?>"><h3 class="product-card-name"><?= sanitize($p['name']) ?></h3></a>
        <div class="product-card-price">
          <span class="price-current"><?= formatPrice($p['price']) ?></span>
          <?php if ($p['original_price'] > 0): ?><span class="price-original"><?= formatPrice($p['original_price']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script src="<?= APP_URL ?>/assets/js/wishlist.js"></script>
<script>
let qty = 1;
function changeQty(d) {
  qty = Math.max(1, Math.min(<?= $product['stock'] ?: 1 ?>, qty + d));
  document.getElementById('qtyValue').textContent = qty;
}
function switchImage(src, el) {
  document.getElementById('mainImage').src = src;
  document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}
function addToCartQty(id, qty, btn) {
  addToCart(id, btn, parseInt(qty));
}
function setRating(n) {
  document.getElementById('ratingInput').value = n;
  document.querySelectorAll('#starPicker i').forEach((s, i) => {
    s.className = i < n ? 'fa-solid fa-star' : 'fa-regular fa-star';
  });
}
setRating(5);
// Review form
document.getElementById('reviewForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const data = Object.fromEntries(new FormData(this));
  const msg = document.getElementById('reviewMsg');
  const btn = this.querySelector('button[type=submit]');
  btn.disabled = true;
  try {
    const res = await fetch('<?= APP_URL ?>/api/review.php', {
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    });
    const d = await res.json();
    msg.style.color = d.success ? 'var(--success)' : 'var(--danger)';
    msg.textContent = d.message;
    if (d.success) this.reset();
  } catch { msg.style.color = 'var(--danger)'; msg.textContent = 'Error submitting review.'; }
  btn.disabled = false;
});
</script>
</body>
</html>
