<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (!isset($_POST['subject']) || !isset($_POST['message'])) {
    echo json_encode(["status" => "error", "message" => "Missing subject or message"]);
    exit;
}

$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// --- 1️⃣ Save message to database ---
$stmt = $conn->prepare("INSERT INTO messages (subject, message, is_public) VALUES (?, ?, 1)");
$stmt->bind_param("ss", $subject, $message);
$stmt->execute();

// --- 2️⃣ Fetch all customer emails ---
$result = $conn->query("SELECT email FROM users WHERE role='customer' AND email IS NOT NULL");
$emails = [];
while ($row = $result->fetch_assoc()) {
    $emails[] = $row['email'];
}

// Email sending simulation (replace with actual email service)
$email_sent = true; // Set to false to simulate email failure

if ($email_sent) {
    echo json_encode([
        "status" => "success",
        "message" => "Public message sent successfully to " . count($emails) . " customers."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Message saved but email sending failed"
    ]);
}

$stmt->close();
?>