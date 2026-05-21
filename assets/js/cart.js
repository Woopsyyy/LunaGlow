// ============================================================
// Luna Glow — Cart JavaScript
// ============================================================

const BASE = '/xampp/Project/LunaGlow';

// ── Add to Cart ────────────────────────────────────────────
async function addToCart(productId, btn, quantity = 1) {
  const originalText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span>';

  try {
    const res  = await cartAction('add', { product_id: productId, quantity });
    const data = await res;

    if (data.success) {
      btn.innerHTML = '<i class="fa-solid fa-check"></i> Added!';
      btn.style.background = 'var(--success)';
      updateCartBadge(data.cart_count);
      openCart();
      loadCartDrawer();
    } else {
      showToast(data.message || 'Could not add to cart.', 'error');
      btn.innerHTML = originalText;
    }
  } catch {
    showToast('Network error. Please try again.', 'error');
    btn.innerHTML = originalText;
  }

  btn.disabled = false;
  setTimeout(() => {
    btn.innerHTML = originalText;
    btn.style.background = '';
  }, 2500);
}

// ── Cart API Action ────────────────────────────────────────
async function cartAction(action, body, callback) {
  const res  = await fetch(`${BASE}/api/cart.php`, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action, ...body }),
  });
  const data = await res.json();
  if (typeof callback === 'function') callback(data);
  return data;
}

// ── Load Cart Drawer ───────────────────────────────────────
async function loadCartDrawer() {
  const body   = document.getElementById('cartDrawerBody');
  const footer = document.getElementById('cartFooter');
  const empty  = document.getElementById('cartEmpty');
  const list   = document.getElementById('cartItemsList');
  const count  = document.getElementById('cartItemCount');
  if (!body) return;

  try {
    const res  = await fetch(`${BASE}/api/cart.php?action=get`);
    const data = await res.json();
    const items = data.items || [];

    count.textContent = items.length + (items.length === 1 ? ' item' : ' items');

    if (!items.length) {
      list.innerHTML = '';
      empty.style.display = 'block';
      footer.style.display = 'none';
      return;
    }

    empty.style.display = 'none';
    footer.style.display = 'block';

    list.innerHTML = items.map(item => `
      <div class="cart-item" id="drawer-item-${item.product_id}">
        <img src="${item.image}" alt="${escHtml(item.name)}" class="cart-item-img">
        <div class="cart-item-info">
          <div class="cart-item-name">${escHtml(item.name)}</div>
          <div class="cart-item-price">&#8369;${parseFloat(item.price).toFixed(2)}</div>
          <div class="cart-item-controls">
            <button class="qty-btn" onclick="drawerQty(${item.product_id}, -1)"><i class="fa-solid fa-minus"></i></button>
            <span class="qty-value" id="drawer-qty-${item.product_id}">${item.quantity}</span>
            <button class="qty-btn" onclick="drawerQty(${item.product_id}, 1)"><i class="fa-solid fa-plus"></i></button>
          </div>
        </div>
        <button class="cart-item-remove" onclick="drawerRemove(${item.product_id})">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>
    `).join('');

    // Totals
    const subtotal = data.subtotal || 0;
    const shipping = subtotal >= 2000 ? 0 : 150;
    const total    = subtotal + shipping;

    document.getElementById('cartSubtotal').textContent = '&#8369;' + subtotal.toFixed(2);
    document.getElementById('cartShipping').innerHTML  = shipping === 0
      ? '<span style="color:var(--success)">FREE</span>'
      : '&#8369;' + shipping.toFixed(2);
    document.getElementById('cartTotal').textContent   = '&#8369;' + total.toFixed(2);
    updateCartBadge(data.cart_count);
  } catch (e) {
    list.innerHTML = '<p style="text-align:center;color:var(--text-muted);padding:20px;">Could not load cart.</p>';
  }
}

// ── Drawer Quantity Update ─────────────────────────────────
async function drawerQty(productId, delta) {
  const el   = document.getElementById(`drawer-qty-${productId}`);
  const newQ = Math.max(1, parseInt(el.textContent) + delta);
  el.textContent = newQ;
  await cartAction('update', { product_id: productId, quantity: newQ });
  loadCartDrawer();
}

// ── Drawer Remove ──────────────────────────────────────────
async function drawerRemove(productId) {
  const el = document.getElementById(`drawer-item-${productId}`);
  if (el) { el.style.opacity = '.4'; el.style.pointerEvents = 'none'; }
  await cartAction('remove', { product_id: productId });
  loadCartDrawer();
}

// ── Apply Coupon (in drawer) ───────────────────────────────
async function applyCoupon() {
  const code = document.getElementById('couponInput')?.value?.trim();
  const msg  = document.getElementById('couponMsg');
  if (!code) return;
  const data = await cartAction('coupon', { code });
  msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
  msg.textContent = data.message;
  if (data.success) {
    document.getElementById('discountRow').style.display = 'flex';
    document.getElementById('cartDiscount').textContent = '-&#8369;' + parseFloat(data.discount).toFixed(2);
    const newTotal = (data.subtotal - data.discount) + (data.subtotal >= 2000 ? 0 : 150);
    document.getElementById('cartTotal').textContent = '&#8369;' + newTotal.toFixed(2);
    document.getElementById('checkoutBtn').href = `${BASE}/checkout.php?coupon=${encodeURIComponent(code)}`;
  }
}
