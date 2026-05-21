<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';

session_unset();
session_destroy();
session_start();
setFlash('success', 'Signed out of admin panel.');
header('Location: ' . APP_URL . '/admin/login.php');
exit;
