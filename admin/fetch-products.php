<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

try {
    // Fetch products with prepared statement
    $sql = "SELECT 
                product_id,
                name,
                brand,
                color,
                material,
                description,
                category,
                price,
                offer_percent,
                stock,
                available_sizes,
                created_at
            FROM products 
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $product_id = $row['product_id'];
        
        // Fetch product images with ordering by position
        $img_stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY position ASC, image_id ASC");
        $img_stmt->bind_param("i", $product_id);
        
        if ($img_stmt->execute()) {
            $img_result = $img_stmt->get_result();
            $images = [];
            
            while ($img = $img_result->fetch_assoc()) {
                $images[] = $img['image_path'];
            }
            
            $row['images'] = $images;
            $img_stmt->close();
        } else {
            // If image fetch fails, set empty images array but still return the product
            $row['images'] = [];
        }

        // Calculate discounted price if offer exists
        if ($row['offer_percent'] > 0) {
            $discounted_price = $row['price'] * (1 - ($row['offer_percent'] / 100));
            $row['discounted_price'] = number_format($discounted_price, 2, '.', '');
        } else {
            $row['discounted_price'] = null;
        }

        // Format price for consistent output
        $row['price'] = number_format($row['price'], 2, '.', '');
        
        // Parse available_sizes if they exist
        if (!empty($row['available_sizes'])) {
            $row['available_sizes_array'] = explode(',', $row['available_sizes']);
        } else {
            $row['available_sizes_array'] = [];
        }

        $products[] = $row;
    }

    $stmt->close();
    
    echo json_encode([
        "status" => "success", 
        "count" => count($products),
        "products" => $products
    ]);

} catch (Exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to fetch products",
        "debug" => (ENVIRONMENT === 'development') ? $e->getMessage() : null
    ]);
}

$conn->close();
?>