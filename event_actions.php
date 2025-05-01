<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$event_id = $_POST['event_id'] ?? null;

if (!$event_id || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

switch ($action) {
    case 'attend':
        $stmt = $conn->prepare("INSERT INTO user_events (user_id, event_id, status) VALUES (?, ?, 'attending') ON DUPLICATE KEY UPDATE status = 'attending'");
        break;
    case 'complete':
        $stmt = $conn->prepare("UPDATE user_events SET status = 'completed' WHERE user_id = ? AND event_id = ?");
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit();
}

$stmt->bind_param("ii", $user_id, $event_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}