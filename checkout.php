<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

$items    = getCartItems();
$subtotal = getCartTotal();
if (empty($items)) { header('Location: ' . APP_URL . '/cart.php'); exit; }

// Handle coupon
$couponCode = sanitize($_GET['coupon'] ?? '');
$discount = 0;
$couponMsg = '';
if ($couponCode) {
    $coupon = dbFetchOne("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) AND used_count < max_uses", [$couponCode]);
    if ($coupon && $subtotal >= $coupon['min_order']) {
        $discount = $coupon['discount_type'] === 'percent'
            ? ($subtotal * $coupon['discount_value'] / 100)
            : $coupon['discount_value'];
    }
}

$shipping = ($subtotal - $discount) >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
$total    = $subtotal - $discount + $shipping;
$user     = getCurrentUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request. Please try again.'); header('Location: checkout.php'); exit;
    }

    $orderNum = generateOrderNumber();
    $name     = sanitize($_POST['shipping_name'] ?? '');
    $email    = sanitize($_POST['shipping_email'] ?? '');
    $phone    = sanitize($_POST['shipping_phone'] ?? '');
    $addr     = sanitize($_POST['shipping_address'] ?? '');
    $city     = sanitize($_POST['shipping_city'] ?? '');
    $province = sanitize($_POST['shipping_province'] ?? '');
    $zip      = sanitize($_POST['shipping_zip'] ?? '');
    $payment  = sanitize($_POST['payment_method'] ?? 'cod');
    $notes    = sanitize($_POST['notes'] ?? '');
    $gcashRef = sanitize($_POST['gcash_ref'] ?? '');
    $couponIn = sanitize($_POST['coupon_code'] ?? '');

    // Recalculate discount server-side
    $svrDiscount = 0;
    if ($couponIn) {
        $cpn = dbFetchOne("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) AND used_count < max_uses", [$couponIn]);
        if ($cpn) $svrDiscount = $cpn['discount_type'] === 'percent' ? ($subtotal * $cpn['discount_value'] / 100) : $cpn['discount_value'];
    }
    $svrShipping = ($subtotal - $svrDiscount) >= FREE_SHIPPING_THRESHOLD ? 0 : SHIPPING_FEE;
    $svrTotal    = $subtotal - $svrDiscount + $svrShipping;

    dbQuery("INSERT INTO orders (order_number, user_id, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_province, shipping_zip, payment_method, subtotal, discount, shipping_fee, total, coupon_code, notes, gcash_ref)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$orderNum, $user ? $user['id'] : null, $name, $email, $phone, $addr, $city, $province, $zip, $payment, $subtotal, $svrDiscount, $svrShipping, $svrTotal, $couponIn ?: null, $notes, $gcashRef ?: null]);

    $orderId = getDB()->lastInsertId();
    foreach ($items as $item) {
        dbQuery("INSERT INTO order_items (order_id, product_id, product_name, product_image, price, quantity) VALUES (?,?,?,?,?,?)",
            [$orderId, $item['product_id'], $item['name'], $item['image'], $item['price'], $item['quantity']]);
        dbQuery("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?",
            [$item['quantity'], $item['product_id'], $item['quantity']]);
    }
    if ($couponIn) dbQuery("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?", [$couponIn]);

    // Clear cart
    if ($user) dbQuery("DELETE FROM cart_items WHERE user_id = ?", [$user['id']]);
    else unset($_SESSION['cart']);

    setFlash('success', "Order #$orderNum placed successfully! 🎉");
    header("Location: " . APP_URL . "/tracking.php?order=$orderNum");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .checkout-layout { display: grid; grid-template-columns: 1fr 400px; gap: 48px; padding: calc(var(--navbar-h)+48px) var(--section-px) var(--section-py); max-width: var(--container); margin: 0 auto; }
  .checkout-card { background: white; border-radius: var(--radius-xl); padding: 36px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-light); margin-bottom: 24px; }
  .checkout-section-title { font-family: var(--font-serif); font-size: 1.4rem; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
  .checkout-section-title .step { width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-family: var(--font-sans); font-size: .82rem; font-weight: 700; flex-shrink: 0; }
  .payment-option { display: flex; align-items: flex-start; gap: 14px; padding: 16px; border: 1.5px solid var(--border); border-radius: var(--radius-md); cursor: pointer; transition: .2s; margin-bottom: 12px; }
  .payment-option:hover { border-color: var(--primary); background: var(--primary-light); }
  .payment-option input[type=radio] { margin-top: 3px; accent-color: var(--primary); }
  .payment-option-info strong { display: block; font-size: .9rem; color: var(--text-dark); }
  .payment-option-info span { font-size: .8rem; color: var(--text-muted); }
  .gcash-field { display: none; margin-top: 14px; }

  /* Order summary sidebar */
  .checkout-summary { background: white; border-radius: var(--radius-xl); padding: 32px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-light); position: sticky; top: calc(var(--navbar-h)+20px); height: fit-content; }
  .checkout-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
  .checkout-item:last-of-type { border-bottom: none; }
  .checkout-item img { width: 56px; height: 56px; border-radius: var(--radius-md); object-fit: cover; background: var(--beige); }
  .checkout-item-qty { position: relative; }
  .checkout-item-qty span { position: absolute; top: -6px; right: -6px; width: 18px; height: 18px; border-radius: 50%; background: var(--primary); color: white; font-size: .65rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
  .checkout-summary-rows { margin-top: 16px; }
  .sum-row { display: flex; justify-content: space-between; font-size: .88rem; padding: 9px 0; border-bottom: 1px solid var(--border-light); }
  .sum-row:last-of-type { border-bottom: none; }
  .sum-total { display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700; color: var(--text-dark); padding: 16px 0; border-top: 2px solid var(--border); margin-top: 8px; }

  @media(max-width:900px) { .checkout-layout { grid-template-columns: 1fr; } .checkout-summary { position: static; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/components/navbar.php'; ?>

<?= renderFlash() ?>

<form method="POST" id="checkoutForm">
  <?= csrfField() ?>
  <input type="hidden" name="coupon_code" value="<?= sanitize($couponCode) ?>">

  <div class="checkout-layout">
    <!-- Left: Form -->
    <div>
      <!-- Shipping Info -->
      <div class="checkout-card">
        <h2 class="checkout-section-title">
          <span class="step">1</span> Shipping Information
        </h2>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="shipping_name" class="form-control" required
                   value="<?= sanitize($user['name'] ?? '') ?>" placeholder="Your full name">
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="shipping_email" class="form-control" required
                   value="<?= sanitize($user['email'] ?? '') ?>" placeholder="your@email.com">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number *</label>
          <input type="tel" name="shipping_phone" class="form-control" required placeholder="09XX XXX XXXX">
        </div>
        <div class="form-group">
          <label class="form-label">Address *</label>
          <input type="text" name="shipping_address" class="form-control" required placeholder="House no., Street, Barangay">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">City / Municipality *</label>
            <input type="text" name="shipping_city" class="form-control" required placeholder="City">
          </div>
          <div class="form-group">
            <label class="form-label">Province *</label>
            <input type="text" name="shipping_province" class="form-control" required placeholder="Province">
          </div>
        </div>
        <div class="form-group" style="max-width:140px;">
          <label class="form-label">ZIP Code</label>
          <input type="text" name="shipping_zip" class="form-control" placeholder="1234">
        </div>
        <div class="form-group">
          <label class="form-label">Order Notes (optional)</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Special instructions for delivery…"></textarea>
        </div>
      </div>

      <!-- Payment -->
      <div class="checkout-card">
        <h2 class="checkout-section-title">
          <span class="step">2</span> Payment Method
        </h2>
        <label class="payment-option" onclick="setPayment('cod')">
          <input type="radio" name="payment_method" value="cod" checked id="payCod">
          <i class="fa-solid fa-money-bill-wave" style="font-size:1.4rem;color:var(--gold);margin-top:2px;"></i>
          <div class="payment-option-info">
            <strong>Cash on Delivery (COD)</strong>
            <span>Pay with cash when your order arrives.</span>
          </div>
        </label>
        <label class="payment-option" onclick="setPayment('gcash')">
          <input type="radio" name="payment_method" value="gcash" id="payGcash">
          <i class="fa-solid fa-mobile-screen-button" style="font-size:1.4rem;color:#007DC5;margin-top:2px;"></i>
          <div class="payment-option-info">
            <strong>GCash</strong>
            <span>Pay via GCash mobile wallet.</span>
            <div class="gcash-field" id="gcashField">
              <div style="margin-top:12px;background:var(--beige);border-radius:var(--radius-md);padding:14px;font-size:.82rem;color:var(--text-body);">
                <strong>GCash Number:</strong> 0917-123-4567 (Luna Glow)<br>
                <strong>Account Name:</strong> Luna Glow PH
              </div>
              <div class="form-group" style="margin-top:12px;">
                <label class="form-label">GCash Reference Number</label>
                <input type="text" name="gcash_ref" id="gcashRef" class="form-control" placeholder="e.g. 1234567890">
              </div>
            </div>
          </div>
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" id="placeOrderBtn">
        <i class="fa-solid fa-lock"></i> Place Order — <?= formatPrice($total) ?>
      </button>
      <p style="text-align:center;font-size:.76rem;color:var(--text-muted);margin-top:12px;">
        By placing your order, you agree to our Terms & Conditions and Privacy Policy.
      </p>
    </div>

    <!-- Right: Summary -->
    <div class="checkout-summary">
      <h3 style="font-family:var(--font-serif);font-size:1.4rem;margin-bottom:20px;">Your Order</h3>
      <?php foreach ($items as $item): ?>
      <div class="checkout-item">
        <div class="checkout-item-qty">
          <img src="<?= sanitize($item['image']) ?>" alt="<?= sanitize($item['name']) ?>">
          <span><?= $item['quantity'] ?></span>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:.88rem;font-weight:500;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($item['name']) ?></div>
        </div>
        <div style="font-size:.9rem;font-weight:600;color:var(--primary);white-space:nowrap;"><?= formatPrice($item['price'] * $item['quantity']) ?></div>
      </div>
      <?php endforeach; ?>

      <div class="checkout-summary-rows">
        <div class="sum-row"><span>Subtotal</span><span><?= formatPrice($subtotal) ?></span></div>
        <?php if ($discount > 0): ?>
        <div class="sum-row"><span style="color:var(--success)">Coupon (<?= sanitize($couponCode) ?>)</span><span style="color:var(--success)">-<?= formatPrice($discount) ?></span></div>
        <?php endif; ?>
        <div class="sum-row">
          <span>Shipping</span>
          <span><?= $shipping === 0 ? '<span style="color:var(--success)">FREE</span>' : formatPrice($shipping) ?></span>
        </div>
      </div>
      <div class="sum-total"><span>Total</span><span><?= formatPrice($total) ?></span></div>

      <div style="margin-top:16px;background:var(--beige);border-radius:var(--radius-md);padding:14px;font-size:.8rem;color:var(--text-muted);">
        <i class="fa-solid fa-shield-halved" style="color:var(--primary);"></i>
        Your information is protected and will never be shared.
      </div>
    </div>
  </div>
</form>

<?php include __DIR__ . '/components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script>
function setPayment(method) {
  document.getElementById('gcashField').style.display = method === 'gcash' ? 'block' : 'none';
  document.getElementById('gcashRef').required = method === 'gcash';
}
document.getElementById('checkoutForm').addEventListener('submit', function() {
  const btn = document.getElementById('placeOrderBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Processing…';
});
</script>
</body>
</html>
