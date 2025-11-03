<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// 1️⃣ Check user session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2️⃣ Fetch items from cart
$sql = "
SELECT 
    c.cart_id,
    c.product_id,
    c.quantity,
    c.selected_size,
    p.name AS product_name,
    p.price,
    p.offer_percent,
    p.stock,
    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.product_id ORDER BY position ASC LIMIT 1) AS image
FROM cart c
JOIN products p ON c.product_id = p.product_id
WHERE c.user_id = ?
ORDER BY c.cart_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total_price = 0;
$total_items = 0;

// 3️⃣ Build response data
while ($row = $result->fetch_assoc()) {
    $price = (float)$row['price'];
    $offer_percent = (int)$row['offer_percent'];

    // Apply discount if applicable
    $discounted_price = $price;
    if ($offer_percent > 0) {
        $discounted_price = $price - ($price * $offer_percent / 100);
    }

    $subtotal = $discounted_price * (int)$row['quantity'];
    $total_price += $subtotal;
    $total_items += (int)$row['quantity'];

    // Add full image path
    $image_path = $row['image'] ? "http://localhost/phpproj/lawgate_mini_market/" . $row['image'] : null;

    $cart_items[] = [
        'cart_id'        => (int)$row['cart_id'],
        'product_id'     => (int)$row['product_id'],
        'product_name'   => $row['product_name'],
        'price'          => $price,
        'offer_percent'  => $offer_percent,
        'discounted_price' => round($discounted_price, 2),
        'quantity'       => (int)$row['quantity'],
        'selected_size'  => $row['selected_size'],
        'stock'          => (int)$row['stock'],
        'image'          => $image_path,
        'subtotal'       => round($subtotal, 2)
    ];
}

// 4️⃣ Return response
echo json_encode([
    'status' => 'success',
    'total_items' => $total_items,
    'total_price' => round($total_price, 2),
    'cart' => $cart_items
]);

$stmt->close();
$conn->close();
?>