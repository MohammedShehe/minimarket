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
$address_id = intval($data['id'] ?? 0);
$full_address = trim($data['full_address'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$pincode = trim($data['pincode'] ?? '');
$landmark = trim($data['landmark'] ?? '');
$is_default = intval($data['is_default'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($address_id <= 0 || $full_address === '' || $city === '' || $state === '' || $pincode === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// Validate pincode
if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid pincode format']);
    exit;
}

// Verify ownership
$check = $conn->prepare("SELECT id FROM addresses WHERE id=? AND user_id=?");
$check->bind_param("ii", $address_id, $user_id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Address not found']);
    exit;
}

// If marked default, reset previous default
if ($is_default === 1) {
    $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = $user_id");
}

$sql = "UPDATE addresses 
        SET full_address=?, city=?, state=?, pincode=?, landmark=?, is_default=? 
        WHERE id=? AND user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssiii", $full_address, $city, $state, $pincode, $landmark, $is_default, $address_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Address updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>