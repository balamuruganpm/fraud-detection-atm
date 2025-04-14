<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include database connection
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Get flagged transactions
$query = "SELECT t.*, a.atm_name, a.address 
          FROM transactions t 
          JOIN atm_locations a ON t.atm_id = a.atm_id 
          WHERE t.transaction_status = 'FLAGGED' 
          ORDER BY t.transaction_date DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return response
echo json_encode($transactions);
?>
