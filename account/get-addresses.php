<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT id, full_address, city, state, pincode, landmark, is_default 
        FROM addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC, id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$addresses = [];
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}

echo json_encode(['status' => 'success', 'addresses' => $addresses]);

$stmt->close();
$conn->close();
?>