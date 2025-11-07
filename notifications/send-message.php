<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');
$subject = trim($_POST['subject'] ?? 'General Inquiry');

if ($message === '') {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit;
}

// Insert message into database
$sql = "INSERT INTO messages (user_id, subject, message, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $subject, $message);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Message sent successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
}

$stmt->close();
$conn->close();
?>