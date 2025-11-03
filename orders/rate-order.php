<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");
session_start();

// 1️⃣ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2️⃣ Get input data - handle both POST form data and JSON
$input_data = $_POST;
if (empty($input_data)) {
    $json_input = file_get_contents("php://input");
    if (!empty($json_input)) {
        $input_data = json_decode($json_input, true);
    }
}

// 3️⃣ Validate that we have input data
if (empty($input_data)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received. Please send order_id, packaging_rating, delivery_rating, and quality_rating']);
    exit;
}

// 4️⃣ Extract and validate required fields
$order_id = isset($input_data['order_id']) ? intval($input_data['order_id']) : 0;
$packaging = isset($input_data['packaging_rating']) ? intval($input_data['packaging_rating']) : 0;
$delivery = isset($input_data['delivery_rating']) ? intval($input_data['delivery_rating']) : 0;
$quality = isset($input_data['quality_rating']) ? intval($input_data['quality_rating']) : 0;

// 5️⃣ Validate order ID
if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

// 6️⃣ Validate ratings (1-5)
if ($packaging < 1 || $packaging > 5 || $delivery < 1 || $delivery > 5 || $quality < 1 || $quality > 5) {
    echo json_encode(['status' => 'error', 'message' => 'All ratings must be between 1 and 5']);
    exit;
}

// 7️⃣ Check if order exists and is delivered
$sql = "SELECT id, order_number FROM orders WHERE id = ? AND user_id = ? AND status = 'Delivered'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Only delivered orders can be rated']);
    exit;
}

$order = $res->fetch_assoc();
$order_number = $order['order_number'];
$stmt->close();

// 8️⃣ Insert or update rating in order_feedback table
// Since there's no unique constraint, we'll check if record exists first
$check_sql = "SELECT id FROM order_feedback WHERE order_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $order_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$feedback_exists = $check_result->num_rows > 0;
$check_stmt->close();

if ($feedback_exists) {
    // Update existing feedback
    $update_sql = "UPDATE order_feedback SET packaging_rating = ?, delivery_rating = ?, quality_rating = ? WHERE order_id = ?";
    $stmt2 = $conn->prepare($update_sql);
    $stmt2->bind_param("iiii", $packaging, $delivery, $quality, $order_id);
    
    if ($stmt2->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Rating updated successfully',
            'order_number' => $order_number
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update rating: ' . $stmt2->error]);
    }
    
    $stmt2->close();
} else {
    // Insert new feedback - ONLY include columns that exist in your table
    $insert_sql = "INSERT INTO order_feedback (order_id, packaging_rating, delivery_rating, quality_rating) VALUES (?, ?, ?, ?)";
    $stmt2 = $conn->prepare($insert_sql);
    $stmt2->bind_param("iiii", $order_id, $packaging, $delivery, $quality);
    
    if ($stmt2->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Rating submitted successfully',
            'order_number' => $order_number
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to submit rating: ' . $stmt2->error]);
    }
    
    $stmt2->close();
}

$conn->close();
?>