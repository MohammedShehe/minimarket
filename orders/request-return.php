<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");
session_start();

// ✅ Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

if ($reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Return reason is required']);
    exit;
}

// ✅ Step 1: Check order ownership & status
$sql = "SELECT status, delivered_at, product_id FROM orders WHERE id=? AND user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

$order = $res->fetch_assoc();

// ✅ Step 2: Ensure it's delivered
if ($order['status'] !== 'Delivered') {
    echo json_encode(['status' => 'error', 'message' => 'Only delivered orders can be returned']);
    exit;
}

// ✅ Step 3: Check return window (3 days)
$delivered_at = strtotime($order['delivered_at']);
if (time() - $delivered_at > 3 * 24 * 60 * 60) { // 3 days
    echo json_encode(['status' => 'error', 'message' => 'Return window expired (3 days)']);
    exit;
}

// ✅ Step 4: Mark order as Returned
$update = $conn->prepare("UPDATE orders SET status='Returned' WHERE id=? AND user_id=?");
$update->bind_param("ii", $order_id, $user_id);
$update->execute();

// ✅ Step 5: Store reason
$stmt2 = $conn->prepare("
    INSERT INTO order_feedback (order_id, product_id, return_reason)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE return_reason = VALUES(return_reason)
");
$stmt2->bind_param("iis", $order_id, $order['product_id'], $reason);
$stmt2->execute();

echo json_encode(['status' => 'success', 'message' => 'Return request submitted successfully']);

$stmt->close();
$update->close();
$stmt2->close();
$conn->close();
?>