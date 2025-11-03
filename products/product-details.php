<?php
require_once("C:/wamp64/www/phpProj/lawgate_mini_market/admin/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

if (!isset($_GET['product_id'])) {
    echo json_encode(['status'=>'error','message'=>'product_id required']);
    exit;
}

$product_id = (int)$_GET['product_id'];

// Product basic info
$sql = "SELECT product_id, name, brand, color, material, description, category, price, offer_percent, stock, available_sizes
        FROM products WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status'=>'error','message'=>'Product not found']);
    exit;
}

$product = $result->fetch_assoc();

// Calculate discounted price
$product['discounted_price'] = $product['offer_percent'] > 0 
    ? round($product['price'] - ($product['price'] * $product['offer_percent'] / 100), 2)
    : $product['price'];

// Images (up to 5)
$imgSql = "SELECT image_path, position FROM product_images WHERE product_id = ? ORDER BY position ASC LIMIT 5";
$stmt2 = $conn->prepare($imgSql);
$stmt2->bind_param("i", $product_id);
$stmt2->execute();
$imgRes = $stmt2->get_result();
$images = [];
$base_url = "http://localhost/phpproj/lawgate_mini_market/";
while ($img = $imgRes->fetch_assoc()) {
    $images[] = $base_url . $img['image_path'];
}

// IMPROVED: Average ratings with better NULL handling
$ratingSql = "
    SELECT 
        COALESCE(AVG(NULLIF(f.packaging_rating, 0)), 0) as avg_pack,
        COALESCE(AVG(NULLIF(f.delivery_rating, 0)), 0) as avg_del,
        COALESCE(AVG(NULLIF(f.quality_rating, 0)), 0) as avg_qual,
        COUNT(f.id) as total_reviews
    FROM order_feedback f
    INNER JOIN orders o ON f.order_id = o.id
    WHERE o.product_id = ? 
    AND f.packaging_rating > 0 
    AND f.delivery_rating > 0 
    AND f.quality_rating > 0";
    
$stmt3 = $conn->prepare($ratingSql);
$stmt3->bind_param("i", $product_id);
$stmt3->execute();
$ratingRes = $stmt3->get_result()->fetch_assoc();

// Process the results
$ratingRes['avg_pack'] = round((float)$ratingRes['avg_pack'], 1);
$ratingRes['avg_del'] = round((float)$ratingRes['avg_del'], 1);
$ratingRes['avg_qual'] = round((float)$ratingRes['avg_qual'], 1);
$ratingRes['total_reviews'] = (int)$ratingRes['total_reviews'];

// Calculate overall rating
if ($ratingRes['total_reviews'] > 0) {
    $ratingRes['overall'] = round(($ratingRes['avg_pack'] + $ratingRes['avg_del'] + $ratingRes['avg_qual']) / 3, 1);
} else {
    $ratingRes['overall'] = 0;
    $ratingRes['avg_pack'] = 0;
    $ratingRes['avg_del'] = 0;
    $ratingRes['avg_qual'] = 0;
}


// Size chart parsing
$sizes = [];
if ($product['available_sizes']) {
    $maybeJson = json_decode($product['available_sizes'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($maybeJson)) {
        $sizes = $maybeJson;
    } else {
        $sizes = array_map('trim', explode(',', $product['available_sizes']));
    }
}

$response = [
    'status' => 'success',
    'product' => $product,
    'images' => $images,
    'ratings' => $ratingRes,
    'sizes' => $sizes
];

echo json_encode($response);
$conn->close();
?>