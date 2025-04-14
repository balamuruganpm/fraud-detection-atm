<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Include the admin dashboard
include 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Fraud Detection Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { margin-top: 30px; }
        .card { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">ATM Fraud Detection System</h1>
        
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">Dashboard</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="false">Users</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="regions-tab" data-bs-toggle="tab" data-bs-target="#regions" type="button" role="tab" aria-controls="regions" aria-selected="false">Geo Regions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="atms-tab" data-bs-toggle="tab" data-bs-target="#atms" type="button" role="tab" aria-controls="atms" aria-selected="false">ATM Locations</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false">Transactions</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button" role="tab" aria-controls="alerts" aria-selected="false">Alerts</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab" aria-controls="settings" aria-selected="false">Settings</button>
            </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Dashboard Tab -->
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <p class="card-text" id="total-users">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Transactions</h5>
                                <p class="card-text" id="total-transactions">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Fraud Alerts</h5>
                                <p class="card-text" id="total-alerts">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Transactions</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="recent-transactions-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="5">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Alerts</h5>
                                <div class="table-responsive">
                                    <table class="table table-striped" id="recent-alerts-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Transaction</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Response</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="5">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Tab -->
            <div class="tab-pane fade" id="users" role="tabpanel" aria-labelledby="users-tab">
                <div class="d-flex justify-content-between mb-3">
                    <h3>User Management</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">Add User</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped" id="users-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Telegram ID</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Geo Regions Tab -->
            <div class="tab-pane fade" id="regions" role="tabpanel" aria-labelledby="regions-tab">
                <div class="d-flex justify-content-between mb-3">
                    <h3>Geo-Fenced Regions</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRegionModal">Add Region</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped" id="regions-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Name</th>
                                <th>Center</th>
                                <th>Radius (km)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ATM Locations Tab -->
            <div class="tab-pane fade" id="atms" role="tabpanel" aria-labelledby="atms-tab">
                <div class="d-flex justify-content-between mb-3">
                    <h3>ATM Locations</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAtmModal">Add ATM</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped" id="atms-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Location</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Transactions Tab -->
            <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                <h3>Transaction History</h3>
                <div class="table-responsive">
                    <table class="table table-striped" id="transactions-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>ATM</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Alerts Tab -->
            <div class="tab-pane fade" id="alerts" role="tabpanel" aria-labelledby="alerts-tab">
                <h3>Alert History</h3>
                <div class="table-responsive">
                    <table class="table table-striped" id="alerts-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Transaction</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Method</th>
                                <th>Response</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notification Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="notification-settings-form">
                            <div class="mb-3">
                                <label for="telegram-bot-token" class="form-label">Telegram Bot Token</label>
                                <input type="text" class="form-control" id="telegram-bot-token" placeholder="Enter your Telegram bot token">
                                <div class="form-text">Create a bot using BotFather on Telegram to get a token.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email-from" class="form-label">Email From Address</label>
                                <input type="email" class="form-control" id="email-from" placeholder="alerts@yourdomain.com">
                            </div>
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Telegram Bot Setup Guide</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>Open Telegram and search for <strong>@BotFather</strong></li>
                            <li>Start a chat and send <code>/newbot</code> command</li>
                            <li>Follow the instructions to create a new bot</li>
                            <li>Copy the API token provided by BotFather</li>
                            <li>Paste the token in the field above</li>
                            <li>Users need to start a chat with your bot before they can receive messages</li>
                            <li>To get a user's chat ID, they should send a message to your bot, then check <code>https://api.telegram.org/bot[YOUR_BOT_TOKEN]/getUpdates</code></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-user-form">
                        <div class="mb-3">
                            <label for="user-name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="user-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="user-phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="user-phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="user-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="user-email" required>
                        </div>
                        <div class="mb-3">
                            <label for="user-telegram" class="form-label">Telegram Chat ID</label>
                            <input type="text" class="form-control" id="user-telegram">
                            <div class="form-text">Optional. User must start a chat with your bot first.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-user-btn">Save</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Region Modal -->
    <div class="modal fade" id="addRegionModal" tabindex="-1" aria-labelledby="addRegionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRegionModalLabel">Add Geo-Fenced Region</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-region-form">
                        <div class="mb-3">
                            <label for="region-user" class="form-label">User</label>
                            <select class="form-select" id="region-user" required>
                                <option value="">Select User</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="region-name" class="form-label">Region Name</label>
                            <input type="text" class="form-control" id="region-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="region-lat" class="form-label">Center Latitude</label>
                            <input type="number" step="0.000001" class="form-control" id="region-lat" required>
                        </div>
                        <div class="mb-3">
                            <label for="region-lng" class="form-label">Center Longitude</label>
                            <input type="number" step="0.000001" class="form-control" id="region-lng" required>
                        </div>
                        <div class="mb-3">
                            <label for="region-radius" class="form-label">Radius (km)</label>
                            <input type="number" step="0.1" class="form-control" id="region-radius" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-region-btn">Save</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add ATM Modal -->
    <div class="modal fade" id="addAtmModal" tabindex="-1" aria-labelledby="addAtmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAtmModalLabel">Add ATM Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-atm-form">
                        <div class="mb-3">
                            <label for="atm-name" class="form-label">ATM Name</label>
                            <input type="text" class="form-control" id="atm-name" required>
                        </div>
                        <div class="mb-3">
                            <label for="atm-lat" class="form-label">Latitude</label>
                            <input type="number" step="0.000001" class="form-control" id="atm-lat" required>
                        </div>
                        <div class="mb-3">
                            <label for="atm-lng" class="form-label">Longitude</label>
                            <input type="number" step="0.000001" class="form-control" id="atm-lng" required>
                        </div>
                        <div class="mb-3">
                            <label for="atm-address" class="form-label">Address</label>
                            <textarea class="form-control" id="atm-address" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-atm-btn">Save</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/admin.js"></script>
</body>
</html>
