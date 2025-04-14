<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include database connection
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Get counts
$response = [
    'users' => 0,
    'transactions' => 0,
    'alerts' => 0
];

// Count users
$query = "SELECT COUNT(*) as count FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$response['users'] = (int)$row['count'];

// Count transactions
$query = "SELECT COUNT(*) as count FROM transactions";
$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$response['transactions'] = (int)$row['count'];

// Count alerts
$query = "SELECT COUNT(*) as count FROM alerts";
$stmt = $db->prepare($query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$response['alerts'] = (int)$row['count'];

// Return response
echo json_encode($response);
?>
