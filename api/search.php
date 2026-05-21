<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$q = sanitize($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$results = dbFetchAll("
    SELECT id, name, price, image, is_featured, is_bestseller, is_new
    FROM products
    WHERE (name LIKE ? OR description LIKE ?) AND stock > 0
    ORDER BY is_bestseller DESC, review_count DESC
    LIMIT 6
", ["%$q%", "%$q%"]);

echo json_encode($results);
