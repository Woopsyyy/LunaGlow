<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

$items    = getCartItems();
$subtotal = getCartTotal();
$shipping = $subtotal >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
$total    = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Cart — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .cart-page { display: grid; grid-template-columns: 1fr 380px; gap: 40px; padding: calc(var(--navbar-h)+48px) var(--section-px) var(--section-py); max-width: var(--container); margin:0 auto; }
  .cart-table { width: 100%; border-collapse: collapse; }
  .cart-table th { font-size: .72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding: 0 0 16px; border-bottom: 1px solid var(--border); text-align: left; }
  .cart-table td { padding: 20px 0; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
  .cart-table .product-col { display: flex; align-items: center; gap: 16px; }
  .cart-table img { width: 80px; height: 80px; border-radius: var(--radius-md); object-fit: cover; }
  .cart-item-name { font-weight: 600; color: var(--text-dark); font-size: .9rem; margin-bottom: 4px; }
  .cart-item-cat { font-size: .78rem; color: var(--text-muted); }
  .remove-btn { color: var(--text-muted); cursor: pointer; font-size: .82rem; transition: .2s; background: none; border: none; }
  .remove-btn:hover { color: var(--danger); }
  .qty-ctrl { display: flex; align-items: center; gap: 0; border: 1.5px solid var(--border); border-radius: var(--radius-pill); width: fit-content; overflow: hidden; }
  .qty-ctrl button { width: 32px; height: 36px; background: var(--beige); border: none; cursor: pointer; font-size: .9rem; transition: .2s; }
  .qty-ctrl button:hover { background: var(--primary-light); color: var(--primary); }
  .qty-ctrl span { width: 36px; text-align: center; font-size: .88rem; font-weight: 600; }

  .order-summary { background: white; border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-light); position: sticky; top: calc(var(--navbar-h)+20px); height: fit-content; }
  .summary-title { font-family: var(--font-serif); font-size: 1.5rem; margin-bottom: 24px; }
  .summary-row { display: flex; justify-content: space-between; font-size: .88rem; padding: 10px 0; border-bottom: 1px solid var(--border-light); color: var(--text-body); }
  .summary-row:last-of-type { border-bottom: none; }
  .summary-total { display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700; color: var(--text-dark); padding: 16px 0; border-top: 2px solid var(--border); margin-top: 8px; }
  .free-ship-bar { background: var(--primary-light); border-radius: var(--radius-md); padding: 12px 16px; margin-bottom: 20px; font-size: .82rem; }
  .ship-progress { height: 4px; background: var(--border); border-radius: 2px; margin-top: 8px; overflow: hidden; }
  .ship-progress-fill { height: 100%; background: var(--primary); border-radius: 2px; transition: .5s; }
  .coupon-row { display: flex; gap: 8px; margin: 16px 0; }
  .coupon-row input { flex: 1; }

  @media(max-width:900px) {
    .cart-page { grid-template-columns: 1fr; }
    .order-summary { position: static; }
    .cart-table th:nth-child(3), .cart-table td:nth-child(3) { display: none; }
  }
  </style>
</head>
<body>
<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>

<div class="cart-page">
  <!-- Cart Items -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;">
      <h1 style="font-size:2rem;">Shopping Bag</h1>
      <span style="font-size:.88rem;color:var(--text-muted);"><?= count($items) ?> <?= count($items)===1?'item':'items' ?></span>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa-regular fa-bag-shopping"></i></div>
      <h3>Your bag is empty</h3>
      <p>Looks like you haven't added anything yet.</p>
      <a href="<?= APP_URL ?>/shop.php" class="btn btn-primary"><i class="fa-solid fa-sparkles"></i> Start Shopping</a>
    </div>
    <?php else: ?>
    <table class="cart-table" id="cartTable">
      <thead>
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Total</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="cartTableBody">
      <?php foreach ($items as $item): ?>
      <tr id="row-<?= $item['product_id'] ?>">
        <td>
          <div class="product-col">
            <img src="<?= sanitize($item['image']) ?>" alt="<?= sanitize($item['name']) ?>">
            <div>
              <a href="<?= APP_URL ?>/product.php?id=<?= $item['product_id'] ?>">
                <div class="cart-item-name"><?= sanitize($item['name']) ?></div>
              </a>
            </div>
          </div>
        </td>
        <td><?= formatPrice($item['price']) ?></td>
        <td>
          <div class="qty-ctrl">
            <button onclick="updateQty(<?= $item['product_id'] ?>, -1)"><i class="fa-solid fa-minus"></i></button>
            <span id="qty-<?= $item['product_id'] ?>"><?= $item['quantity'] ?></span>
            <button onclick="updateQty(<?= $item['product_id'] ?>, 1)"><i class="fa-solid fa-plus"></i></button>
          </div>
        </td>
        <td style="font-weight:600;color:var(--primary);" id="line-<?= $item['product_id'] ?>">
          <?= formatPrice($item['price'] * $item['quantity']) ?>
        </td>
        <td>
          <button class="remove-btn" onclick="removeFromCart(<?= $item['product_id'] ?>)" title="Remove">
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
      <a href="<?= APP_URL ?>/shop.php" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Continue Shopping</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Order Summary -->
  <?php if (!empty($items)): ?>
  <div class="order-summary">
    <h3 class="summary-title">Order Summary</h3>

    <!-- Free shipping progress -->
    <?php $remaining = FREE_SHIPPING_THRESHOLD - $subtotal; $pct = min(100, ($subtotal / FREE_SHIPPING_THRESHOLD) * 100); ?>
    <div class="free-ship-bar">
      <?php if ($remaining > 0): ?>
        <span>Add <strong><?= formatPrice($remaining) ?></strong> more for <strong>free shipping!</strong></span>
      <?php else: ?>
        <span>🎉 You qualify for <strong>FREE shipping!</strong></span>
      <?php endif; ?>
      <div class="ship-progress">
        <div class="ship-progress-fill" style="width:<?= $pct ?>%"></div>
      </div>
    </div>

    <!-- Coupon -->
    <div class="coupon-row">
      <input type="text" id="couponCode" class="form-control" placeholder="Coupon code">
      <button class="btn btn-outline btn-sm" onclick="applyCoupon()">Apply</button>
    </div>
    <div id="couponMsg" style="font-size:.8rem;margin-bottom:12px;"></div>
    <input type="hidden" id="discountAmount" value="0">
    <input type="hidden" id="couponApplied" value="">

    <div class="summary-row"><span>Subtotal</span><span id="pageSubtotal"><?= formatPrice($subtotal) ?></span></div>
    <div class="summary-row" id="discRow" style="display:none"><span style="color:var(--success)">Discount</span><span id="discVal" style="color:var(--success)"></span></div>
    <div class="summary-row"><span>Shipping</span><span id="pageShipping"><?= $shipping === 0 ? '<span style="color:var(--success)">FREE</span>' : formatPrice($shipping) ?></span></div>
    <div class="summary-total"><span>Total</span><span id="pageTotal"><?= formatPrice($total) ?></span></div>

    <a href="<?= APP_URL ?>/checkout.php" id="checkoutPageBtn" class="btn btn-primary btn-full btn-lg" style="margin-top:20px;">
      <i class="fa-solid fa-lock"></i> Proceed to Checkout
    </a>
    <p style="text-align:center;font-size:.76rem;color:var(--text-muted);margin-top:12px;">
      <i class="fa-solid fa-shield-halved"></i> Secure & encrypted checkout
    </p>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
<script>
function updateQty(id, d) {
  const el = document.getElementById('qty-' + id);
  const newQty = Math.max(1, parseInt(el.textContent) + d);
  cartAction('update', {product_id: id, quantity: newQty}, () => {
    el.textContent = newQty;
    location.reload();
  });
}
function removeFromCart(id) {
  if (!confirm('Remove this item?')) return;
  cartAction('remove', {product_id: id}, () => location.reload());
}
function applyCoupon() {
  const code = document.getElementById('couponCode').value.trim();
  const msg = document.getElementById('couponMsg');
  if (!code) return;
  fetch('<?= APP_URL ?>/api/cart.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'coupon', code})
  }).then(r => r.json()).then(d => {
    msg.style.color = d.success ? 'var(--success)' : 'var(--danger)';
    msg.textContent = d.message;
    if (d.success) {
      document.getElementById('discountAmount').value = d.discount;
      document.getElementById('couponApplied').value = code;
      document.getElementById('discRow').style.display = 'flex';
      document.getElementById('discVal').textContent = '-<?= CURRENCY_SYMBOL ?>' + parseFloat(d.discount).toFixed(2);
      const btn = document.getElementById('checkoutPageBtn');
      btn.href = '<?= APP_URL ?>/checkout.php?coupon=' + encodeURIComponent(code);
    }
  });
}
</script>
</body>
</html>
