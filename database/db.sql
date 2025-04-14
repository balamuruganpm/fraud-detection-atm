-- Create database
CREATE DATABASE IF NOT EXISTS atm_fraud_detection;
USE atm_fraud_detection;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telegram_chat_id VARCHAR(50) NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Will store hashed passwords
    firebase_uid VARCHAR(128) NULL, -- Firebase User ID
    is_admin TINYINT(1) DEFAULT 0,
    mfa_enabled TINYINT(1) DEFAULT 0, -- Multi-factor authentication
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bank accounts table
CREATE TABLE IF NOT EXISTS bank_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_number VARCHAR(20) NOT NULL UNIQUE,
    account_type ENUM('SAVINGS', 'CHECKING', 'CREDIT') NOT NULL,
    balance DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('ACTIVE', 'INACTIVE', 'BLOCKED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ATM Cards table
CREATE TABLE IF NOT EXISTS atm_cards (
    card_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_id INT NOT NULL,
    card_number VARCHAR(16) NOT NULL UNIQUE,
    card_holder VARCHAR(100) NOT NULL,
    pin VARCHAR(255) NOT NULL, -- Will store hashed PIN
    expiry_month INT NOT NULL,
    expiry_year INT NOT NULL,
    cvv VARCHAR(3) NOT NULL,
    card_status ENUM('ACTIVE', 'BLOCKED', 'EXPIRED') DEFAULT 'ACTIVE',
    daily_limit DECIMAL(10, 2) DEFAULT 2000.00,
    remaining_limit DECIMAL(10, 2) DEFAULT 2000.00, -- Daily remaining limit
    limit_reset_date DATE DEFAULT (CURRENT_DATE), -- When the limit resets
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (account_id) REFERENCES bank_accounts(account_id)
);

-- Country restrictions table
CREATE TABLE IF NOT EXISTS country_restrictions (
    restriction_id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    country_code VARCHAR(2) NOT NULL,
    country_name VARCHAR(100) NOT NULL,
    is_allowed TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES atm_cards(card_id)
);

-- Geo-fenced regions table
CREATE TABLE IF NOT EXISTS geo_fenced_regions (
    region_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    region_name VARCHAR(100) NOT NULL,
    center_latitude DECIMAL(10, 8) NOT NULL,
    center_longitude DECIMAL(11, 8) NOT NULL,
    radius_km DECIMAL(10, 2) NOT NULL, -- Radius in kilometers
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ATM locations table
CREATE TABLE IF NOT EXISTS atm_locations (
    atm_id INT AUTO_INCREMENT PRIMARY KEY,
    atm_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address VARCHAR(255) NOT NULL,
    status ENUM('ACTIVE', 'MAINTENANCE', 'OFFLINE') DEFAULT 'ACTIVE'
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_id INT NOT NULL,
    card_id INT NULL,
    atm_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_type ENUM('WITHDRAWAL', 'DEPOSIT', 'TRANSFER', 'BALANCE_CHECK', 'PAYMENT') NOT NULL,
    transaction_status ENUM('PENDING', 'APPROVED', 'DENIED', 'FLAGGED') NOT NULL DEFAULT 'PENDING',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location_latitude DECIMAL(10, 8) NULL, -- Transaction location
    location_longitude DECIMAL(11, 8) NULL, -- Transaction location
    location_verified TINYINT(1) DEFAULT 0, -- Whether location was verified
    country_code VARCHAR(2) NULL, -- Country where transaction occurred
    device_id VARCHAR(255) NULL, -- Device identifier
    ip_address VARCHAR(45) NULL, -- IP address
    description TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (account_id) REFERENCES bank_accounts(account_id),
    FOREIGN KEY (card_id) REFERENCES atm_cards(card_id),
    FOREIGN KEY (atm_id) REFERENCES atm_locations(atm_id)
);

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    alert_type ENUM('LOCATION_MISMATCH', 'UNUSUAL_AMOUNT', 'MULTIPLE_ATTEMPTS', 'COUNTRY_RESTRICTION', 'LIMIT_EXCEEDED') NOT NULL,
    alert_message TEXT NOT NULL,
    alert_status ENUM('SENT', 'DELIVERED', 'FAILED') NOT NULL,
    notification_method ENUM('TELEGRAM', 'EMAIL', 'PUSH', 'SMS', 'BOTH') NOT NULL,
    user_response ENUM('APPROVED', 'DENIED', 'NO_RESPONSE') DEFAULT 'NO_RESPONSE',
    response_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id)
);

-- User location history
CREATE TABLE IF NOT EXISTS user_locations (
    location_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(10, 2) NULL, -- Accuracy in meters
    device_id VARCHAR(255) NULL, -- Device identifier
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Authentication logs
CREATE TABLE IF NOT EXISTS auth_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    auth_type ENUM('LOGIN', 'LOGOUT', 'PIN_ENTRY', 'MFA', 'PASSWORD_CHANGE') NOT NULL,
    status ENUM('SUCCESS', 'FAILED') NOT NULL,
    ip_address VARCHAR(45) NULL,
    device_info TEXT NULL,
    location_latitude DECIMAL(10, 8) NULL,
    location_longitude DECIMAL(11, 8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data

-- Sample system settings
INSERT INTO system_settings (setting_name, setting_value) VALUES
('telegram_bot_token', ''),
('email_from', 'atm-alerts@yourdomain.com'),
('google_maps_api_key', ''),
('firebase_api_key', ''),
('firebase_auth_domain', ''),
('firebase_project_id', ''),
('firebase_storage_bucket', ''),
('firebase_messaging_sender_id', ''),
('firebase_app_id', ''),
('firebase_measurement_id', ''),
('location_tracking_interval', '120'); -- 2 minutes in seconds

-- Update the Telegram bot token in system settings
UPDATE system_settings SET setting_value = '5674063615:AAEWqCj-fNCz37SvHklnCCTNolF6eUV-ev8' WHERE setting_name = 'telegram_bot_token';

-- Sample users (password is 'password123' hashed with PASSWORD_DEFAULT)
INSERT INTO users (full_name, phone_number, email, telegram_chat_id, username, password, is_admin, mfa_enabled, firebase_uid) VALUES
('Admin User', '+1234567890', 'admin@example.com', '123456789', 'admin', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 1, 0, NULL),
('John Doe', '+1234567891', 'john.doe@example.com', '123456790', 'johndoe', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 0, 1, NULL),
('Jane Smith', '+1234567892', 'jane.smith@example.com', '123456791', 'janesmith', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 0, 0, NULL),
('Bob Johnson', '+1234567893', 'bob.johnson@example.com', '123456792', 'bobjohnson', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 0, 0, NULL),
('Alice Williams', '+1234567894', 'alice.williams@example.com', '123456793', 'alicew', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 0, 0, NULL),
('Super Admin', '+1234567895', 'superadmin@example.com', '123456794', 'superadmin', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 1, 1, NULL);

-- Add a comment to help users understand the password
-- Note: All users have the password 'password123'
-- In a production environment, each user should have a unique, securely hashed password

-- Sample bank accounts
INSERT INTO bank_accounts (user_id, account_number, account_type, balance, currency, status) VALUES
(2, '1000123456789', 'SAVINGS', 5000.00, 'USD', 'ACTIVE'),
(2, '1000123456790', 'CHECKING', 2500.00, 'USD', 'ACTIVE'),
(3, '1000123456791', 'SAVINGS', 7500.00, 'USD', 'ACTIVE'),
(3, '1000123456792', 'CREDIT', 1000.00, 'USD', 'ACTIVE'),
(4, '1000123456793', 'CHECKING', 3500.00, 'USD', 'ACTIVE');

-- Sample ATM cards (PIN is '1234' hashed)
INSERT INTO atm_cards (user_id, account_id, card_number, card_holder, pin, expiry_month, expiry_year, cvv, card_status, daily_limit, remaining_limit) VALUES
(2, 1, '4111111111111111', 'John Doe', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 12, 2025, '123', 'ACTIVE', 2000.00, 1100.00),
(2, 2, '4222222222222222', 'John Doe', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 10, 2024, '456', 'ACTIVE', 2000.00, 2000.00),
(3, 3, '5555555555554444', 'Jane Smith', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 10, 2024, '456', 'ACTIVE', 2000.00, 2000.00),
(4, 5, '3782822463100005', 'Bob Johnson', '$2y$10$8mnOFRGdw.dNI8C9XurIYe6RGO5YhNj5psYlLQrKaQKLPJqYl3YPi', 6, 2026, '789', 'ACTIVE', 2000.00, 2000.00);

-- Sample country restrictions
INSERT INTO country_restrictions (card_id, country_code, country_name, is_allowed) VALUES
(1, 'LK', 'Sri Lanka', 1),
(1, 'IN', 'India', 1),
(1, 'PK', 'Pakistan', 1),
(1, 'AF', 'Afghanistan', 0),
(1, 'KW', 'Kuwait', 1),
(1, 'QA', 'Qatar', 0),
(1, 'RO', 'Romania', 0);

-- Sample ATM locations
INSERT INTO atm_locations (atm_name, latitude, longitude, address, status) VALUES
('Main Street ATM', 40.7128, -74.0060, '123 Main St, New York, NY 10001', 'ACTIVE'),
('Downtown ATM', 34.0522, -118.2437, '456 Broadway, Los Angeles, CA 90012', 'ACTIVE'),
('University ATM', 41.8781, -87.6298, '789 Campus Dr, Chicago, IL 60607', 'ACTIVE'),
('Shopping Mall ATM', 29.7604, -95.3698, '101 Mall Circle, Houston, TX 77002', 'ACTIVE'),
('Airport ATM', 33.7490, -84.3880, 'Terminal A, Atlanta, GA 30320', 'ACTIVE');

-- Sample geo-fenced regions
INSERT INTO geo_fenced_regions (user_id, region_name, center_latitude, center_longitude, radius_km) VALUES
(2, 'Home', 40.7128, -74.0060, 5.0),
(2, 'Work', 40.7580, -73.9855, 3.0),
(3, 'Home', 34.0522, -118.2437, 4.0),
(3, 'University', 34.0689, -118.4452, 2.0),
(4, 'Home', 41.8781, -87.6298, 5.0);

-- Sample user locations
INSERT INTO user_locations (user_id, latitude, longitude, accuracy) VALUES
(2, 40.7128, -74.0060, 10.5),
(2, 40.7580, -73.9855, 15.2),
(3, 34.0522, -118.2437, 8.7),
(4, 41.8781, -87.6298, 12.3);

-- Sample transactions
INSERT INTO transactions (user_id, account_id, card_id, atm_id, amount, transaction_type, transaction_status, location_latitude, location_longitude, location_verified, country_code, description) VALUES
(2, 1, 1, 1, 100.00, 'WITHDRAWAL', 'APPROVED', 40.7128, -74.0060, 1, 'US', 'ATM withdrawal'),
(2, 2, 2, 2, 50.00, 'WITHDRAWAL', 'FLAGGED', 34.0522, -118.2437, 0, 'US', 'ATM withdrawal - unusual location'),
(3, 3, 3, 3, 200.00, 'DEPOSIT', 'APPROVED', 41.8781, -87.6298, 1, 'US', 'ATM deposit'),
(3, 3, 3, 4, 75.00, 'WITHDRAWAL', 'APPROVED', 29.7604, -95.3698, 1, 'US', 'ATM withdrawal'),
(4, 5, 4, 5, 300.00, 'WITHDRAWAL', 'FLAGGED', 33.7490, -84.3880, 0, 'US', 'ATM withdrawal - unusual location');

-- Sample alerts
INSERT INTO alerts (transaction_id, alert_type, alert_message, alert_status, notification_method, user_response) VALUES
(2, 'LOCATION_MISMATCH', 'Unusual location detected for your ATM transaction. Please verify.', 'DELIVERED', 'BOTH', 'NO_RESPONSE'),
(5, 'LOCATION_MISMATCH', 'Unusual location detected for your ATM transaction. Please verify.', 'DELIVERED', 'EMAIL', 'APPROVED');
