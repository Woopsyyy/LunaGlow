<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = $input['action'] ?? 'add';
$productId = sanitizeInt($input['product_id'] ?? 0);

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please sign in to use your wishlist.', 'redirect' => APP_URL . '/login.php']);
    exit;
}

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($action === 'add') {
    try {
        dbQuery("INSERT IGNORE INTO wishlist_items (user_id, product_id) VALUES (?, ?)", [$userId, $productId]);
        $count = (int) dbFetchColumn("SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?", [$userId]);
        echo json_encode(['success' => true, 'message' => 'Added to wishlist.', 'count' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Could not add to wishlist.']);
    }
} elseif ($action === 'remove') {
    dbQuery("DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
    $count = (int) dbFetchColumn("SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?", [$userId]);
    echo json_encode(['success' => true, 'message' => 'Removed from wishlist.', 'count' => $count]);
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
