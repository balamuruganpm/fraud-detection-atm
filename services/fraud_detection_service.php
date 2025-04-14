<?php
require_once 'notification_service.php';
require_once __DIR__ . '/../utils/location_utils.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase.php';

class FraudDetectionService {
    private $db;
    private $locationUtils;
    private $notificationService;
    private $firebase;
    private $baseUrl;
    
    public function __construct($config = []) {
        $database = new Database();
        $this->db = $database->getConnection();
        
        // Initialize with empty API key for now (will use Haversine formula)
        $this->locationUtils = new LocationUtils();
        
        // Initialize notification service
        $this->notificationService = new NotificationService($config);
        
        // Initialize Firebase
        $this->firebase = FirebaseConfig::getInstance();
        
        // Base URL for response links
        $this->baseUrl = $config['base_url'] ?? 'http://localhost/fraud-detection';
    }
    
    // Process a new transaction and check for potential fraud
    public function processTransaction($userId, $accountId, $cardId, $atmId, $amount, $transactionType, $locationData = null) {
        // Log transaction attempt
        error_log("TRANSACTION ATTEMPT: User ID: $userId, Amount: $amount, Type: $transactionType");
        
        // Check if card exists and is active
        $card = $this->getCardInfo($cardId);
        if (!$card) {
            error_log("TRANSACTION FAILED: Card not found or inactive");
            return [
                'success' => false,
                'message' => 'Card not found or inactive'
            ];
        }
        
        // Check if account exists and is active
        $account = $this->getAccountInfo($accountId);
        if (!$account) {
            error_log("TRANSACTION FAILED: Account not found or inactive");
            return [
                'success' => false,
                'message' => 'Account not found or inactive'
            ];
        }
        
        // Check if user has sufficient balance for withdrawal
        if ($transactionType === 'WITHDRAWAL' && $account['balance'] < $amount) {
            error_log("TRANSACTION FAILED: Insufficient funds");
            return [
                'success' => false,
                'message' => 'Insufficient funds'
            ];
        }
        
        // Check if transaction exceeds daily limit
        if ($transactionType === 'WITHDRAWAL' && $amount > $card['remaining_limit']) {
            error_log("TRANSACTION FAILED: Exceeds daily limit");
            return [
                'success' => false,
                'message' => 'Transaction exceeds daily limit',
                'remaining_limit' => $card['remaining_limit']
            ];
        }
        
        // Extract location data if provided
        $latitude = null;
        $longitude = null;
        $countryCode = null;
        $deviceId = null;
        $ipAddress = null;
        
        if ($locationData) {
            $latitude = $locationData['latitude'] ?? null;
            $longitude = $locationData['longitude'] ?? null;
            $countryCode = $locationData['country_code'] ?? null;
            $deviceId = $locationData['device_id'] ?? null;
            $ipAddress = $locationData['ip_address'] ?? null;
            
            error_log("LOCATION DATA: Lat: $latitude, Lng: $longitude, Country: $countryCode");
        } else if ($atmId) {
            // If no location data but ATM ID is provided, get ATM location
            $atmInfo = $this->getAtmInfo($atmId);
            if ($atmInfo) {
                $latitude = $atmInfo['latitude'];
                $longitude = $atmInfo['longitude'];
                error_log("USING ATM LOCATION: Lat: $latitude, Lng: $longitude");
            }
        }
        
        // Create a new transaction record
        $transactionId = $this->createTransaction(
            $userId, 
            $accountId, 
            $cardId, 
            $atmId, 
            $amount, 
            $transactionType,
            $latitude,
            $longitude,
            $countryCode,
            $deviceId,
            $ipAddress
        );
        
        if (!$transactionId) {
            error_log("TRANSACTION FAILED: Could not create transaction record");
            return [
                'success' => false,
                'message' => 'Failed to create transaction record'
            ];
        }
        
        error_log("TRANSACTION CREATED: ID: $transactionId");
        
        // Check for potential fraud indicators
        $fraudDetected = false;
        $alertType = null;
        $alertMessage = null;
        
        // 1. Check country restrictions if country code is provided
        if ($countryCode && !$this->isCountryAllowed($cardId, $countryCode)) {
            $fraudDetected = true;
            $alertType = 'COUNTRY_RESTRICTION';
            $alertMessage = "Transaction attempted in a restricted country: {$countryCode}";
            error_log("FRAUD DETECTED: Country restriction - $countryCode");
        }
        
        // 2. Check if the transaction location is within the user's geo-fenced regions
        if (!$fraudDetected && $latitude && $longitude) {
            $isLocationValid = $this->validateLocation($userId, $latitude, $longitude, $atmId);
            
            if (!$isLocationValid) {
                $fraudDetected = true;
                $alertType = 'LOCATION_MISMATCH';
                $alertMessage = 'Unusual location detected for your ATM transaction. Please verify.';
                error_log("FRAUD DETECTED: Location mismatch - Lat: $latitude, Lng: $longitude");
            }
        }
        
        // 3. Check for unusual transaction amount
        if (!$fraudDetected && $transactionType !== 'BALANCE_CHECK' && $this->isUnusualAmount($userId, $amount, $transactionType)) {
            $fraudDetected = true;
            $alertType = 'UNUSUAL_AMOUNT';
            $alertMessage = 'Unusual transaction amount detected. Please verify this transaction.';
            error_log("FRAUD DETECTED: Unusual amount - $amount");
        }
        
        if ($fraudDetected) {
            // Flag transaction as potentially fraudulent
            $this->updateTransactionStatus($transactionId, 'FLAGGED');
            
            // Generate a unique response URL
            $responseToken = bin2hex(random_bytes(16));
            $responseUrl = "{$this->baseUrl}/response.php?token={$responseToken}&transaction={$transactionId}";
            
            // Create an alert
            $alertId = $this->createAlert($transactionId, $alertType, $alertMessage, $responseUrl);
            
            // Send notification to user
            $userInfo = $this->getUserInfo($userId);
            $atmInfo = $atmId ? $this->getAtmInfo($atmId) : null;
            
            $message = "🚨 ALERT: {$transactionType} of \${$amount} ";
            if ($atmInfo) {
                $message .= "at {$atmInfo['atm_name']} ({$atmInfo['address']}) ";
            }
            $message .= "detected with potential security concern.\n\n";
            $message .= "Transaction ID: {$transactionId}\n";
            $message .= "Date/Time: " . date('Y-m-d H:i:s') . "\n\n";
            $message .= "To approve this transaction, click here: {$responseUrl}&response=approve\n";
            $message .= "To deny this transaction, click here: {$responseUrl}&response=deny\n\n";
            $message .= "If you did not initiate this transaction, please deny it immediately.";
            
            // Log the fraud alert message
            error_log("FRAUD ALERT MESSAGE: $message");
            
            $notificationResult = $this->notificationService->sendNotification($userInfo, $message, 'URGENT: Unusual Transaction Detected');
            
            // Store transaction data in Firebase for real-time monitoring
            $this->storeTransactionInFirebase($transactionId, [
                'user_id' => $userId,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'status' => 'FLAGGED',
                'alert_type' => $alertType,
                'timestamp' => time(),
                'location' => $latitude && $longitude ? [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ] : null
            ]);
            
            // Determine which notification methods were used
            $notificationMethod = 'TELEGRAM'; // Default to Telegram only
            
            // Update alert status and notification method
            $this->updateAlertDetails($alertId, $notificationResult['success'] ? 'DELIVERED' : 'FAILED', $notificationMethod);
            
            error_log("FRAUD ALERT SENT: Alert ID: $alertId, Transaction ID: $transactionId");
            
            return [
                'success' => true,
                'fraud_detected' => true,
                'transaction_id' => $transactionId,
                'alert_id' => $alertId,
                'message' => 'Potential fraud detected. User has been notified.',
                'notification_result' => $notificationResult
            ];
        }
        
        // No fraud detected, approve the transaction
        $this->updateTransactionStatus($transactionId, 'APPROVED');
        
        // Update account balance
        if ($transactionType === 'WITHDRAWAL') {
            $this->updateAccountBalance($accountId, -$amount);
            $this->updateCardRemainingLimit($cardId, -$amount);
        } elseif ($transactionType === 'DEPOSIT') {
            $this->updateAccountBalance($accountId, $amount);
        }
        // For BALANCE_CHECK, we don't update any balances
        
        // Store transaction data in Firebase for real-time monitoring
        $this->storeTransactionInFirebase($transactionId, [
            'user_id' => $userId,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'status' => 'APPROVED',
            'timestamp' => time(),
            'location' => $latitude && $longitude ? [
                'latitude' => $latitude,
                'longitude' => $longitude
            ] : null
        ]);
        
        error_log("TRANSACTION APPROVED: ID: $transactionId, Amount: $amount, Type: $transactionType");
        
        return [
            'success' => true,
            'fraud_detected' => false,
            'transaction_id' => $transactionId,
            'message' => 'Transaction approved.',
            'new_balance' => $this->getAccountBalance($accountId),
            'remaining_limit' => $this->getCardRemainingLimit($cardId)
        ];
    }
    
    // Store transaction data in Firebase
    private function storeTransactionInFirebase($transactionId, $data) {
        try {
            $database = $this->firebase->getDatabase();
            $database->getReference('transactions/' . $transactionId)->set($data);
            
            // Also update user's transaction history
            $database->getReference('users/' . $data['user_id'] . '/transactions/' . $transactionId)->set($data);
            
            return true;
        } catch (Exception $e) {
            // Log error but continue processing
            error_log('Firebase error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Check if country is allowed for this card
    private function isCountryAllowed($cardId, $countryCode) {
        $query = "SELECT is_allowed FROM country_restrictions 
                  WHERE card_id = :card_id AND country_code = :country_code";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':card_id', $cardId);
        $stmt->bindParam(':country_code', $countryCode);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no restriction is set for this country, default to allowed
        if (!$result) {
            return true;
        }
        
        return (bool)$result['is_allowed'];
    }
    
    // Check if amount is unusual based on user's transaction history
    private function isUnusualAmount($userId, $amount, $transactionType) {
        // Get user's average transaction amount for this type
        $query = "SELECT AVG(amount) as avg_amount, MAX(amount) as max_amount 
                  FROM transactions 
                  WHERE user_id = :user_id 
                  AND transaction_type = :transaction_type 
                  AND transaction_status = 'APPROVED'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':transaction_type', $transactionType);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no transaction history, return false
        if (!$result['avg_amount']) {
            return false;
        }
        
        $avgAmount = (float)$result['avg_amount'];
        $maxAmount = (float)$result['max_amount'];
        
        // If amount is more than 3 times the average or 1.5 times the max, consider it unusual
        return ($amount > ($avgAmount * 3) || ($maxAmount > 0 && $amount > ($maxAmount * 1.5)));
    }
    
    // Validate if the location is within any of the user's geo-fenced regions
    private function validateLocation($userId, $latitude, $longitude, $atmId = null) {
        // If ATM ID is provided, get ATM location
        if ($atmId) {
            $atmInfo = $this->getAtmInfo($atmId);
            
            if ($atmInfo) {
                $latitude = $atmInfo['latitude'];
                $longitude = $atmInfo['longitude'];
            }
        }
        
        // Get user's geo-fenced regions
        $query = "SELECT * FROM geo_fenced_regions WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If user has no defined regions, consider it valid (first-time use)
        if (empty($regions)) {
            return true;
        }
        
        // Check if location is within any of the user's regions
        foreach ($regions as $region) {
            $isWithin = $this->locationUtils->isWithinGeoFence(
                $latitude,
                $longitude,
                $region['center_latitude'],
                $region['center_longitude'],
                $region['radius_km']
            );
            
            if ($isWithin) {
                return true;
            }
        }
        
        // Location is not within any allowed region
        return false;
    }
    
    // Create a new transaction record
    private function createTransaction($userId, $accountId, $cardId, $atmId, $amount, $transactionType, $latitude = null, $longitude = null, $countryCode = null, $deviceId = null, $ipAddress = null) {
        $query = "INSERT INTO transactions 
                  (user_id, account_id, card_id, atm_id, amount, transaction_type, 
                   location_latitude, location_longitude, country_code, device_id, ip_address) 
                  VALUES 
                  (:user_id, :account_id, :card_id, :atm_id, :amount, :transaction_type, 
                   :latitude, :longitude, :country_code, :device_id, :ip_address)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':account_id', $accountId);
        $stmt->bindParam(':card_id', $cardId);
        $stmt->bindParam(':atm_id', $atmId);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':transaction_type', $transactionType);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':country_code', $countryCode);
        $stmt->bindParam(':device_id', $deviceId);
        $stmt->bindParam(':ip_address', $ipAddress);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // Update transaction status
    private function updateTransactionStatus($transactionId, $status) {
        $query = "UPDATE transactions SET transaction_status = :status 
                  WHERE transaction_id = :transaction_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':transaction_id', $transactionId);
        
        return $stmt->execute();
    }
    
    // Create an alert record
    private function createAlert($transactionId, $alertType, $alertMessage, $responseUrl = null) {
        $query = "INSERT INTO alerts (transaction_id, alert_type, alert_message, alert_status, notification_method, response_url) 
                  VALUES (:transaction_id, :alert_type, :alert_message, 'SENT', 'TELEGRAM', :response_url)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->bindParam(':alert_type', $alertType);
        $stmt->bindParam(':alert_message', $alertMessage);
        $stmt->bindParam(':response_url', $responseUrl);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    // Update alert status and notification method
    private function updateAlertDetails($alertId, $status, $notificationMethod) {
        $query = "UPDATE alerts SET alert_status = :status, notification_method = :method 
                  WHERE alert_id = :alert_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':method', $notificationMethod);
        $stmt->bindParam(':alert_id', $alertId);
        
        return $stmt->execute();
    }
    
    // Get user information
    private function getUserInfo($userId) {
        $query = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get ATM information
    private function getAtmInfo($atmId) {
        $query = "SELECT * FROM atm_locations WHERE atm_id = :atm_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':atm_id', $atmId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get card information
    private function getCardInfo($cardId) {
        $query = "SELECT * FROM atm_cards WHERE card_id = :card_id AND card_status = 'ACTIVE'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':card_id', $cardId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get account information
    private function getAccountInfo($accountId) {
        $query = "SELECT * FROM bank_accounts WHERE account_id = :account_id AND status = 'ACTIVE'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':account_id', $accountId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update account balance
    private function updateAccountBalance($accountId, $amount) {
        $query = "UPDATE bank_accounts SET balance = balance + :amount 
                  WHERE account_id = :account_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':account_id', $accountId);
        
        return $stmt->execute();
    }
    
    // Get account balance
    private function getAccountBalance($accountId) {
        $query = "SELECT balance FROM bank_accounts WHERE account_id = :account_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':account_id', $accountId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['balance'] : 0;
    }
    
    // Update card remaining limit
    private function updateCardRemainingLimit($cardId, $amount) {
        // Check if limit needs to be reset (new day)
        $query = "SELECT daily_limit, remaining_limit, limit_reset_date FROM atm_cards 
                  WHERE card_id = :card_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':card_id', $cardId);
        $stmt->execute();
        
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$card) {
            return false;
        }
        
        $currentDate = date('Y-m-d');
        $resetDate = $card['limit_reset_date'];
        
        // If it's a new day, reset the limit
        if ($currentDate != $resetDate) {
            $query = "UPDATE atm_cards SET remaining_limit = daily_limit, limit_reset_date = :current_date 
                      WHERE card_id = :card_id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':current_date', $currentDate);
            $stmt->bindParam(':card_id', $cardId);
            $stmt->execute();
            
            // Set remaining limit to daily limit
            $remainingLimit = $card['daily_limit'];
        } else {
            $remainingLimit = $card['remaining_limit'];
        }
        
        // Update remaining limit
        $newLimit = $remainingLimit + $amount;
        
        $query = "UPDATE atm_cards SET remaining_limit = :remaining_limit 
                  WHERE card_id = :card_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':remaining_limit', $newLimit);
        $stmt->bindParam(':card_id', $cardId);
        
        return $stmt->execute();
    }
    
    // Get card remaining limit
    private function getCardRemainingLimit($cardId) {
        $query = "SELECT remaining_limit FROM atm_cards WHERE card_id = :card_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':card_id', $cardId);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['remaining_limit'] : 0;
    }
    
    // Process user response to an alert (approve/deny)
    public function processUserResponse($transactionId, $response) {
        // Get the alert for this transaction
        $query = "SELECT * FROM alerts WHERE transaction_id = :transaction_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->execute();
        
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$alert) {
            return [
                'success' => false,
                'message' => 'Alert not found for this transaction'
            ];
        }
        
        // Update alert with user response
        $userResponseValue = strtolower($response) === 'approve' ? 'APPROVED' : 'DENIED';
        
        $query = "UPDATE alerts SET user_response = :user_response 
                  WHERE alert_id = :alert_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_response', $userResponseValue);
        $stmt->bindParam(':alert_id', $alert['alert_id']);
        $stmt->execute();
        
        // Get transaction details
        $query = "SELECT t.*, u.user_id, u.full_name, u.email, u.telegram_chat_id, 
                         a.account_id, a.balance, c.card_id, c.remaining_limit,
                         atm.atm_name, atm.address 
                  FROM transactions t 
                  JOIN users u ON t.user_id = u.user_id 
                  JOIN bank_accounts a ON t.account_id = a.account_id 
                  LEFT JOIN atm_cards c ON t.card_id = c.card_id 
                  LEFT JOIN atm_locations atm ON t.atm_id = atm.atm_id 
                  WHERE t.transaction_id = :transaction_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->execute();
        
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];
        }
        
        // Update transaction status based on user response
        $transactionStatus = $userResponseValue === 'APPROVED' ? 'APPROVED' : 'DENIED';
        $this->updateTransactionStatus($transactionId, $transactionStatus);
        
        // If approved, update account balance and card limit
        if ($transactionStatus === 'APPROVED') {
            if ($transaction['transaction_type'] === 'WITHDRAWAL') {
                $this->updateAccountBalance($transaction['account_id'], -$transaction['amount']);
                if ($transaction['card_id']) {
                    $this->updateCardRemainingLimit($transaction['card_id'], -$transaction['amount']);
                }
            } elseif ($transaction['transaction_type'] === 'DEPOSIT') {
                $this->updateAccountBalance($transaction['account_id'], $transaction['amount']);
            }
        }
        
        // Update Firebase with the transaction status
        $this->storeTransactionInFirebase($transactionId, [
            'user_id' => $transaction['user_id'],
            'transaction_type' => $transaction['transaction_type'],
            'amount' => $transaction['amount'],
            'status' => $transactionStatus,
            'timestamp' => time(),
            'user_response' => $userResponseValue
        ]);
        
        // Send confirmation to user
        $message = "✅ Your transaction #{$transactionId} has been {$transactionStatus}.\n\n";
        $message .= "Transaction Details:\n";
        $message .= "- Amount: \${$transaction['amount']}\n";
        $message .= "- Type: {$transaction['transaction_type']}\n";
        
        if ($transaction['atm_name']) {
            $message .= "- ATM: {$transaction['atm_name']}\n";
            $message .= "- Location: {$transaction['address']}\n";
        }
        
        $message .= "- Date/Time: " . date('Y-m-d H:i:s', strtotime($transaction['transaction_date'])) . "\n\n";
        
        if ($transactionStatus === 'APPROVED') {
            $message .= "Thank you for confirming this transaction.";
        } else {
            $message .= "The transaction has been blocked. If you did not initiate this transaction, please contact customer support immediately.";
        }
        
        $this->notificationService->sendNotification($transaction, $message, "Transaction {$transactionStatus}");
        
        return [
            'success' => true,
            'transaction_status' => $transactionStatus,
            'message' => "Transaction has been {$transactionStatus} based on user response."
        ];
    }
}
?>
