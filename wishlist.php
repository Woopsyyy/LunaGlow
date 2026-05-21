<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

// Force the user to sign in to access wishlist, then redirect them to the wishlist page
requireLogin(APP_URL . '/user/wishlist.php');

header('Location: ' . APP_URL . '/user/wishlist.php');
exit;
