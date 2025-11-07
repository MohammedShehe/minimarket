<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($search === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty search query']);
    exit;
}

// 🔍 Prepare SQL to match product name, brand, color, or material
$sql = "
    SELECT 
        p.product_id,
        p.name,
        p.brand,
        p.color,
        p.material,
        p.category,
        p.price,
        p.offer_percent,
        p.stock,
        (
            SELECT image_path 
            FROM product_images pi 
            WHERE pi.product_id = p.product_id 
            ORDER BY position ASC 
            LIMIT 1
        ) AS thumb
    FROM products p
    WHERE 
        (p.name LIKE ? OR p.brand LIKE ? OR p.color LIKE ? OR p.material LIKE ? OR p.category LIKE ?)
        AND p.stock > 0
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$searchTerm = '%' . $search . '%';
$stmt->bind_param("sssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();

$result = $stmt->get_result();
$products = [];

$base_url = "http://localhost/lawgate_mini_market/"; 

while ($row = $result->fetch_assoc()) {
    // Add full image path
    $row['thumb'] = $row['thumb'] ? $base_url . $row['thumb'] : null;
    $row['price'] = (float)$row['price'];
    $row['offer_percent'] = (int)$row['offer_percent'];
    $row['stock'] = (int)$row['stock'];
    
    // Calculate discounted price
    $row['discounted_price'] = $row['offer_percent'] > 0 
        ? round($row['price'] - ($row['price'] * $row['offer_percent'] / 100), 2)
        : $row['price'];
    
    $products[] = $row;
}

echo json_encode([
    'status' => 'success',
    'count' => count($products),
    'search_term' => $search,
    'products' => $products
]);

$stmt->close();
$conn->close();
?>