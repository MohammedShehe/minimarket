<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// 1️⃣ Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$cart_id = intval($data['cart_id'] ?? 0);

if ($cart_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid cart item']);
    exit;
}

// 2️⃣ Verify item belongs to this user
$check = $conn->prepare("SELECT cart_id FROM cart WHERE cart_id = ? AND user_id = ?");
$check->bind_param("ii", $cart_id, $user_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Item not found in your cart']);
    exit;
}

// 3️⃣ Delete item
$delete = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
$delete->bind_param("ii", $cart_id, $user_id);

if ($delete->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
}

$check->close();
$delete->close();
$conn->close();
?>