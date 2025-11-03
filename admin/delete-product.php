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
    
    // First get product images to delete files
    $img_stmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $img_stmt->bind_param("i", $id);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    
    // Delete image files
    while ($img = $img_result->fetch_assoc()) {
        $image_path = "../" . $img['image_path'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    $img_stmt->close();
    
    // Delete product images from database
    $conn->query("DELETE FROM product_images WHERE product_id = $id");
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Product deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete product"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing product_id"]);
}
?>