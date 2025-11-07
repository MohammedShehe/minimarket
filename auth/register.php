<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");

$response = [];

// 1️⃣ — Check required fields
if (
    isset($_POST['first_name'], $_POST['last_name'], $_POST['mobile_no'], $_POST['address'], $_POST['gender'])
) {
    // 2️⃣ — Get and sanitize input
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $mobile_no  = trim($_POST['mobile_no']);
    $email      = isset($_POST['email']) && $_POST['email'] !== '' ? trim($_POST['email']) : NULL;
    $address    = trim($_POST['address']);
    $gender     = strtolower(trim($_POST['gender']));

    // 3️⃣ — Validate mobile number (must be 10 digits)
    if (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid mobile number. Must be 10 digits.'
        ]);
        exit;
    }

    // 4️⃣ — Validate email format if provided
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email format.'
        ]);
        exit;
    }

    // 5️⃣ — Validate gender
    $valid_genders = ['male', 'female', 'other'];
    if (!in_array($gender, $valid_genders)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid gender value.'
        ]);
        exit;
    }

    // 6️⃣ — Check if mobile number already exists
    $check_sql = "SELECT user_id FROM users WHERE mobile_no = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $mobile_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Mobile number already registered.'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // 7️⃣ — Generate a temporary password
    $temp_password = substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 6);
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

    // 8️⃣ — Insert user record
    $insert_sql = "INSERT INTO users (first_name, last_name, mobile_no, email, password, address, gender, role)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sssssss", $first_name, $last_name, $mobile_no, $email, $hashed_password, $address, $gender);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;

        http_response_code(201);
        $response = [
            'status' => 'success',
            'message' => 'Registration successful!',
            'user_id' => $user_id,
            'mobile_no' => $mobile_no,
            'temp_password' => $temp_password // Remove in production
        ];
    } else {
        http_response_code(500);
        $response = [
            'status' => 'error',
            'message' => 'Failed to register user. Please try again.'
        ];
    }

    $stmt->close();
} else {
    http_response_code(400);
    $response = [
        'status' => 'error',
        'message' => 'Missing required fields.'
    ];
}

$conn->close();
echo json_encode($response);
?>