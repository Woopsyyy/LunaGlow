<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('success', 'You have been signed out. See you soon! 👋');
header('Location: ' . APP_URL . '/index.php');
exit;
