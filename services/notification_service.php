<?php
class NotificationService {
  private $telegramBotToken;
  
  public function __construct($config = []) {
      // Initialize with configuration
      $this->telegramBotToken = $config['telegram_bot_token'] ?? null;
      
      // If no token provided in config, try to get from database
      if (!$this->telegramBotToken) {
          $this->telegramBotToken = $this->getTelegramBotTokenFromDB();
      }
  }
  
  // Get Telegram bot token from database
  private function getTelegramBotTokenFromDB() {
      try {
          require_once __DIR__ . '/../config/database.php';
          $database = new Database();
          $db = $database->getConnection();
          
          $query = "SELECT setting_value FROM system_settings WHERE setting_name = 'telegram_bot_token'";
          $stmt = $db->prepare($query);
          $stmt->execute();
          $result = $stmt->fetch(PDO::FETCH_ASSOC);
          
          return $result ? $result['setting_value'] : null;
      } catch (Exception $e) {
          error_log('Error getting Telegram bot token: ' . $e->getMessage());
          return null;
      }
  }
  
  // Send notification via Telegram
  public function sendTelegram($chatId, $message) {
      if (!$this->telegramBotToken) {
          error_log('Telegram notification failed: Bot token not configured');
          return [
              'success' => false,
              'message' => 'Telegram bot token not configured',
              'provider' => 'telegram'
          ];
      }
      
      // Always log the notification to console for development
      error_log('TELEGRAM NOTIFICATION TO ' . $chatId . ': ' . $message);
      
      // Check for MFA code in message and log it prominently
      if (preg_match('/verification code.*?is: \*(\d+)\*/', $message, $matches)) {
          $mfaCode = $matches[1];
          error_log('==================================================');
          error_log('MFA CODE: ' . $mfaCode);
          error_log('==================================================');
      }
      
      // For development, we can return success without actually sending
      // This is useful when Telegram API is not available or configured
      if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
          return [
              'success' => true,
              'message' => 'Notification logged to console (development mode)',
              'provider' => 'telegram'
          ];
      }
      
      try {
          $url = "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage";
          $data = [
              'chat_id' => $chatId,
              'text' => $message,
              'parse_mode' => 'Markdown'
          ];
          
          $options = [
              'http' => [
                  'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                  'method' => 'POST',
                  'content' => http_build_query($data),
                  'timeout' => 10
              ]
          ];
          
          $context = stream_context_create($options);
          $result = @file_get_contents($url, false, $context);
          
          if ($result === FALSE) {
              $error = error_get_last();
              error_log('Telegram API request failed: ' . ($error['message'] ?? 'Unknown error'));
              return [
                  'success' => false,
                  'message' => 'Failed to send Telegram message: ' . ($error['message'] ?? 'Unknown error'),
                  'provider' => 'telegram'
              ];
          }
          
          $response = json_decode($result, true);
          
          // Log the response
          error_log('TELEGRAM API RESPONSE: ' . json_encode($response));
          
          return [
              'success' => $response['ok'] ?? false,
              'message' => $response['description'] ?? 'Unknown error',
              'provider' => 'telegram'
          ];
      } catch (Exception $e) {
          error_log('Exception sending Telegram message: ' . $e->getMessage());
          return [
              'success' => false,
              'message' => 'Exception: ' . $e->getMessage(),
              'provider' => 'telegram'
          ];
      }
  }
  
  // Send notification using Telegram only
  public function sendNotification($user, $message, $subject = 'ATM Transaction Alert') {
      // Log the notification attempt
      error_log('SENDING NOTIFICATION: ' . $subject);
      error_log('MESSAGE: ' . $message);
      
      // Check if user has telegram_chat_id
      if (empty($user['telegram_chat_id'])) {
          error_log('No Telegram chat ID available for user - using console only');
          
          // For development, we'll consider this a success since we logged to console
          return [
              'success' => true,
              'message' => 'Notification logged to console only (no Telegram ID)',
              'results' => ['console' => ['success' => true]]
          ];
      }
      
      // Send via Telegram
      $result = $this->sendTelegram($user['telegram_chat_id'], $message);
      
      return [
          'success' => $result['success'],
          'results' => ['telegram' => $result],
          'message' => $result['success'] ? 'Notification sent successfully via Telegram' : 'Failed to send notification via Telegram'
      ];
  }
}
?>
