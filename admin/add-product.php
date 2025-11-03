<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// Check fields
if (isset($_POST['name'], $_POST['price'], $_POST['category'])) {
    $name = trim($_POST['name']);
    $brand = $_POST['brand'] ?? null;
    $color = $_POST['color'] ?? null;
    $material = $_POST['material'] ?? null;
    $description = $_POST['description'] ?? null;
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $offer_percent = floatval($_POST['offer_percent'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $available_sizes = $_POST['available_sizes'] ?? null;

    // Validate price
    if ($price <= 0) {
        echo json_encode(["status" => "error", "message" => "Price must be greater than 0"]);
        exit;
    }

    // Validate and format available_sizes
    if (!empty($available_sizes)) {
        // Split by comma and clean up each size
        $sizes_array = explode(',', $available_sizes);
        $cleaned_sizes = [];
        
        foreach ($sizes_array as $size) {
            $trimmed_size = trim($size);
            // Allow alphanumeric characters, hyphens, and spaces (for sizes like "X-Large", "10.5", "2XL", etc.)
            if (preg_match('/^[a-zA-Z0-9\-\s\.]+$/', $trimmed_size)) {
                $cleaned_sizes[] = $trimmed_size;
            }
        }
        
        // Rejoin with comma separator
        $available_sizes = implode(',', $cleaned_sizes);
        
        // If after cleaning we have no valid sizes, set to null
        if (empty($available_sizes)) {
            $available_sizes = null;
        }
    }

    // Insert product
    $stmt = $conn->prepare("INSERT INTO products (name, brand, color, material, description, category, price, offer_percent, stock, available_sizes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssdiis", $name, $brand, $color, $material, $description, $category, $price, $offer_percent, $stock, $available_sizes);
    
    if ($stmt->execute()) {
        $product_id = $stmt->insert_id;

        // Handle up to 5 images
        if (!empty($_FILES['images'])) {
            $upload_dir = "../uploads/products/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $imageCount = 0;

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($imageCount >= 5) break;
                
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileType = mime_content_type($tmp_name);
                    
                    if (in_array($fileType, $allowedTypes)) {
                        $filename = time() . "_" . $imageCount . "_" . basename($_FILES['images']['name'][$key]);
                        $target = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $target)) {
                            $path = "uploads/products/" . $filename;
                            $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                            $img_stmt->bind_param("is", $product_id, $path);
                            $img_stmt->execute();
                            $img_stmt->close();
                            $imageCount++;
                        }
                    }
                }
            }
        }

        echo json_encode(["status" => "success", "message" => "Product added successfully", "product_id" => $product_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add product: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
}

$conn->close();
?>