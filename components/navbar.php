<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

$cartCount    = getCartCount();
$wishCount    = getWishlistCount();
$user         = getCurrentUser();
$currentPath  = $_SERVER['REQUEST_URI'];

function isActivePage(string $path): string {
    global $currentPath;
    return str_contains($currentPath, $path) ? 'active' : '';
}
?>
<nav class="navbar" id="navbar">
  <div class="nav-inner">
    <!-- Logo -->
    <a href="<?= APP_URL ?>/index.php" class="nav-logo">
      <span class="logo-icon">✦</span> Luna Glow
    </a>

    <!-- Desktop Links -->
    <ul class="nav-links" id="navLinks">
      <li><a href="<?= APP_URL ?>/index.php" class="<?= str_contains($currentPath,'index') || $currentPath === '/' ? 'active' : '' ?>">Home</a></li>
      <li class="nav-dropdown">
        <a href="<?= APP_URL ?>/shop.php" class="<?= isActivePage('shop') ?>">
          Shop <i class="fa-solid fa-chevron-down"></i>
        </a>
        <div class="nav-dropdown-menu">
          <div class="dropdown-grid">
            <div>
              <p class="dropdown-label">Categories</p>
              <a href="<?= APP_URL ?>/shop.php?category=lipstick">Lipstick</a>
              <a href="<?= APP_URL ?>/shop.php?category=foundation">Foundation</a>
              <a href="<?= APP_URL ?>/shop.php?category=blush">Blush</a>
              <a href="<?= APP_URL ?>/shop.php?category=mascara">Mascara</a>
              <a href="<?= APP_URL ?>/shop.php?category=powder">Powder</a>
              <a href="<?= APP_URL ?>/shop.php?category=skincare">Skincare</a>
              <a href="<?= APP_URL ?>/shop.php?category=makeup-sets">Makeup Sets</a>
            </div>
            <div>
              <p class="dropdown-label">Collections</p>
              <a href="<?= APP_URL ?>/shop.php?filter=featured">Featured</a>
              <a href="<?= APP_URL ?>/shop.php?filter=bestseller">Best Sellers</a>
              <a href="<?= APP_URL ?>/shop.php?filter=new">New Arrivals</a>
              <a href="<?= APP_URL ?>/shop.php?filter=sale">On Sale</a>
            </div>
          </div>
        </div>
      </li>
      <li><a href="<?= APP_URL ?>/about.php" class="<?= isActivePage('about') ?>">About</a></li>
      <li><a href="<?= APP_URL ?>/contact.php" class="<?= isActivePage('contact') ?>">Contact</a></li>
    </ul>

    <!-- Nav Actions -->
    <div class="nav-actions">
      <!-- Search -->
      <button class="nav-icon-btn" id="searchToggle" title="Search" aria-label="Search">
        <i class="fa-solid fa-magnifying-glass"></i>
      </button>

      <!-- Wishlist -->
      <a href="<?= APP_URL ?>/wishlist.php" class="nav-icon-btn" title="Wishlist" aria-label="Wishlist">
        <i class="fa-regular fa-heart"></i>
        <?php if ($wishCount > 0): ?>
          <span class="nav-badge"><?= $wishCount ?></span>
        <?php endif; ?>
      </a>

      <!-- Cart -->
      <button class="nav-icon-btn" id="cartToggle" title="Cart" aria-label="Cart">
        <i class="fa-solid fa-bag-shopping"></i>
        <span class="nav-badge" id="cartBadge" <?= $cartCount === 0 ? 'style="display:none"' : '' ?>>
          <?= $cartCount ?>
        </span>
      </button>

      <!-- User -->
      <?php if ($user): ?>
        <div class="nav-dropdown">
          <button class="nav-user-btn">
            <span class="user-avatar-mini"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
            <i class="fa-solid fa-chevron-down" style="font-size:.65rem;"></i>
          </button>
          <div class="nav-dropdown-menu nav-dropdown-user">
            <div class="dropdown-user-header">
              <div class="user-avatar-mini lg"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
              <div>
                <p class="fw-600 text-dark" style="font-size:.9rem;"><?= sanitize($user['name']) ?></p>
                <p style="font-size:.76rem;color:var(--text-muted);"><?= sanitize($user['email']) ?></p>
              </div>
            </div>
            <div class="divider" style="margin:10px 0;"></div>
            <a href="<?= APP_URL ?>/user/dashboard.php"><i class="fa-regular fa-user"></i> My Account</a>
            <a href="<?= APP_URL ?>/user/orders.php"><i class="fa-regular fa-bag-shopping"></i> My Orders</a>
            <a href="<?= APP_URL ?>/user/wishlist.php"><i class="fa-regular fa-heart"></i> Wishlist</a>
            <a href="<?= APP_URL ?>/tracking.php"><i class="fa-solid fa-truck"></i> Track Order</a>
            <div class="divider" style="margin:10px 0;"></div>
            <a href="<?= APP_URL ?>/logout.php" style="color:var(--danger);">
              <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
            </a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary btn-sm">Sign In</a>
      <?php endif; ?>

      <!-- Hamburger -->
      <button class="hamburger" id="hamburger" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <!-- Search Bar -->
  <div class="nav-search-bar" id="searchBar">
    <div class="search-inner">
      <i class="fa-solid fa-magnifying-glass search-icon-sm"></i>
      <input type="text" id="searchInput" placeholder="Search for lipstick, foundation, skincare…" autocomplete="off">
      <button class="search-close" id="searchClose"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="search-results" id="searchResults"></div>
  </div>
</nav>

<!-- Mobile Menu Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<style>
/* ── Navbar ──────────────────────────────────────────────── */
.navbar {
  position: fixed;
  top: 0; left: 0;
  width: 100%;
  z-index: 1000;
  transition: var(--transition);
}
.navbar.scrolled {
  background: rgba(253,248,245,.92);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  box-shadow: 0 2px 24px rgba(180,100,130,.08);
}
.nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 var(--section-px);
  height: var(--navbar-h);
  max-width: var(--container);
  margin: 0 auto;
}

/* Logo */
.nav-logo {
  font-family: var(--font-serif);
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--primary);
  letter-spacing: .5px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.logo-icon { font-size: 1rem; animation: float 4s ease-in-out infinite; display: inline-block; }

/* Links */
.nav-links {
  display: flex;
  align-items: center;
  gap: 4px;
}
.nav-links > li { position: relative; }
.nav-links a {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 8px 14px;
  border-radius: var(--radius-pill);
  font-size: .88rem;
  font-weight: 500;
  color: var(--text-body);
  transition: var(--transition-fast);
}
.nav-links a:hover,
.nav-links a.active {
  color: var(--primary);
  background: var(--primary-light);
}
.nav-links a i { font-size: .65rem; transition: .3s; }

/* Dropdown */
.nav-dropdown { position: relative; }
.nav-dropdown:hover > a i { transform: rotate(180deg); }
.nav-dropdown-menu {
  position: absolute;
  top: calc(100% + 12px);
  left: 50%;
  transform: translateX(-50%) translateY(-8px);
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xl);
  border: 1px solid var(--border-light);
  padding: 24px;
  min-width: 360px;
  opacity: 0;
  pointer-events: none;
  transition: var(--transition-fast);
  z-index: 100;
}
.nav-dropdown:hover .nav-dropdown-menu {
  opacity: 1;
  pointer-events: all;
  transform: translateX(-50%) translateY(0);
}
.dropdown-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.dropdown-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--primary);
  margin-bottom: 10px;
}
.nav-dropdown-menu a {
  display: block;
  padding: 6px 0;
  font-size: .85rem;
  color: var(--text-body);
  border-radius: 0;
  background: none;
}
.nav-dropdown-menu a:hover { color: var(--primary); background: none; padding-left: 4px; }

/* User Dropdown */
.nav-dropdown-user { min-width: 240px; left: auto; right: 0; transform: translateY(-8px); }
.nav-dropdown:hover .nav-dropdown-user {
  transform: translateY(0);
  left: auto;
}
.dropdown-user-header { display: flex; align-items: center; gap: 12px; }
.nav-dropdown-user a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 6px;
  font-size: .85rem;
  transition: .2s;
  border-radius: 8px;
}
.nav-dropdown-user a i { width: 16px; text-align: center; }
.nav-dropdown-user a:hover { background: var(--beige); padding-left: 10px; }

/* Avatar */
.user-avatar-mini {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .85rem;
  font-weight: 600;
  flex-shrink: 0;
}
.user-avatar-mini.lg { width: 44px; height: 44px; font-size: 1.1rem; }

/* Icon Buttons */
.nav-actions { display: flex; align-items: center; gap: 6px; }
.nav-icon-btn {
  position: relative;
  width: 40px; height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-body);
  font-size: 1rem;
  transition: var(--transition-fast);
  border: none;
  background: transparent;
  cursor: pointer;
}
.nav-icon-btn:hover {
  background: var(--primary-light);
  color: var(--primary);
}
.nav-user-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: var(--radius-pill);
  background: transparent;
  cursor: pointer;
  transition: .2s;
  border: none;
}
.nav-user-btn:hover { background: var(--primary-light); }

.nav-badge {
  position: absolute;
  top: 4px; right: 4px;
  min-width: 17px; height: 17px;
  border-radius: var(--radius-pill);
  background: var(--primary);
  color: white;
  font-size: .65rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 2px solid var(--cream);
}

/* Search Bar */
.nav-search-bar {
  background: white;
  border-top: 1px solid var(--border);
  padding: 0 var(--section-px);
  max-height: 0;
  overflow: hidden;
  transition: max-height .4s ease, padding .4s ease;
}
.nav-search-bar.open {
  max-height: 400px;
  padding: 16px var(--section-px);
}
.search-inner {
  display: flex;
  align-items: center;
  gap: 14px;
  max-width: 640px;
  margin: 0 auto;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 12px 20px;
  transition: .2s;
}
.search-inner:focus-within {
  border-color: var(--primary);
  box-shadow: 0 0 0 4px var(--primary-glow);
}
.search-icon-sm { color: var(--text-muted); }
.search-inner input {
  flex: 1;
  border: none;
  outline: none;
  font-family: var(--font-sans);
  font-size: .9rem;
  color: var(--text-dark);
  background: transparent;
}
.search-close {
  color: var(--text-muted);
  cursor: pointer;
  font-size: .9rem;
  transition: .2s;
  border: none;
  background: none;
}
.search-close:hover { color: var(--primary); }

.search-results {
  max-width: 640px;
  margin: 12px auto 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.search-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: white;
  transition: .2s;
  text-decoration: none;
  color: var(--text-dark);
}
.search-item:hover { border-color: var(--primary); background: var(--primary-light); }
.search-item img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
.search-item-name { font-size: .88rem; font-weight: 500; }
.search-item-price { font-size: .82rem; color: var(--primary); font-weight: 600; }

/* Hamburger */
.hamburger {
  display: none;
  flex-direction: column;
  gap: 5px;
  padding: 6px;
  background: none;
  border: none;
  cursor: pointer;
}
.hamburger span {
  display: block;
  width: 22px; height: 2px;
  background: var(--text-dark);
  border-radius: 2px;
  transition: .3s;
}
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.mobile-overlay {
  position: fixed; inset: 0;
  background: rgba(42,31,40,.5);
  z-index: 900;
  opacity: 0;
  pointer-events: none;
  transition: .3s;
}
.mobile-overlay.active { opacity: 1; pointer-events: all; }

/* ── Responsive Navbar ───────────────────────────────────── */
@media (max-width: 900px) {
  .nav-links {
    position: fixed;
    top: 0; right: 0;
    width: 300px;
    height: 100vh;
    background: white;
    flex-direction: column;
    align-items: flex-start;
    padding: 100px 32px 32px;
    gap: 0;
    transform: translateX(100%);
    transition: transform .4s cubic-bezier(.16,1,.3,1);
    z-index: 950;
    box-shadow: -10px 0 40px rgba(0,0,0,.1);
    overflow-y: auto;
  }
  .nav-links.open { transform: translateX(0); }
  .nav-links > li { width: 100%; }
  .nav-links a { padding: 12px 0; border-radius: 0; border-bottom: 1px solid var(--border-light); width: 100%; }
  .nav-links a:hover { background: transparent; color: var(--primary); }
  .nav-dropdown-menu {
    position: static;
    transform: none !important;
    box-shadow: none;
    border: none;
    opacity: 1;
    pointer-events: all;
    min-width: auto;
    padding: 8px 16px;
    display: none;
  }
  .nav-dropdown.open .nav-dropdown-menu { display: block; }
  .hamburger { display: flex; }
}
</style>
