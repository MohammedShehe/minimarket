<?php
require_once("cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();

$response = [];

// ✅ Step 1: Ensure admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// ✅ Step 2: Total Customers
$sql_users = "SELECT COUNT(*) AS total_users FROM users WHERE role = 'customer'";
$total_users = $conn->query($sql_users)->fetch_assoc()['total_users'] ?? 0;

// ✅ Step 3: Total Products
$sql_products = "SELECT COUNT(*) AS total_products FROM products";
$total_products = $conn->query($sql_products)->fetch_assoc()['total_products'] ?? 0;

// ✅ Step 4: Total Orders
$sql_orders = "SELECT COUNT(*) AS total_orders FROM orders";
$total_orders = $conn->query($sql_orders)->fetch_assoc()['total_orders'] ?? 0;

// ✅ Step 5: Total Income (Delivered orders only)
$sql_income = "SELECT SUM(total_price) AS total_income FROM orders WHERE status = 'Delivered'";
$total_income = $conn->query($sql_income)->fetch_assoc()['total_income'] ?? 0;

// ✅ Step 6: Monthly Income (for last 12 months)
$sql_monthly = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        SUM(total_price) AS income
    FROM orders
    WHERE status = 'Delivered'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
    LIMIT 12
";
$result_monthly = $conn->query($sql_monthly);

$monthly_income = [];
while ($row = $result_monthly->fetch_assoc()) {
    $monthly_income[] = [
        "month" => $row['month'],
        "income" => (float)$row['income']
    ];
}

// ✅ Step 7: Prepare response
$response = [
    "status" => "success",
    "data" => [
        "total_users" => (int)$total_users,
        "total_products" => (int)$total_products,
        "total_orders" => (int)$total_orders,
        "total_income" => (float)$total_income,
        "monthly_income" => $monthly_income
    ]
];

echo json_encode($response);
?>