<?php require_once __DIR__ . '/../includes/config.php'; ?>
<footer class="site-footer">
  <div class="footer-top">
    <div class="footer-brand">
      <a href="<?= APP_URL ?>/index.php" class="footer-logo">✦ Luna Glow</a>
      <p class="footer-tagline">Premium beauty essentials crafted for confidence, elegance, and your most radiant self.</p>
      <div class="footer-socials">
        <a href="<?= SOCIAL_INSTAGRAM ?>" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
        <a href="<?= SOCIAL_FACEBOOK ?>" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        <a href="<?= SOCIAL_TIKTOK ?>" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
        <a href="<?= SOCIAL_YOUTUBE ?>" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
      </div>
    </div>

    <div class="footer-links-group">
      <h4>Shop</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/shop.php?filter=featured">Featured</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?filter=bestseller">Best Sellers</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?filter=new">New Arrivals</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?filter=sale">On Sale</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?category=makeup-sets">Gift Sets</a></li>
      </ul>
    </div>

    <div class="footer-links-group">
      <h4>Categories</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/shop.php?category=lipstick">Lipstick</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?category=foundation">Foundation</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?category=blush">Blush</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?category=mascara">Mascara</a></li>
        <li><a href="<?= APP_URL ?>/shop.php?category=skincare">Skincare</a></li>
      </ul>
    </div>

    <div class="footer-links-group">
      <h4>Help</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/tracking.php">Track Order</a></li>
        <li><a href="<?= APP_URL ?>/contact.php">Contact Us</a></li>
        <li><a href="<?= APP_URL ?>/about.php">About Luna Glow</a></li>
        <li><a href="#">Shipping Policy</a></li>
        <li><a href="#">Returns</a></li>
      </ul>
    </div>

    <div class="footer-links-group">
      <h4>Account</h4>
      <ul>
        <li><a href="<?= APP_URL ?>/login.php">Sign In</a></li>
        <li><a href="<?= APP_URL ?>/register.php">Create Account</a></li>
        <li><a href="<?= APP_URL ?>/user/dashboard.php">My Dashboard</a></li>
        <li><a href="<?= APP_URL ?>/wishlist.php">My Wishlist</a></li>
        <li><a href="<?= APP_URL ?>/user/orders.php">My Orders</a></li>
      </ul>
    </div>
  </div>

  <div class="footer-middle">
    <div class="footer-trust">
      <div class="trust-item">
        <i class="fa-solid fa-truck-fast"></i>
        <div>
          <strong>Free Shipping</strong>
          <span>On orders over &#8369;2,000</span>
        </div>
      </div>
      <div class="trust-item">
        <i class="fa-solid fa-shield-halved"></i>
        <div>
          <strong>Secure Checkout</strong>
          <span>SSL encrypted payments</span>
        </div>
      </div>
      <div class="trust-item">
        <i class="fa-solid fa-rotate-left"></i>
        <div>
          <strong>Easy Returns</strong>
          <span>30-day return policy</span>
        </div>
      </div>
      <div class="trust-item">
        <i class="fa-regular fa-star"></i>
        <div>
          <strong>Premium Quality</strong>
          <span>Dermatologist tested</span>
        </div>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. Made with <span style="color:var(--primary)">♥</span> in the Philippines.</p>
    <div class="footer-payment">
      <span>We accept:</span>
      <i class="fa-brands fa-cc-visa" title="Visa"></i>
      <i class="fa-brands fa-cc-mastercard" title="Mastercard"></i>
      <span class="gcash-badge">GCash</span>
      <span class="cod-badge">COD</span>
    </div>
  </div>
</footer>

<style>
.site-footer {
  background: var(--text-dark);
  color: rgba(255,255,255,.7);
  padding: 72px var(--section-px) 0;
  margin-top: 80px;
}
.footer-top {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr 1fr 1fr;
  gap: 48px;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255,255,255,.08);
}
.footer-logo {
  font-family: var(--font-serif);
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--primary-light);
  display: block;
  margin-bottom: 14px;
}
.footer-tagline {
  font-size: .85rem;
  line-height: 1.7;
  color: rgba(255,255,255,.55);
  margin-bottom: 22px;
  max-width: 280px;
}
.footer-socials { display: flex; gap: 12px; }
.footer-socials a {
  width: 38px; height: 38px;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,.15);
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(255,255,255,.6);
  font-size: .9rem;
  transition: .25s;
}
.footer-socials a:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: rgba(212,120,154,.1);
}
.footer-links-group h4 {
  font-family: var(--font-serif);
  font-size: 1rem;
  font-weight: 600;
  color: white;
  margin-bottom: 18px;
  letter-spacing: .3px;
}
.footer-links-group ul { display: flex; flex-direction: column; gap: 10px; }
.footer-links-group a {
  font-size: .83rem;
  color: rgba(255,255,255,.55);
  transition: .25s;
  display: inline-block;
}
.footer-links-group a:hover { color: var(--primary-light); transform: translateX(4px); }

/* Trust bar */
.footer-middle { padding: 36px 0; border-bottom: 1px solid rgba(255,255,255,.08); }
.footer-trust {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
}
.trust-item {
  display: flex;
  align-items: center;
  gap: 14px;
}
.trust-item i {
  font-size: 1.6rem;
  color: var(--primary);
  flex-shrink: 0;
}
.trust-item strong {
  display: block;
  font-size: .88rem;
  font-weight: 600;
  color: white;
  margin-bottom: 3px;
}
.trust-item span { font-size: .77rem; color: rgba(255,255,255,.5); }

/* Bottom */
.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  padding: 24px 0;
}
.footer-bottom p { font-size: .82rem; }
.footer-payment {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: .82rem;
}
.footer-payment i { font-size: 1.6rem; color: rgba(255,255,255,.7); }
.gcash-badge, .cod-badge {
  padding: 3px 10px;
  border-radius: 4px;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .5px;
}
.gcash-badge { background: #0070ba; color: white; }
.cod-badge { background: rgba(255,255,255,.15); color: rgba(255,255,255,.8); }

/* Responsive */
@media (max-width: 1024px) {
  .footer-top { grid-template-columns: 1fr 1fr 1fr; }
  .footer-brand { grid-column: 1 / -1; }
  .footer-trust { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
  .footer-top { grid-template-columns: 1fr 1fr; gap: 32px; }
  .footer-trust { grid-template-columns: 1fr; }
  .footer-bottom { flex-direction: column; text-align: center; }
}
</style>
