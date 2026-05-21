<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

try {
    dbQuery("INSERT INTO newsletter_subscribers (email) VALUES (?)", [$email]);
    echo json_encode(['success' => true, 'message' => '🎉 You\'re in! Welcome to the Glow Club.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'This email is already subscribed.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not subscribe. Please try again.']);
    }
}
