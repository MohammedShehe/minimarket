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

if (
    isset($_POST['first_name']) &&
    isset($_POST['last_name']) &&
    isset($_POST['mobile_no']) &&
    isset($_POST['password'])
) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $mobile_no  = trim($_POST['mobile_no']);
    $email      = $_POST['email'] ?? null;
    $address    = $_POST['address'] ?? null;
    $gender     = $_POST['gender'] ?? null;
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role       = 'customer';

    // Validate mobile number
    if (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $response = ["status" => "error", "message" => "Invalid mobile number"];
        echo json_encode($response);
        exit;
    }

    // Prevent duplicates
    $check = $conn->prepare("SELECT user_id FROM users WHERE mobile_no = ?");
    $check->bind_param("s", $mobile_no);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $response = ["status" => "error", "message" => "Mobile number already exists"];
    } else {
        $sql = "INSERT INTO users (first_name, last_name, mobile_no, email, password, address, gender, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $first_name, $last_name, $mobile_no, $email, $password, $address, $gender, $role);

        if ($stmt->execute()) {
            $response = ["status" => "success", "message" => "Customer added successfully"];
        } else {
            $response = ["status" => "error", "message" => "Failed to add customer"];
        }
        $stmt->close();
    }
    $check->close();
} else {
    $response = ["status" => "error", "message" => "Missing required fields"];
}

echo json_encode($response);
?>