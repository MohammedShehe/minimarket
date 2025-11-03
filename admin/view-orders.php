<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// ✅ Fixed table and column names
$sql = "
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.quantity,
        o.total_price,
        o.status,
        o.delivery_otp,
        o.delivered_at,
        o.created_at,
        p.product_id,
        p.name AS product_name,
        p.brand,
        p.color,
        p.material,
        p.price,
        p.offer_percent,
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.mobile_no,
        u.address,
        -- ✅ Fetch main image (first image for each product)
        (SELECT image_path 
         FROM product_images 
         WHERE product_id = p.product_id 
         ORDER BY position ASC LIMIT 1) AS main_image
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    INNER JOIN products p ON o.product_id = p.product_id
    ORDER BY o.created_at DESC
";

$res = $conn->query($sql);

$orders = [];
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $orders
]);
?>