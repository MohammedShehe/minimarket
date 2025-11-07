<?php
require_once("C:/wamp64/www/lawgate_mini_market/admin/cors-headers.php");
session_start();
header("Content-Type: application/json");
require_once("../config/db_connect.php");

$response = [];

if (isset($_POST['mobile_no']) && isset($_POST['otp_code'])) {
    $mobile_no = trim($_POST['mobile_no']);
    $otp_code  = trim($_POST['otp_code']);

    // 1️⃣ — Check if OTP exists and valid (not used, created within 10 mins)
    $sql = "SELECT o.otp_id, o.user_id, u.role, u.first_name, u.last_name 
            FROM otps o 
            JOIN users u ON o.user_id = u.user_id 
            WHERE o.mobile_no = ? AND o.otp_code = ? AND o.is_used = 0 
            AND o.created_at >= (NOW() - INTERVAL 10 MINUTE)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $mobile_no, $otp_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $data = $result->fetch_assoc();

        // 2️⃣ — Mark OTP as used
        $update_sql = "UPDATE otps SET is_used = 1 WHERE otp_id = ?";
        $stmt2 = $conn->prepare($update_sql);
        $stmt2->bind_param("i", $data['otp_id']);
        $stmt2->execute();

        // 3️⃣ — Create session for logged-in user
        $_SESSION['user_id'] = $data['user_id'];
        $_SESSION['role'] = $data['role'];
        $_SESSION['name'] = $data['first_name'] . " " . $data['last_name'];
        $_SESSION['mobile_no'] = $mobile_no;

        $response['status'] = 'success';
        $response['message'] = 'Login successful!';
        $response['role'] = $data['role'];
        $response['name'] = $data['first_name'] . " " . $data['last_name'];
        $response['user_id'] = $data['user_id'];
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Invalid or expired OTP.';
    }
    
    $stmt->close();
    if (isset($stmt2)) $stmt2->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Mobile number and OTP required.';
}

$conn->close();
echo json_encode($response);
?>