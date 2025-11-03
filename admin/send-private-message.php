<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (isset($_POST['user_id'], $_POST['subject'], $_POST['message'])) {
    $admin_id = $_SESSION['admin_id'];
    $user_id  = intval($_POST['user_id']);
    $subject  = trim($_POST['subject']);
    $message  = trim($_POST['message']);

    // Fetch recipient email
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    $user = $result->fetch_assoc();
    $email = $user['email'];

    // Save message to DB
    $insert = $conn->prepare("INSERT INTO messages (admin_id, user_id, subject, message, is_public) VALUES (?, ?, ?, ?, 0)");
    $insert->bind_param("iiss", $admin_id, $user_id, $subject, $message);
    $insert->execute();

    // Email sending simulation (replace with actual email service)
    // For demo purposes, we'll just return success
    $email_sent = true; // Set to false to simulate email failure
    
    if ($email_sent) {
        echo json_encode(["status" => "success", "message" => "Message sent successfully to $email"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Message saved but email sending failed"]);
    }
    
    $insert->close();
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
}
?>