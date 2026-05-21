<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET: fetch cart items
if ($method === 'GET') {
    $items    = getCartItems();
    $subtotal = getCartTotal();
    echo json_encode([
        'success'    => true,
        'items'      => $items,
        'subtotal'   => $subtotal,
        'cart_count' => getCartCount(),
    ]);
    exit;
}

// POST: cart actions
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

switch ($action) {
    case 'add':
        $productId = sanitizeInt($input['product_id'] ?? 0);
        $quantity  = max(1, (int) ($input['quantity'] ?? 1));
        if (!$productId) { echo json_encode(['success' => false, 'message' => 'Invalid product.']); exit; }

        $product = dbFetchOne("SELECT id, name, price, stock FROM products WHERE id = ?", [$productId]);
        if (!$product) { echo json_encode(['success' => false, 'message' => 'Product not found.']); exit; }
        if ($product['stock'] < $quantity) { echo json_encode(['success' => false, 'message' => 'Not enough stock.']); exit; }

        if (isLoggedIn()) {
            $exists = dbFetchColumn("SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?",
                [$_SESSION['user_id'], $productId]);
            if ($exists) {
                dbQuery("UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?",
                    [$quantity, $_SESSION['user_id'], $productId]);
            } else {
                dbQuery("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)",
                    [$_SESSION['user_id'], $productId, $quantity]);
            }
        } else {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $_SESSION['cart'][$productId] = [
                'quantity' => ($rSESSION['cart'][$productId]['quantity'] ?? 0) + $quantity,
            ];
        }

        echo json_encode(['success' => true, 'message' => 'Added to cart!', 'cart_count' => getCartCount()]);
        break;

    case 'update':
        $productId = sanitizeInt($input['product_id'] ?? 0);
        $quantity  = max(1, (int) ($input['quantity'] ?? 1));

        if (isLoggedIn()) {
            dbQuery("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?",
                [$quantity, $_SESSION['user_id'], $productId]);
        } else {
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['quantity'] = $quantity;
            }
        }
        echo json_encode(['success' => true, 'cart_count' => getCartCount()]);
        break;

    case 'remove':
        $productId = sanitizeInt($input['product_id'] ?? 0);
        if (isLoggedIn()) {
            dbQuery("DELETE FROM cart_items WHERE user_id = ? AND product_id = ?",
                [$_SESSION['user_id'], $productId]);
        } else {
            unset($_SESSION['cart'][$productId]);
        }
        echo json_encode(['success' => true, 'cart_count' => getCartCount()]);
        break;

    case 'coupon':
        $code    = strtoupper(sanitize($input['code'] ?? ''));
        $subtotal = getCartTotal();
        $coupon  = dbFetchOne("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= CURDATE()) AND used_count < max_uses", [$code]);

        if (!$coupon) { echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon.']); exit; }
        if ($subtotal < $coupon['min_order']) {
            echo json_encode(['success' => false, 'message' => "Minimum order of " . formatPrice($coupon['min_order']) . " required."]);
            exit;
        }
        $discount = $coupon['discount_type'] === 'percent'
            ? ($subtotal * $coupon['discount_value'] / 100)
            : $coupon['discount_value'];
        echo json_encode(['success' => true, 'message' => "Coupon applied! You save " . formatPrice($discount), 'discount' => $discount, 'subtotal' => $subtotal]);
        break;

    case 'clear':
        if (isLoggedIn()) dbQuery("DELETE FROM cart_items WHERE user_id = ?", [$_SESSION['user_id']]);
        else unset($_SESSION['cart']);
        echo json_encode(['success' => true, 'cart_count' => 0]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
