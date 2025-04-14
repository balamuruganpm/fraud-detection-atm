<?php
// require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseConfig {
    private static $instance = null;
    private $firebase;
    private $database;
    private $auth;
    private $messaging;
    
    private function __construct() {
        // Get Firebase configuration from database
        require_once __DIR__ . '/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $settings = [];
        $query = "SELECT setting_name, setting_value FROM system_settings WHERE setting_name LIKE 'firebase_%'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        
        // Create Firebase configuration array
        $config = [
            'apiKey' => $settings['firebase_api_key'] ?? '',
            'authDomain' => $settings['firebase_auth_domain'] ?? '',
            'projectId' => $settings['firebase_project_id'] ?? '',
            'storageBucket' => $settings['firebase_storage_bucket'] ?? '',
            'messagingSenderId' => $settings['firebase_messaging_sender_id'] ?? '',
            'appId' => $settings['firebase_app_id'] ?? '',
            'measurementId' => $settings['firebase_measurement_id'] ?? '',
            'databaseURL' => "https://{$settings['firebase_project_id']}.firebaseio.com",
        ];
        
        // Initialize Firebase
        $factory = (new Factory)
            ->withProjectId($config['projectId'])
            ->withDatabaseUri($config['databaseURL']);
        
        // Check if service account file exists
        $serviceAccountPath = __DIR__ . '/firebase-service-account.json';
        if (file_exists($serviceAccountPath)) {
            $factory = $factory->withServiceAccount($serviceAccountPath);
        }
        
        $this->firebase = $factory->create();
        $this->database = $this->firebase->getDatabase();
        $this->auth = $this->firebase->getAuth();
        $this->messaging = $this->firebase->getMessaging();
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new FirebaseConfig();
        }
        return self::$instance;
    }
    
    public function getDatabase() {
        return $this->database;
    }
    
    public function getAuth() {
        return $this->auth;
    }
    
    public function getMessaging() {
        return $this->messaging;
    }
}
?>
