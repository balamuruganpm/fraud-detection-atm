<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if latitude and longitude are provided
if (!isset($_POST['latitude']) || !isset($_POST['longitude'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing location data']);
    exit;
}

// Include database connection
require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get user ID and location data
$userId = $_SESSION['user_id'];
$latitude = $_POST['latitude'];
$longitude = $_POST['longitude'];
$accuracy = $_POST['accuracy'] ?? null;
$deviceId = $_POST['device_id'] ?? $_SERVER['HTTP_USER_AGENT'];

// Insert location into database
$query = "INSERT INTO user_locations (user_id, latitude, longitude, accuracy, device_id) 
          VALUES (:user_id, :latitude, :longitude, :accuracy, :device_id)";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $userId);
$stmt->bindParam(':latitude', $latitude);
$stmt->bindParam(':longitude', $longitude);
$stmt->bindParam(':accuracy', $accuracy);
$stmt->bindParam(':device_id', $deviceId);

if ($stmt->execute()) {
    // Update user's last known location in Firebase (if configured)
    try {
        require_once '../config/firebase.php';
        $firebase = FirebaseConfig::getInstance();
        $database = $firebase->getDatabase();
        
        $database->getReference('users/' . $userId . '/location')->set([
            'latitude' => (float)$latitude,
            'longitude' => (float)$longitude,
            'accuracy' => $accuracy ? (float)$accuracy : null,
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        // Log error but continue
        error_log('Firebase error: ' . $e->getMessage());
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update location']);
}
?>
