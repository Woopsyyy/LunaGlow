<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

// ── Sanitization ───────────────────────────────────────

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt($value): int {
    return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeFloat($value): float {
    return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

// ── Formatting ─────────────────────────────────────────

function formatPrice(float $amount): string {
    return '&#8369;' . number_format($amount, 2);
}

function formatDate(string $datetime, string $format = 'M j, Y'): string {
    return date($format, strtotime($datetime));
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('M j, Y', strtotime($datetime));
}

function truncate(string $text, int $length = 100): string {
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

function slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

// ── Order Helpers ──────────────────────────────────────

function generateOrderNumber(): string {
    return 'LG-' . strtoupper(substr(uniqid(), -6)) . '-' . date('Y');
}

function getStatusBadge(string $status): string {
    $map = [
        'pending'    => ['label' => 'Pending',    'color' => 'var(--status-pending)'],
        'processing' => ['label' => 'Processing', 'color' => 'var(--status-processing)'],
        'shipped'    => ['label' => 'Shipped',    'color' => 'var(--status-shipped)'],
        'delivered'  => ['label' => 'Delivered',  'color' => 'var(--status-delivered)'],
        'cancelled'  => ['label' => 'Cancelled',  'color' => 'var(--status-cancelled)'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'color' => '#999'];
    return '<span class="status-badge" style="background:' . $s['color'] . '">' . $s['label'] . '</span>';
}

// ── Stars ──────────────────────────────────────────────

function starRating(float $rating): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating)          $html .= '<i class="fa-solid fa-star"></i>';
        elseif ($i - 0.5 <= $rating) $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        else                         $html .= '<i class="fa-regular fa-star"></i>';
    }
    return $html . '</span>';
}

// ── Cart Count ─────────────────────────────────────────

function getCartCount(): int {
    if (isLoggedIn()) {
        return (int) (dbFetchColumn(
            'SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?',
            [$_SESSION['user_id']]
        ) ?? 0);
    }
    if (!isset($_SESSION['cart'])) return 0;
    return array_sum(array_column($_SESSION['cart'], 'quantity'));
}

function getWishlistCount(): int {
    if (!isLoggedIn()) return 0;
    return (int) (dbFetchColumn(
        'SELECT COUNT(*) FROM wishlist_items WHERE user_id = ?',
        [$_SESSION['user_id']]
    ) ?? 0);
}

// ── Cart Items ─────────────────────────────────────────

function getCartItems(): array {
    if (isLoggedIn()) {
        return dbFetchAll('
            SELECT ci.id, ci.quantity, p.id AS product_id, p.name, p.price, p.image, p.stock
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.user_id = ?
            ORDER BY ci.created_at DESC
        ', [$_SESSION['user_id']]);
    }
    // Guest cart from session
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) return [];
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $products = dbFetchAll("SELECT id, name, price, image, stock FROM products WHERE id IN ($ids)");
    $items = [];
    foreach ($products as $p) {
        $q = $cart[$p['id']]['quantity'] ?? 1;
        $items[] = array_merge($p, ['product_id' => $p['id'], 'quantity' => $q]);
    }
    return $items;
}

function getCartTotal(): float {
    $items = getCartItems();
    return array_reduce($items, fn($sum, $i) => $sum + ($i['price'] * $i['quantity']), 0);
}

// ── User Helpers ──────────────────────────────────────

if (!function_exists('getUserById')) {
    function getUserById(int $id): ?array {
        return dbFetchOne("SELECT * FROM users WHERE id = ?", [$id]) ?: null;
    }
}

// ── Image ──────────────────────────────────────────────

function productImage(string $image): string {
    if (!$image) return APP_URL . '/assets/images/placeholder.jpg';
    if (str_starts_with($image, 'http')) return $image;
    return APP_URL . '/assets/images/uploads/' . $image;
}

// ── Pagination ─────────────────────────────────────────

function paginate(string $sql, array $params, int $perPage, int $page): array {
    $total   = (int) dbFetchColumn("SELECT COUNT(*) FROM ($sql) t", $params);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = max(1, min($page, $pages));
    $offset  = ($page - 1) * $perPage;
    $rows    = dbFetchAll("$sql LIMIT $perPage OFFSET $offset", $params);
    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'current' => $page];
}
