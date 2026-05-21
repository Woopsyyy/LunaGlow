<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please sign in to leave a review.']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$productId = sanitizeInt($input['product_id'] ?? 0);
$rating    = min(5, max(1, (int) ($input['rating'] ?? 5)));
$title     = sanitize($input['title'] ?? '');
$body      = sanitize($input['body'] ?? '');

if (!$productId || !$body) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

$existing = dbFetchColumn("SELECT COUNT(*) FROM reviews WHERE product_id = ? AND user_id = ?",
    [$productId, $_SESSION['user_id']]);
if ($existing) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this product.']);
    exit;
}

$user = dbFetchOne("SELECT name FROM users WHERE id = ?", [$_SESSION['user_id']]);
dbQuery("INSERT INTO reviews (product_id, user_id, reviewer_name, rating, title, body) VALUES (?,?,?,?,?,?)",
    [$productId, $_SESSION['user_id'], $user['name'], $rating, $title, $body]);

echo json_encode(['success' => true, 'message' => 'Thank you! Your review is pending approval.']);
