<?php
/**
 * Cart Drawer Component
 * Include once per page. Controlled via JS.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
?>
<!-- Cart Drawer Overlay -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>

<!-- Cart Drawer -->
<aside class="cart-drawer" id="cartDrawer" aria-label="Shopping Cart">
  <div class="cart-drawer-header">
    <div>
      <h3 class="cart-drawer-title">My Bag</h3>
      <span class="cart-drawer-count" id="cartItemCount">0 items</span>
    </div>
    <button class="cart-close" onclick="closeCart()" aria-label="Close cart">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <div class="cart-drawer-body" id="cartDrawerBody">
    <div class="cart-loading" id="cartLoading" style="display:none;">
      <div class="spinner"></div>
    </div>
    <div id="cartItemsList"></div>
    <div class="cart-empty" id="cartEmpty" style="display:none;">
      <div class="cart-empty-icon"><i class="fa-regular fa-bag-shopping"></i></div>
      <h4>Your bag is empty</h4>
      <p>Add some beautiful products to get started!</p>
      <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary btn-sm" onclick="closeCart()">
        <i class="fa-solid fa-sparkles"></i> Shop Now
      </a>
    </div>
  </div>

  <div class="cart-drawer-footer" id="cartFooter" style="display:none;">
    <div class="cart-coupon">
      <input type="text" id="couponInput" placeholder="Coupon code…" class="form-control">
      <button class="btn btn-outline btn-sm" onclick="applyCoupon()">Apply</button>
    </div>
    <div id="couponMsg" style="font-size:.8rem;margin-top:6px;"></div>

    <div class="cart-totals">
      <div class="cart-total-row">
        <span>Subtotal</span>
        <span id="cartSubtotal">₱0.00</span>
      </div>
      <div class="cart-total-row" id="discountRow" style="display:none;">
        <span style="color:var(--success)">Discount</span>
        <span id="cartDiscount" style="color:var(--success)">-₱0.00</span>
      </div>
      <div class="cart-total-row">
        <span>Shipping</span>
        <span id="cartShipping">₱150.00</span>
      </div>
      <div class="cart-total-row cart-total-final">
        <span>Total</span>
        <span id="cartTotal">₱0.00</span>
      </div>
    </div>

    <a href="<?= APP_URL ?>/checkout.php" class="btn btn-primary btn-full" id="checkoutBtn">
      <i class="fa-solid fa-lock"></i> Secure Checkout
    </a>
    <a href="<?= APP_URL ?>/cart.php" class="btn btn-ghost btn-full" style="margin-top:8px;font-size:.85rem;" onclick="closeCart()">
      View Full Cart
    </a>
  </div>
</aside>

<style>
.cart-overlay {
  position: fixed; inset: 0;
  background: rgba(42,31,40,.5);
  backdrop-filter: blur(4px);
  z-index: 1100;
  opacity: 0;
  pointer-events: none;
  transition: .35s;
}
.cart-overlay.active { opacity: 1; pointer-events: all; }

.cart-drawer {
  position: fixed;
  top: 0; right: 0;
  width: 420px;
  max-width: 100vw;
  height: 100vh;
  background: white;
  z-index: 1200;
  display: flex;
  flex-direction: column;
  transform: translateX(100%);
  transition: transform .42s cubic-bezier(.16,1,.3,1);
  box-shadow: -10px 0 50px rgba(0,0,0,.12);
}
.cart-drawer.open { transform: translateX(0); }

.cart-drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px 28px;
  border-bottom: 1px solid var(--border);
}
.cart-drawer-title { font-family: var(--font-serif); font-size: 1.4rem; margin-bottom: 2px; }
.cart-drawer-count { font-size: .78rem; color: var(--text-muted); }
.cart-close {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--beige);
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted);
  font-size: .9rem;
  cursor: pointer;
  transition: .2s;
  border: none;
}
.cart-close:hover { background: var(--primary-light); color: var(--primary); }

.cart-drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 28px;
}
.cart-loading { display: flex; justify-content: center; padding: 40px; }

.cart-empty { text-align: center; padding: 48px 20px; }
.cart-empty-icon {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: var(--primary-light);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 16px;
  font-size: 1.8rem;
  color: var(--primary);
}
.cart-empty h4 { font-size: 1.2rem; margin-bottom: 8px; }
.cart-empty p { font-size: .88rem; color: var(--text-muted); margin-bottom: 20px; }

/* Cart Items */
.cart-item {
  display: flex;
  gap: 14px;
  padding: 16px 0;
  border-bottom: 1px solid var(--border-light);
  align-items: flex-start;
}
.cart-item:last-child { border-bottom: none; }
.cart-item-img {
  width: 72px; height: 72px;
  border-radius: var(--radius-md);
  object-fit: cover;
  background: var(--beige);
  flex-shrink: 0;
}
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-name {
  font-size: .88rem;
  font-weight: 500;
  color: var(--text-dark);
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cart-item-price { font-size: .88rem; font-weight: 600; color: var(--primary); margin-bottom: 10px; }
.cart-item-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}
.qty-btn {
  width: 28px; height: 28px;
  border-radius: 50%;
  border: 1.5px solid var(--border);
  background: white;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  font-size: .8rem;
  transition: .2s;
  color: var(--text-body);
}
.qty-btn:hover { border-color: var(--primary); color: var(--primary); }
.qty-value { font-size: .88rem; font-weight: 600; width: 20px; text-align: center; }
.cart-item-remove {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: none;
  border: none;
  color: var(--text-muted);
  cursor: pointer;
  font-size: .82rem;
  transition: .2s;
  display: flex; align-items: center; justify-content: center;
}
.cart-item-remove:hover { color: var(--danger); background: #fef2f2; }

/* Footer */
.cart-drawer-footer {
  padding: 20px 28px 28px;
  border-top: 1px solid var(--border);
  background: white;
}
.cart-coupon { display: flex; gap: 10px; margin-bottom: 4px; }
.cart-coupon .form-control { padding: 10px 14px; font-size: .85rem; border-radius: var(--radius-pill); }

.cart-totals { margin: 16px 0; }
.cart-total-row {
  display: flex;
  justify-content: space-between;
  font-size: .88rem;
  color: var(--text-body);
  padding: 7px 0;
  border-bottom: 1px solid var(--border-light);
}
.cart-total-row:last-child { border-bottom: none; }
.cart-total-final {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-dark);
  padding-top: 12px;
}

@media (max-width: 480px) {
  .cart-drawer { width: 100vw; }
  .cart-drawer-header,
  .cart-drawer-body,
  .cart-drawer-footer { padding-left: 20px; padding-right: 20px; }
}
</style>
