<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME ?? 86400,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── User Auth ─────────────────────────────────────────

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return !empty($_SESSION['admin_id']);
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $url = APP_URL . '/login.php';
        if ($redirect) $url .= '?redirect=' . urlencode($redirect);
        header('Location: ' . $url);
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
    ];
}

// ── Flash Messages ─────────────────────────────────────

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icon = match($flash['type']) {
        'success' => 'fa-circle-check',
        'error'   => 'fa-circle-xmark',
        'warning' => 'fa-triangle-exclamation',
        default   => 'fa-circle-info',
    };
    return '<div class="flash-message flash-' . $flash['type'] . '" id="flashMsg">
        <i class="fa-solid ' . $icon . '"></i>
        <span>' . htmlspecialchars($flash['message']) . '</span>
        <button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
    </div>';
}

// ── CSRF ───────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(?string $token): bool {
    return !empty($token) && !empty($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
