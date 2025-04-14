<?php
class Logger {
    private static $instance = null;
    private $logFile;
    
    private function __construct() {
        $this->logFile = __DIR__ . '/../logs/app.log';
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Log to PHP error log
        error_log($formattedMessage);
        
        // Also log to file if possible
        try {
            file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
        } catch (Exception $e) {
            // Silently fail if we can't write to the log file
        }
    }
    
    public function info($message) {
        $this->log($message, 'INFO');
    }
    
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
    
    public function error($message) {
        $this->log($message, 'ERROR');
    }
    
    public function debug($message) {
        $this->log($message, 'DEBUG');
    }
    
    public function notification($message) {
        $this->log($message, 'NOTIFICATION');
    }
    
    public function mfa($code, $user = null) {
        $userInfo = $user ? " for user {$user}" : '';
        $this->log("MFA CODE{$userInfo}: $code", 'MFA');
        $this->log("=============================================", 'MFA');
    }
}
?>
