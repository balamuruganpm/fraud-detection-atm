-- Create database
CREATE DATABASE IF NOT EXISTS atm_fraud_detection;
USE atm_fraud_detection;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    address VARCHAR(255) NOT NULL
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    atm_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_type ENUM('WITHDRAWAL', 'DEPOSIT', 'BALANCE_CHECK') NOT NULL,
    transaction_status ENUM('PENDING', 'APPROVED', 'DENIED', 'FLAGGED') NOT NULL DEFAULT 'PENDING',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (atm_id) REFERENCES atm_locations(atm_id)
);

-- Alerts table
CREATE TABLE IF NOT EXISTS alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    alert_type ENUM('LOCATION_MISMATCH', 'UNUSUAL_AMOUNT', 'MULTIPLE_ATTEMPTS') NOT NULL,
    alert_message TEXT NOT NULL,
    alert_status ENUM('SENT', 'DELIVERED', 'FAILED') NOT NULL,
    user_response ENUM('APPROVED', 'DENIED', 'NO_RESPONSE') DEFAULT 'NO_RESPONSE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id)
);
