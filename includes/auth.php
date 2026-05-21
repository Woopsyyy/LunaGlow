<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

// ── User Authentication ────────────────────────────────

function loginUser(string $email, string $password): bool {
    $user = dbFetchOne('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        // Merge guest cart into user cart
        mergeGuestCart($user['id']);
        return true;
    }
    return false;
}

function loginAdmin(string $email, string $password): bool {
    $admin = dbFetchOne('SELECT * FROM admins WHERE email = ?', [$email]);
    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']    = $admin['id'];
        $_SESSION['admin_name']  = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        return true;
    }
    return false;
}

function registerUser(string $name, string $email, string $password, string $phone = ''): array {
    // Check existing email
    $exists = dbFetchColumn('SELECT COUNT(*) FROM users WHERE email = ?', [$email]);
    if ($exists) return ['success' => false, 'error' => 'Email already registered.'];

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    dbQuery('INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)',
        [$name, $email, $hashed, $phone]);
    return ['success' => true];
}

function logoutUser(): void {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
    session_regenerate_id(true);
}

function logoutAdmin(): void {
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email']);
    session_regenerate_id(true);
}

// ── Cart Merge (guest -> user on login) ───────────────

function mergeGuestCart(int $userId): void {
    if (empty($_SESSION['cart'])) return;
    foreach ($_SESSION['cart'] as $productId => $item) {
        $exists = dbFetchColumn(
            'SELECT id FROM cart_items WHERE user_id = ? AND product_id = ?',
            [$userId, $productId]
        );
        if ($exists) {
            dbQuery('UPDATE cart_items SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?',
                [$item['quantity'], $userId, $productId]);
        } else {
            dbQuery('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)',
                [$userId, $productId, $item['quantity']]);
        }
    }
    unset($_SESSION['cart']);
}
