# Location-Based Fraud Detection System for ATM Transactions

This system detects potential fraud in ATM transactions by comparing the ATM's location with the user's predefined geo-fenced regions. If a transaction is initiated from an unusual location, the system sends an SMS alert to the user, who can approve or deny the transaction.

## Features

- Track ATM locations and compare with user's geo-fenced regions
- Send SMS alerts for suspicious transactions
- Allow users to approve/deny transactions via SMS
- Admin interface for managing users, regions, and ATMs
- Transaction and alert logging for audit purposes
- Free to implement using WAMP server and free SMS services

## Requirements

- WAMP Server (Windows, Apache, MySQL, PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser for admin interface

## Installation

1. Clone or download this repository to your WAMP server's www directory
2. Import the database schema by running the SQL script in `database/schema.sql`
3. Configure your database connection in `config/database.php`
4. (Optional) Add your SMS API credentials in `services/notification_service.php`
5. Access the admin interface at `http://localhost/atm-fraud-detection/admin/`

## Usage

### Admin Interface

The admin interface allows you to:

1. Manage users
2. Define geo-fenced regions for users
3. Add ATM locations
4. View transaction history
5. Monitor fraud alerts

### Testing the System

Use the provided test tools to simulate:

1. ATM transactions: `http://localhost/atm-fraud-detection/test/atm_simulator.php`
2. User responses to alerts: `http://localhost/atm-fraud-detection/test/user_response_simulator.php`

### SMS Integration

The system supports two methods for sending SMS alerts:

1. Using a free SMS API (like Textbelt's free tier)
2. Using email-to-SMS gateways as a fallback

To use your own SMS API:
1. Edit `services/notification_service.php`
2. Update the `sendSmsViaApi()` method with your API credentials

### Handling User Responses

Users can respond to alerts by:
1. Replying to the SMS with "YES" or "NO" followed by the transaction ID
2. Using a link provided in the SMS that leads to a web page

## Customization

### Google Maps API Integration

To use Google Maps API for more accurate distance calculation:
1. Get a Google Maps API key
2. Update the `LocationUtils` class in `utils/location_utils.php`

### SMS Provider

To use a different SMS provider:
1. Update the `NotificationService` class in `services/notification_service.php`
2. Implement your provider's API in the `sendSmsViaApi()` method

## Security Considerations

This system is designed for educational purposes. In a production environment, you should:

1. Implement proper authentication and authorization
2. Use HTTPS for all communications
3. Encrypt sensitive data in the database
4. Implement rate limiting to prevent abuse
5. Add additional fraud detection mechanisms

## License

This project is open-source and free to use for any purpose.
