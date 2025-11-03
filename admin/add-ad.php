<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

$response = [];

if (isset($_FILES['image']) && isset($_POST['link'])) {
    $link = trim($_POST['link']);

    // File upload setup
    $targetDir = "../uploads/ads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $fileType = mime_content_type($_FILES['image']['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        $response = ["status" => "error", "message" => "Invalid file type. Only JPG, PNG, GIF allowed."];
        echo json_encode($response);
        exit;
    }

    $fileName = time() . "_" . basename($_FILES['image']['name']);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        $imagePath = "uploads/ads/" . $fileName;

        $stmt = $conn->prepare("INSERT INTO ads (image_path, link) VALUES (?, ?)");
        $stmt->bind_param("ss", $imagePath, $link);
        
        if ($stmt->execute()) {
            $response = ["status" => "success", "message" => "Ad posted successfully"];
        } else {
            $response = ["status" => "error", "message" => "Database error: " . $stmt->error];
        }
        $stmt->close();
    } else {
        $response = ["status" => "error", "message" => "Failed to upload image"];
    }
} else {
    $response = ["status" => "error", "message" => "Missing image or link"];
}

echo json_encode($response);
?>