<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if (isset($_POST['product_id'])) {
    $id = intval($_POST['product_id']);
    $name = trim($_POST['name'] ?? '');
    $brand = $_POST['brand'] ?? '';
    $color = $_POST['color'] ?? '';
    $material = $_POST['material'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'unisex';
    $price = floatval($_POST['price'] ?? 0);
    $offer_percent = floatval($_POST['offer_percent'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $available_sizes = $_POST['available_sizes'] ?? null; // ADDED: This was missing

    // Validate price
    if ($price <= 0) {
        echo json_encode(["status" => "error", "message" => "Price must be greater than 0"]);
        exit;
    }

    // Validate and format available_sizes (same as add-product.php)
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

    // FIXED: Updated SQL to include available_sizes and removed size_chart
    $stmt = $conn->prepare("UPDATE products SET name=?, brand=?, color=?, material=?, description=?, category=?, price=?, offer_percent=?, stock=?, available_sizes=? WHERE product_id=?");
    $stmt->bind_param("ssssssdiisi", $name, $brand, $color, $material, $description, $category, $price, $offer_percent, $stock, $available_sizes, $id);
    
    if ($stmt->execute()) {
        // If new images uploaded, replace them
        if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
            // Delete old images and files
            $old_img_stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $old_img_stmt->bind_param("i", $id);
            $old_img_stmt->execute();
            $old_img_result = $old_img_stmt->get_result();
            
            while ($old_img = $old_img_result->fetch_assoc()) {
                $old_image_path = "../" . $old_img['image_path'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $old_img_stmt->close();
            
            // Delete old images from database
            $delete_stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Upload new images
            $upload_dir = "../uploads/products/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $imageCount = 0;

            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($imageCount >= 5) break;
                
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK && !empty($tmp_name)) {
                    $fileType = mime_content_type($tmp_name);
                    
                    if (in_array($fileType, $allowedTypes)) {
                        $filename = time() . "_" . $imageCount . "_" . basename($_FILES['images']['name'][$key]);
                        $target = $upload_dir . $filename;
                        
                        if (move_uploaded_file($tmp_name, $target)) {
                            $path = "uploads/products/" . $filename;
                            $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                            $img_stmt->bind_param("is", $id, $path);
                            $img_stmt->execute();
                            $img_stmt->close();
                            $imageCount++;
                        }
                    }
                }
            }
        }

        echo json_encode(["status" => "success", "message" => "Product updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update product: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing product_id"]);
}

$conn->close();
?>