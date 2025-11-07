<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$full_address = trim($data['full_address'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$pincode = trim($data['pincode'] ?? '');
$landmark = trim($data['landmark'] ?? '');
$is_default = intval($data['is_default'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($full_address === '' || $city === '' || $state === '' || $pincode === '') {
    echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled']);
    exit;
}

// Validate pincode
if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid pincode format']);
    exit;
}

// If user marks this as default, unset previous default
if ($is_default === 1) {
    $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
}

$sql = "INSERT INTO addresses (user_id, full_address, city, state, pincode, landmark, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isssssi", $user_id, $full_address, $city, $state, $pincode, $landmark, $is_default);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Address added successfully', 'address_id' => $stmt->insert_id]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add address']);
}

$stmt->close();
$conn->close();
?>