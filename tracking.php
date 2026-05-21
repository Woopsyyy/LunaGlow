<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/helpers.php';

$orderNum  = sanitize($_GET['order'] ?? '');
$order     = null;
$orderItems = [];

if ($orderNum) {
    $order      = dbFetchOne("SELECT * FROM orders WHERE order_number = ?", [$orderNum]);
    if ($order) $orderItems = dbFetchAll("SELECT * FROM order_items WHERE order_id = ?", [$order['id']]);
}

$statusSteps = ['pending', 'processing', 'shipped', 'delivered'];
$currentStep = $order ? array_search($order['status'], $statusSteps) : -1;
if ($order && $order['status'] === 'cancelled') $currentStep = -1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Order — Luna Glow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
  .tracking-card { background: white; border-radius: var(--radius-xl); padding: 40px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-light); margin-bottom: 28px; }
  .tracking-form { display: flex; gap: 12px; margin-bottom: 0; }
  .tracking-form input { flex: 1; }
  .order-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 32px; }
  .order-num { font-family: var(--font-serif); font-size: 1.6rem; margin-bottom: 4px; }

  /* Progress timeline */
  .tracking-timeline { display: flex; align-items: flex-start; gap: 0; margin: 32px 0; }
  .timeline-step { flex: 1; display: flex; flex-direction: column; align-items: center; position: relative; }
  .timeline-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 20px; left: 50%; right: -50%;
    height: 3px;
    background: var(--border);
    z-index: 0;
    transition: background .5s ease;
  }
  .timeline-step.done:not(:last-child)::after { background: var(--primary); }
  .step-icon {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: var(--beige);
    border: 3px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: var(--text-muted);
    z-index: 1;
    transition: .4s;
  }
  .timeline-step.done .step-icon {
    background: var(--primary-light);
    border-color: var(--primary);
    color: var(--primary);
  }
  .timeline-step.current .step-icon {
    background: var(--primary);
    border-color: var(--primary-dark);
    color: white;
    box-shadow: 0 0 0 6px var(--primary-glow);
  }
  .step-label { font-size: .78rem; font-weight: 600; margin-top: 10px; color: var(--text-muted); text-align: center; }
  .timeline-step.done .step-label, .timeline-step.current .step-label { color: var(--primary); }

  .cancelled-banner { background: #fef2f2; border: 1px solid #fca5a5; border-radius: var(--radius-md); padding: 16px 20px; color: #991b1b; display: flex; align-items: center; gap: 12px; margin: 20px 0; }

  .order-items-table { width: 100%; border-collapse: collapse; }
  .order-items-table th { font-size: .72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted); padding-bottom: 12px; border-bottom: 1px solid var(--border); text-align: left; }
  .order-items-table td { padding: 14px 0; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
  .order-items-table img { width: 56px; height: 56px; border-radius: var(--radius-md); object-fit: cover; }

  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .info-block h4 { font-size: .78rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--primary); margin-bottom: 10px; }
  .info-block p { font-size: .88rem; line-height: 1.7; color: var(--text-body); }
  @media(max-width:640px) { .info-grid { grid-template-columns: 1fr; } .tracking-timeline { flex-direction: column; gap: 16px; } .timeline-step::after { display: none; } }
  </style>
</head>
<body>
<?php include __DIR__ . '/components/navbar.php'; ?>
<?php include __DIR__ . '/components/cart-drawer.php'; ?>
<?= renderFlash() ?>

<div class="page-hero">
  <h1>Order Tracking</h1>
  <p>Enter your order number to track your Luna Glow delivery.</p>
</div>

<div class="section" style="padding-top:48px;max-width:860px;margin:0 auto;">
  <!-- Search Form -->
  <div class="tracking-card">
    <h3 style="font-size:1.2rem;margin-bottom:20px;">Track Your Order</h3>
    <form method="GET" class="tracking-form">
      <input type="text" name="order" class="form-control" placeholder="Order number (e.g. LG-ABC123-2026)"
             value="<?= sanitize($orderNum) ?>" required>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Track</button>
    </form>
  </div>

  <?php if ($orderNum && !$order): ?>
  <div class="tracking-card">
    <div class="empty-state" style="padding:40px 20px;">
      <div class="empty-state-icon"><i class="fa-solid fa-box-open"></i></div>
      <h3>Order Not Found</h3>
      <p>We couldn't find order #<?= sanitize($orderNum) ?>. Please check the number and try again.</p>
    </div>
  </div>

  <?php elseif ($order): ?>
  <!-- Order Header -->
  <div class="tracking-card">
    <div class="order-header">
      <div>
        <div class="order-num">Order #<?= sanitize($order['order_number']) ?></div>
        <p style="font-size:.84rem;color:var(--text-muted);">
          Placed on <?= formatDate($order['created_at'], 'F j, Y \a\t g:i A') ?>
        </p>
      </div>
      <?= getStatusBadge($order['status']) ?>
    </div>

    <?php if ($order['status'] === 'cancelled'): ?>
    <div class="cancelled-banner">
      <i class="fa-solid fa-circle-xmark fa-lg"></i>
      <div><strong>This order has been cancelled.</strong><br>Please contact us if you have questions.</div>
    </div>
    <?php else: ?>
    <!-- Timeline -->
    <div class="tracking-timeline">
      <?php
      $icons = ['fa-clock', 'fa-gear', 'fa-truck', 'fa-circle-check'];
      $labels = ['Order Placed', 'Processing', 'Shipped', 'Delivered'];
      foreach ($statusSteps as $i => $step):
        $cls = '';
        if ($i < $currentStep) $cls = 'done';
        elseif ($i === $currentStep) $cls = 'current';
      ?>
      <div class="timeline-step <?= $cls ?>">
        <div class="step-icon"><i class="fa-solid <?= $icons[$i] ?>"></i></div>
        <div class="step-label"><?= $labels[$i] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Shipping & Payment Info -->
    <div class="info-grid" style="margin-top:24px;">
      <div class="info-block">
        <h4>Shipping To</h4>
        <p>
          <strong><?= sanitize($order['shipping_name']) ?></strong><br>
          <?= sanitize($order['shipping_address']) ?><br>
          <?= sanitize($order['shipping_city']) ?>, <?= sanitize($order['shipping_province']) ?> <?= sanitize($order['shipping_zip']) ?><br>
          <?= sanitize($order['shipping_phone']) ?>
        </p>
      </div>
      <div class="info-block">
        <h4>Payment</h4>
        <p>
          <strong><?= strtoupper($order['payment_method']) ?></strong><br>
          <?php if ($order['gcash_ref']): ?>GCash Ref: <?= sanitize($order['gcash_ref']) ?><br><?php endif; ?>
          <?php if ($order['coupon_code']): ?>Coupon: <?= sanitize($order['coupon_code']) ?><br><?php endif; ?>
          Total: <strong><?= formatPrice($order['total']) ?></strong>
        </p>
      </div>
    </div>
  </div>

  <!-- Order Items -->
  <div class="tracking-card">
    <h3 style="font-size:1.2rem;margin-bottom:20px;">Order Items</h3>
    <table class="order-items-table">
      <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($orderItems as $item): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:14px;">
            <img src="<?= productImage($item['product_image']) ?>" alt="<?= sanitize($item['product_name']) ?>">
            <span style="font-size:.88rem;font-weight:500;color:var(--text-dark);"><?= sanitize($item['product_name']) ?></span>
          </div>
        </td>
        <td><?= formatPrice($item['price']) ?></td>
        <td><?= $item['quantity'] ?></td>
        <td style="font-weight:600;color:var(--primary);"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="display:flex;justify-content:flex-end;margin-top:20px;gap:24px;flex-wrap:wrap;padding-top:16px;border-top:1px solid var(--border);">
      <span style="font-size:.88rem;color:var(--text-muted);">Subtotal: <?= formatPrice($order['subtotal']) ?></span>
      <?php if ($order['discount'] > 0): ?><span style="color:var(--success);font-size:.88rem;">Discount: -<?= formatPrice($order['discount']) ?></span><?php endif; ?>
      <span style="font-size:.88rem;color:var(--text-muted);">Shipping: <?= $order['shipping_fee'] > 0 ? formatPrice($order['shipping_fee']) : 'FREE' ?></span>
      <span style="font-size:1rem;font-weight:700;color:var(--text-dark);">Total: <?= formatPrice($order['total']) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <div style="text-align:center;">
    <a href="<?= APP_URL ?>/contact.php" style="color:var(--text-muted);font-size:.84rem;">Need help? Contact us <i class="fa-solid fa-arrow-right"></i></a>
  </div>
</div>

<?php include __DIR__ . '/components/footer.php'; ?>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
<script src="<?= APP_URL ?>/assets/js/cart.js"></script>
</body>
</html>
