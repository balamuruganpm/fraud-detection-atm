<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include database connection
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Get recent transactions
$query = "SELECT t.*, u.full_name 
          FROM transactions t 
          JOIN users u ON t.user_id = u.user_id 
          ORDER BY t.transaction_date DESC 
          LIMIT 10";

$stmt = $db->prepare($query);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return response
echo json_encode($transactions);
?>
