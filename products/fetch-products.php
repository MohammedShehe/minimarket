<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

$sql = "SELECT p.product_id, p.name, p.brand, p.price, p.offer_percent, p.stock, p.category,
               (SELECT image_path FROM product_images pi WHERE pi.product_id = p.product_id ORDER BY position LIMIT 1) AS thumb
        FROM products p
        WHERE p.stock > 0
        ORDER BY p.created_at DESC";

$res = $conn->query($sql);
$items = [];

$base_url = "http://localhost/phpproj/lawgate_mini_market/";

while ($row = $res->fetch_assoc()) {
    $row['price'] = (float)$row['price'];
    $row['offer_percent'] = (int)$row['offer_percent'];
    $row['stock'] = (int)$row['stock'];
    
    // Add full image path
    $row['thumb'] = $row['thumb'] ? $base_url . $row['thumb'] : null;
    
    // Calculate discounted price
    $row['discounted_price'] = $row['offer_percent'] > 0 
        ? round($row['price'] - ($row['price'] * $row['offer_percent'] / 100), 2)
        : $row['price'];
    
    $items[] = $row;
}

echo json_encode(['status'=>'success','products'=>$items]);
$conn->close();
?>