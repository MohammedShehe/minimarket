<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

$sql = "SELECT status FROM orders WHERE id=? AND user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

$order = $res->fetch_assoc();

if ($order['status'] !== 'Placed') {
    echo json_encode(['status' => 'error', 'message' => 'Cannot cancel this order']);
    exit;
}

$update = $conn->prepare("UPDATE orders SET status='Cancelled' WHERE id=? AND user_id=?");
$update->bind_param("ii", $order_id, $user_id);
if ($update->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Order cancelled successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel order']);
}

$stmt->close();
$update->close();
$conn->close();
?>