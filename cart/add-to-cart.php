<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status'=>'error','message'=>'login_required']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
$selected_size = isset($_POST['size']) ? trim($_POST['size']) : null;

if (!$product_id) {
    echo json_encode(['status'=>'error','message'=>'product_id required']);
    exit;
}

// Check stock
$check = $conn->prepare("SELECT stock, price FROM products WHERE product_id = ?");
$check->bind_param("i", $product_id);
$check->execute();
$prod = $check->get_result()->fetch_assoc();
if (!$prod) {
    echo json_encode(['status'=>'error','message'=>'product_not_found']);
    exit;
}
if ($quantity > (int)$prod['stock']) {
    echo json_encode(['status'=>'error','message'=>'not_enough_stock']);
    exit;
}

// If item already in cart, update quantity
$exists = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND product_id = ? AND selected_size = ?");
$exists->bind_param("iis", $user_id, $product_id, $selected_size);
$exists->execute();
$r = $exists->get_result();

if ($r->num_rows) {
    $row = $r->fetch_assoc();
    $newQ = $row['quantity'] + $quantity;
    $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
    $upd->bind_param("ii", $newQ, $row['cart_id']);
    $upd->execute();
} else {
    $ins = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, selected_size) VALUES (?, ?, ?, ?)");
    $ins->bind_param("iiis", $user_id, $product_id, $quantity, $selected_size);
    $ins->execute();
}

echo json_encode(['status'=>'success','message'=>'added_to_cart']);
$conn->close();
?>