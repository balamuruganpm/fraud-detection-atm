<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include database connection
require_once '../../config/database.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Get recent alerts
$query = "SELECT * FROM alerts ORDER BY created_at DESC LIMIT 10";

$stmt = $db->prepare($query);
$stmt->execute();
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return response
echo json_encode($alerts);
?>
