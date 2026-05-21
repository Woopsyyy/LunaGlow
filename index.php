<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

// Fetch dynamic data
$banners      = dbFetchAll("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order LIMIT 3");
$featured     = dbFetchAll("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_featured = 1 ORDER BY p.review_count DESC LIMIT 4");
$bestsellers  = dbFetchAll("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_bestseller = 1 ORDER BY p.review_count DESC LIMIT 4");
$newArrivals  = dbFetchAll("SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_new = 1 ORDER BY p.created_at DESC LIMIT 4");
$categories   = dbFetchAll("SELECT * FROM categories ORDER BY sort_order LIMIT 7");
$reviews      = dbFetchAll("SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 6");

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Luna Glow — Premium Beauty Boutique</title>
  <meta name="description" content="Discover Luna Glow's premium makeup collection. Elegant lipsticks, foundations, skincare and more — crafted for your most radiant self.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  /* ── Hero ─────────────────────────────────────────────── */
  .hero {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    position: relative;
    overflow: hidden;
    padding-top: var(--navbar-h);
  }
  .hero-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 80px var(--section-px) 80px;
    position: relative;
    z-index: 2;
  }
  .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--primary-light);
    color: var(--primary);
    padding: 7px 16px;
    border-radius: var(--radius-pill);
    font-size: .76rem;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 24px;
    width: fit-content;
    animation: fadeInUp .7s ease forwards;
  }
  .hero-badge i { animation: float 3s ease-in-out infinite; }
  .hero-title {
    font-family: var(--font-serif);
    font-size: clamp(3rem, 5vw, 4.8rem);
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.1;
    margin-bottom: 24px;
    animation: fadeInUp .7s ease .15s both;
  }
  .hero-title .accent { color: var(--primary); display: block; }
  .hero-title .italic-word { font-style: italic; }
  .hero-desc {
    font-size: 1rem;
    color: var(--text-body);
    line-height: 1.8;
    max-width: 440px;
    margin-bottom: 36px;
    animation: fadeInUp .7s ease .3s both;
  }
  .hero-buttons {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    animation: fadeInUp .7s ease .45s both;
  }
  .hero-stats {
    display: flex;
    gap: 36px;
    margin-top: 48px;
    animation: fadeInUp .7s ease .6s both;
  }
  .hero-stat-num {
    font-family: var(--font-serif);
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1;
    margin-bottom: 4px;
  }
  .hero-stat-label { font-size: .78rem; color: var(--text-muted); letter-spacing: .5px; }

  /* Hero image side */
  .hero-visual {
    position: relative;
    overflow: hidden;
    background: var(--beige);
  }
  .hero-slideshow {
    position: absolute; inset: 0;
  }
  .hero-slide {
    position: absolute; inset: 0;
    opacity: 0;
    transition: opacity 1.2s ease;
  }
  .hero-slide.active { opacity: 1; }
  .hero-slide img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: top center;
  }
  .hero-slide-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to right, rgba(253,248,245,.15) 0%, transparent 60%);
  }
  /* Floating product card */
  .hero-float-card {
    position: absolute;
    bottom: 48px;
    left: -36px;
    background: white;
    border-radius: var(--radius-lg);
    padding: 16px 20px;
    box-shadow: var(--shadow-xl);
    display: flex;
    align-items: center;
    gap: 14px;
    width: 240px;
    animation: fadeInUp .7s ease .8s both;
    z-index: 10;
  }
  .hero-float-img {
    width: 52px; height: 52px;
    border-radius: var(--radius-md);
    object-fit: cover;
  }
  .hero-float-name { font-size: .82rem; font-weight: 600; color: var(--text-dark); margin-bottom: 3px; }
  .hero-float-price { font-size: .88rem; color: var(--primary); font-weight: 700; }

  /* Slide indicators */
  .hero-indicators {
    position: absolute;
    bottom: 32px;
    right: 32px;
    display: flex;
    gap: 8px;
    z-index: 10;
  }
  .hero-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,.5);
    cursor: pointer;
    transition: .3s;
    border: none;
  }
  .hero-dot.active { background: white; width: 24px; border-radius: var(--radius-pill); }

  /* Decorative elements */
  .hero-deco-circle {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
  }
  .hero-deco-1 {
    width: 360px; height: 360px;
    background: radial-gradient(circle, rgba(212,120,154,.12) 0%, transparent 70%);
    top: -80px; left: -80px;
  }
  .hero-deco-2 {
    width: 200px; height: 200px;
    background: radial-gradient(circle, rgba(201,169,110,.1) 0%, transparent 70%);
    bottom: 100px; right: 20px;
  }

  /* ── Categories ─────────────────────────────────────── */
  .categories-scroll {
    display: flex;
    gap: 20px;
    overflow-x: auto;
    padding-bottom: 12px;
    scrollbar-width: none;
  }
  .categories-scroll::-webkit-scrollbar { display: none; }
  .cat-pill {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: white;
    border-radius: var(--radius-pill);
    border: 1.5px solid var(--border);
    white-space: nowrap;
    transition: var(--transition-fast);
    text-decoration: none;
    color: var(--text-body);
    font-size: .85rem;
    font-weight: 500;
    flex-shrink: 0;
  }
  .cat-pill img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
  .cat-pill:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

  /* ── Editorial Banner ───────────────────────────────── */
  .editorial-banner {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    min-height: 500px;
    border-radius: var(--radius-xl);
    overflow: hidden;
    position: relative;
  }
  .editorial-img {
    width: 100%; height: 100%;
    object-fit: cover;
  }
  .editorial-content {
    background: linear-gradient(135deg, var(--text-dark) 0%, #3d2c3a 100%);
    padding: 72px 60px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .editorial-tag {
    font-size: .7rem;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 600;
    margin-bottom: 20px;
  }
  .editorial-title {
    font-family: var(--font-serif);
    font-size: 2.6rem;
    color: white;
    line-height: 1.2;
    margin-bottom: 18px;
  }
  .editorial-desc { color: rgba(255,255,255,.65); font-size: .9rem; line-height: 1.8; margin-bottom: 32px; }

  /* ── Instagram ──────────────────────────────────────── */
  .instagram-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
    border-radius: var(--radius-lg);
    overflow: hidden;
  }
  .instagram-item {
    aspect-ratio: 1;
    overflow: hidden;
    position: relative;
    cursor: pointer;
  }
  .instagram-item img { width: 100%; height: 100%; object-fit: cover; transition: .4s; }
  .instagram-item:hover img { transform: scale(1.08); }
  .instagram-item-overlay {
    position: absolute; inset: 0;
    background: rgba(212,120,154,.5);
    display: flex; align-items: center; justify-content: center;
    opacity: 0;
    transition: .3s;
  }
  .instagram-item:hover .instagram-item-overlay { opacity: 1; }
  .instagram-item-overlay i { color: white; font-size: 1.4rem; }

  /* Responsive */
  @media (max-width: 900px) {
    .hero { grid-template-columns: 1fr; }
    .hero-visual { height: 65vw; min-height: 320px; order: -1; }
    .hero-float-card { display: none; }
    .hero-content { padding: 48px var(--section-px); }
    .editorial-banner { grid-template-columns: 1fr; }
    .editorial-img { height: 280px; }
    .editorial-content { padding: 48px 32px; }
    .instagram-grid { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 600px) {
    .hero-stats { gap: 24px; }
    .instagram-grid { grid-template-columns: repeat(3, 1fr); }
  }
  </style>
</head>
<body>

<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>

<?php if ($flash): ?>
  <?= renderFlash() ?>
<?php endif; ?>

<!-- ══ HERO ═══════════════════════════════════════════════ -->
<section class="hero" id="heroSection">
  <div class="hero-deco-circle hero-deco-1"></div>

  <div class="hero-content">
    <div class="hero-badge">
      <i class="fa-solid fa-star"></i>
      <?= !empty($banners[0]['badge']) ? sanitize($banners[0]['badge']) : 'New Collection' ?>
    </div>

    <h1 class="hero-title">
      <?php if (!empty($banners[0])): ?>
        <?php
          $words = explode(' ', $banners[0]['title'], 2);
          echo sanitize($words[0]) . ' <span class="accent italic-word">' . sanitize($words[1] ?? '') . '</span>';
        ?>
      <?php else: ?>
        Glow Beyond <span class="accent italic-word">Beauty</span>
      <?php endif; ?>
    </h1>

    <p class="hero-desc">
      <?= !empty($banners[0]) ? sanitize($banners[0]['subtitle']) : 'Discover premium makeup essentials crafted for confidence, elegance, and your most radiant self.' ?>
    </p>

    <div class="hero-buttons">
      <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-bag-shopping"></i> Shop Now
      </a>
      <a href="<?= APP_URL ?>/shop.php?filter=featured" class="btn btn-outline btn-lg">
        Explore Collection
      </a>
    </div>

    <div class="hero-stats">
      <div>
        <div class="hero-stat-num">12K+</div>
        <div class="hero-stat-label">Happy Customers</div>
      </div>
      <div>
        <div class="hero-stat-num">4.9★</div>
        <div class="hero-stat-label">Average Rating</div>
      </div>
      <div>
        <div class="hero-stat-num">100%</div>
        <div class="hero-stat-label">Cruelty Free</div>
      </div>
    </div>
  </div>

  <!-- Hero Visual with Slideshow -->
  <div class="hero-visual">
    <div class="hero-slideshow" id="heroSlideshow">
      <div class="hero-slide active" data-index="0">
        <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=1400&auto=format&fit=crop" alt="Beautiful model with makeup">
        <div class="hero-slide-overlay"></div>
      </div>
      <div class="hero-slide" data-index="1">
        <img src="https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?q=80&w=1400&auto=format&fit=crop" alt="Luxury makeup collection">
        <div class="hero-slide-overlay"></div>
      </div>
      <div class="hero-slide" data-index="2">
        <img src="https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=1400&auto=format&fit=crop" alt="Skincare and beauty">
        <div class="hero-slide-overlay"></div>
      </div>
    </div>

    <!-- Floating product card -->
    <?php if (!empty($featured[0])): ?>
    <div class="hero-float-card">
      <img src="<?= sanitize($featured[0]['image']) ?>" alt="<?= sanitize($featured[0]['name']) ?>" class="hero-float-img">
      <div>
        <div class="hero-float-name"><?= sanitize($featured[0]['name']) ?></div>
        <div class="hero-float-price"><?= formatPrice($featured[0]['price']) ?></div>
        <div class="stars" style="font-size:.65rem;margin-top:4px;">
          <?php for($i=1;$i<=5;$i++) echo $i<=round($featured[0]['rating']) ? '★' : '☆'; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="hero-indicators" id="heroIndicators">
      <button class="hero-dot active" data-slide="0"></button>
      <button class="hero-dot" data-slide="1"></button>
      <button class="hero-dot" data-slide="2"></button>
    </div>

    <div class="hero-deco-circle hero-deco-2"></div>
  </div>
</section>

<!-- ══ CATEGORIES ══════════════════════════════════════════ -->
<section class="section" style="padding-top:60px;padding-bottom:60px;">
  <div class="section-header left reveal">
    <span class="section-label">Browse By</span>
    <h2 class="section-title">Shop by Category</h2>
  </div>
  <div class="categories-scroll reveal">
    <?php foreach ($categories as $cat): ?>
    <a href="<?= APP_URL ?>/shop.php?category=<?= urlencode($cat['slug']) ?>" class="cat-pill">
      <img src="<?= sanitize($cat['image']) ?>" alt="<?= sanitize($cat['name']) ?>">
      <?= sanitize($cat['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ══ FEATURED PRODUCTS ═══════════════════════════════════ -->
<section class="section" style="padding-top:48px;">
  <div class="section-header reveal">
    <span class="section-label">Curated for You</span>
    <h2 class="section-title">Featured Products</h2>
    <p class="section-sub">Handpicked premium makeup essentials loved by beauty enthusiasts everywhere.</p>
  </div>
  <div class="products-grid">
    <?php foreach ($featured as $p): ?>
    <div class="product-card reveal" data-product-id="<?= $p['id'] ?>">
      <div class="product-card-image">
        <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
        <div class="product-card-badges">
          <?php if ($p['is_new']): ?><span class="badge badge-new">New</span><?php endif; ?>
          <?php if ($p['is_bestseller']): ?><span class="badge badge-bestseller">Best Seller</span><?php endif; ?>
          <?php if ($p['original_price'] > 0): ?><span class="badge badge-sale">Sale</span><?php endif; ?>
        </div>
        <button class="product-card-wishlist" onclick="toggleWishlist(<?= $p['id'] ?>, this)" title="Add to Wishlist">
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
  <div style="text-align:center;margin-top:48px;">
    <a href="<?= APP_URL ?>/shop.php?filter=featured" class="btn btn-outline btn-lg">View All Featured</a>
  </div>
</section>

<!-- ══ EDITORIAL BANNER ════════════════════════════════════ -->
<section class="section">
  <div class="editorial-banner reveal">
    <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=800&auto=format&fit=crop"
         alt="Beauty Editorial" class="editorial-img">
    <div class="editorial-content">
      <div class="editorial-tag">✦ The Luna Edit</div>
      <h2 class="editorial-title">Beauty That Tells Your Story</h2>
      <p class="editorial-desc">Every formula is crafted to celebrate your individuality — clean, luxurious, and made to make you feel extraordinary every single day.</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/shop.php" class="btn btn-gold">Explore Collection</a>
        <a href="<?= APP_URL ?>/about.php" class="btn btn-white">Our Story</a>
      </div>
    </div>
  </div>
</section>

<!-- ══ BEST SELLERS ════════════════════════════════════════ -->
<section class="section" style="padding-top:48px;">
  <div class="section-header reveal">
    <span class="section-label">Community Faves</span>
    <h2 class="section-title">Best Sellers</h2>
    <p class="section-sub">The products our customers can't stop talking about.</p>
  </div>
  <div class="products-grid">
    <?php foreach ($bestsellers as $p): ?>
    <div class="product-card reveal" data-product-id="<?= $p['id'] ?>">
      <div class="product-card-image">
        <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
        <div class="product-card-badges">
          <span class="badge badge-bestseller">Best Seller</span>
          <?php if ($p['is_new']): ?><span class="badge badge-new">New</span><?php endif; ?>
        </div>
        <button class="product-card-wishlist" onclick="toggleWishlist(<?= $p['id'] ?>, this)" title="Add to Wishlist">
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
  <div style="text-align:center;margin-top:48px;">
    <a href="<?= APP_URL ?>/shop.php?filter=bestseller" class="btn btn-outline btn-lg">Shop Best Sellers</a>
  </div>
</section>

<!-- ══ NEW ARRIVALS ════════════════════════════════════════ -->
<?php if (!empty($newArrivals)): ?>
<section class="section" style="background:var(--beige);border-radius:var(--radius-xl);margin:0 var(--section-px);">
  <div class="section-header reveal">
    <span class="section-label">Just Dropped</span>
    <h2 class="section-title">New Arrivals</h2>
  </div>
  <div class="products-grid">
    <?php foreach ($newArrivals as $p): ?>
    <div class="product-card reveal">
      <div class="product-card-image">
        <img src="<?= sanitize($p['image']) ?>" alt="<?= sanitize($p['name']) ?>" loading="lazy">
        <div class="product-card-badges"><span class="badge badge-new">New</span></div>
        <button class="product-card-wishlist" onclick="toggleWishlist(<?= $p['id'] ?>, this)"><i class="fa-regular fa-heart"></i></button>
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
</section>
<?php endif; ?>

<!-- ══ TESTIMONIALS ════════════════════════════════════════ -->
<?php if (!empty($reviews)): ?>
<section class="section">
  <div class="section-header reveal">
    <span class="section-label">Real Reviews</span>
    <h2 class="section-title">What Our Glow Community Says</h2>
    <p class="section-sub">Thousands of beauty lovers trust Luna Glow for their everyday radiance.</p>
  </div>
  <div class="testimonials-grid">
    <?php foreach (array_slice($reviews, 0, 3) as $r): ?>
    <div class="testimonial-card reveal">
      <div class="testimonial-stars stars">
        <?php for($i=1;$i<=5;$i++) echo $i<=$r['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?>
      </div>
      <p class="testimonial-text">"<?= sanitize($r['body']) ?>"</p>
      <div class="testimonial-author">
        <div class="testimonial-avatar"><?= strtoupper(substr($r['reviewer_name'], 0, 1)) ?></div>
        <div>
          <div class="testimonial-name"><?= sanitize($r['reviewer_name']) ?></div>
          <div class="testimonial-meta">on <?= sanitize($r['product_name']) ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ══ INSTAGRAM GALLERY ═══════════════════════════════════ -->
<section class="section" style="padding-top:0;">
  <div class="section-header reveal">
    <span class="section-label">Follow the Glow</span>
    <h2 class="section-title">@LunaGlowPH</h2>
    <p class="section-sub">Tag us in your looks for a chance to be featured.</p>
  </div>
  <div class="instagram-grid reveal">
    <?php
    $igImages = [
      'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=300',
      'https://images.unsplash.com/photo-1583241800698-9f2f3d9c0c94?w=300',
      'https://images.unsplash.com/photo-1596462502278-27bfdc403348?w=300',
      'https://images.unsplash.com/photo-1526045478516-99145907023c?w=300',
      'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=300',
      'https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?w=300',
    ];
    foreach ($igImages as $img): ?>
    <div class="instagram-item">
      <img src="<?= $img ?>" alt="Luna Glow Instagram" loading="lazy">
      <div class="instagram-item-overlay"><i class="fa-brands fa-instagram"></i></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ══ NEWSLETTER ══════════════════════════════════════════ -->
<section class="section">
  <div class="newsletter-section reveal">
    <span class="section-label">Join the Club</span>
    <h2 style="font-family:var(--font-serif);font-size:2.4rem;margin:12px 0;">Get Exclusive Beauty Drops</h2>
    <p style="color:var(--text-muted);max-width:420px;margin:0 auto;">
      Subscribe for early access to new launches, exclusive deals, and beauty tips from our experts.
    </p>
    <form class="newsletter-form" id="newsletterForm">
      <input type="email" name="email" id="newsletterEmail" placeholder="Enter your email address" required>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-paper-plane"></i> Subscribe
      </button>
    </form>
    <p id="newsletterMsg" style="margin-top:12px;font-size:.85rem;"></p>
    <p style="font-size:.74rem;color:var(--text-muted);margin-top:14px;">No spam. Unsubscribe anytime. ✨</p>
  </div>
</section>

<?php include __DIR__ . '/components/footer.php'; ?>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script src="<?= APP_URL ?>/assets/js/wishlist.js"></script>
<script>
// Hero Slideshow
(function() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.hero-dot');
  let current  = 0;
  let timer;

  function goTo(n) {
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
  }

  function autoplay() {
    timer = setInterval(() => goTo(current + 1), 5000);
  }

  dots.forEach(d => d.addEventListener('click', () => {
    clearInterval(timer);
    goTo(parseInt(d.dataset.slide));
    autoplay();
  }));

  autoplay();
})();

// Newsletter
document.getElementById('newsletterForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const email = document.getElementById('newsletterEmail').value;
  const msg   = document.getElementById('newsletterMsg');
  const btn   = this.querySelector('button[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';
  try {
    const res  = await fetch('<?= APP_URL ?>/api/newsletter.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({email})
    });
    const data = await res.json();
    msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
    msg.textContent = data.message;
    if (data.success) this.reset();
  } catch {
    msg.style.color = 'var(--danger)';
    msg.textContent = 'Something went wrong. Please try again.';
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Subscribe';
});
</script>
</body>
</html>
