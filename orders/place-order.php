<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
session_start();
header("Content-Type: application/json");
require_once("../config/db_connect.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("Place order request received: " . print_r($_POST, true));

// 1️⃣ — Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in for order placement");
    echo json_encode(['status' => 'error', 'message' => 'login_required']);
    exit;
}

// Check both POST and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

$user_id = (int)$_SESSION['user_id'];
$payment_method = trim($input_data['payment_method'] ?? '');
$address_id = isset($input_data['address_id']) ? (int)$input_data['address_id'] : 0;

error_log("User ID: $user_id, Payment Method: $payment_method, Address ID: $address_id");

// 2️⃣ — Validate payment method
$allowed_methods = ['COD', 'UPI', 'PAYTM', 'PHONEPE'];
if (!in_array(strtoupper($payment_method), $allowed_methods)) {
    error_log("Invalid payment method: $payment_method");
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
    exit;
}

// 3️⃣ — Validate address
if ($address_id <= 0) {
    error_log("Invalid address ID: $address_id");
    echo json_encode(['status' => 'error', 'message' => 'Please select a delivery address']);
    exit;
}

// 4️⃣ — Get address details with better debugging
$address_sql = $conn->prepare("SELECT id, full_address, city, state, pincode, landmark FROM addresses WHERE id = ? AND user_id = ?");
$address_sql->bind_param("ii", $address_id, $user_id);
$address_sql->execute();
$address_result = $address_sql->get_result();

error_log("Address query executed. Found rows: " . $address_result->num_rows);

if ($address_result->num_rows === 0) {
    // Let's check if the address exists but belongs to another user
    $check_any_sql = $conn->prepare("SELECT id FROM addresses WHERE id = ?");
    $check_any_sql->bind_param("i", $address_id);
    $check_any_sql->execute();
    $check_any_result = $check_any_sql->get_result();
    
    if ($check_any_result->num_rows === 0) {
        error_log("Address ID $address_id does not exist in database");
        echo json_encode(['status' => 'error', 'message' => 'Address not found in system']);
    } else {
        error_log("Address ID $address_id exists but doesn't belong to user $user_id");
        echo json_encode(['status' => 'error', 'message' => 'Address does not belong to current user']);
    }
    exit;
}

$address_data = $address_result->fetch_assoc();
$delivery_address = "{$address_data['full_address']}, {$address_data['city']}, {$address_data['state']} - {$address_data['pincode']}";
if (!empty($address_data['landmark'])) {
    $delivery_address .= " (Landmark: {$address_data['landmark']})";
}

error_log("Using delivery address: $delivery_address");

// 5️⃣ — Fetch cart items
$cart_sql = $conn->prepare("
    SELECT c.cart_id, c.product_id, c.quantity, c.selected_size,
           p.price, p.offer_percent, p.stock, p.name
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.user_id = ?
");
$cart_sql->bind_param("i", $user_id);
$cart_sql->execute();
$cart_result = $cart_sql->get_result();

error_log("Cart items found: " . $cart_result->num_rows);

if ($cart_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    $total_order_value = 0;
    $placed_orders = [];

    while ($item = $cart_result->fetch_assoc()) {
        $price = (float)$item['price'];
        $offer = (int)$item['offer_percent'];
        $discount_price = $offer > 0 ? ($price - ($price * $offer / 100)) : $price;
        $item_total = $discount_price * $item['quantity'];
        $total_order_value += $item_total;

        error_log("Processing cart item: {$item['name']}, Qty: {$item['quantity']}, Stock: {$item['stock']}, Price: $price, Offer: $offer%, Total: $item_total");

        // Check stock availability
        if ($item['quantity'] > $item['stock']) {
            throw new Exception("Insufficient stock for {$item['name']}. Available: {$item['stock']}, Requested: {$item['quantity']}");
        }

        // Deduct stock
        $new_stock = $item['stock'] - $item['quantity'];
        $upd_stock = $conn->prepare("UPDATE products SET stock = ? WHERE product_id = ?");
        $upd_stock->bind_param("ii", $new_stock, $item['product_id']);
        if (!$upd_stock->execute()) {
            throw new Exception("Failed to update stock for product {$item['product_id']}");
        }
        $upd_stock->close();

        // Generate unique order number & OTP
        $order_number = "ORD" . time() . rand(100, 999);
        $otp = str_pad(rand(0, 999999), 6, "0", STR_PAD_LEFT);

        // FIXED: Check the actual number of columns in your orders table
        // Let's first try without address_id and delivery_address to see if it works
        $insert = $conn->prepare("
            INSERT INTO orders (user_id, order_number, product_id, quantity, total_price, status, delivery_otp, created_at)
            VALUES (?, ?, ?, ?, ?, 'Placed', ?, NOW())
        ");
        
        if (!$insert) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // FIXED: Correct parameter binding - 7 parameters
        $insert->bind_param(
            "isiiis",
            $user_id,
            $order_number,
            $item['product_id'],
            $item['quantity'],
            $item_total,
            $otp
        );
        
        if (!$insert->execute()) {
            // If the simple insert fails, try with address fields
            $insert->close();
            
            // Try with address fields
            $insert2 = $conn->prepare("
                INSERT INTO orders (user_id, order_number, product_id, quantity, total_price, status, delivery_otp, created_at, address_id, delivery_address)
                VALUES (?, ?, ?, ?, ?, 'Placed', ?, NOW(), ?, ?)
            ");
            
            if (!$insert2) {
                throw new Exception("Prepare with address failed: " . $conn->error);
            }
            
            // FIXED: Correct parameter binding for 9 parameters
            $insert2->bind_param(
                "isiiisiss",
                $user_id,
                $order_number,
                $item['product_id'],
                $item['quantity'],
                $item_total,
                $otp,
                $address_id,
                $delivery_address
            );
            
            if (!$insert2->execute()) {
                throw new Exception("Execute with address failed: " . $insert2->error);
            }
            
            $insert2->close();
            error_log("Order created with address: $order_number for product {$item['product_id']}");
        } else {
            $insert->close();
            error_log("Order created without address: $order_number for product {$item['product_id']}");
        }

        $placed_orders[] = [
            'order_number' => $order_number,
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'quantity' => $item['quantity'],
            'total_price' => round($item_total, 2),
            'otp' => $otp,
            'delivery_address' => $delivery_address
        ];
    }

    // Clear cart
    $clear = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $clear->bind_param("i", $user_id);
    if (!$clear->execute()) {
        throw new Exception("Failed to clear cart");
    }
    $clear->close();

    // Commit all
    $conn->commit();

    error_log("Order placement successful for user $user_id");
    echo json_encode([
        'status' => 'success',
        'message' => 'Order placed successfully',
        'orders' => $placed_orders,
        'total_value' => round($total_order_value, 2)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    // Log the actual error for debugging
    error_log("Order placement error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to place order: ' . $e->getMessage()]);
}

$conn->close();
?>