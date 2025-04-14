<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATM Transaction Simulator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { margin-top: 30px; }
        .card { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">ATM Transaction Simulator</h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Simulate Transaction</h5>
                <form id="transaction-form">
                    <div class="mb-3">
                        <label for="user-id" class="form-label">User</label>
                        <select class="form-select" id="user-id" required>
                            <option value="">Select User</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="atm-id" class="form-label">ATM Location</label>
                        <select class="form-select" id="atm-id" required>
                            <option value="">Select ATM</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" id="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="transaction-type" class="form-label">Transaction Type</label>
                        <select class="form-select" id="transaction-type" required>
                            <option value="WITHDRAWAL">Withdrawal</option>
                            <option value="DEPOSIT">Deposit</option>
                            <option value="BALANCE_CHECK">Balance Check</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Process Transaction</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Transaction Result</h5>
            </div>
            <div class="card-body">
                <pre id="result">No transaction processed yet.</pre>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Load users
            $.getJSON('../api/admin/get_users.php', function(data) {
                let options = '<option value="">Select User</option>';
                data.forEach(function(user) {
                    options += `<option value="${user.user_id}">${user.full_name}</option>`;
                });
                $('#user-id').html(options);
            });
            
            // Load ATMs
            $.getJSON('../api/admin/get_atms.php', function(data) {
                let options = '<option value="">Select ATM</option>';
                data.forEach(function(atm) {
                    options += `<option value="${atm.atm_id}">${atm.atm_name} - ${atm.address}</option>`;
                });
                $('#atm-id').html(options);
            });
            
            // Handle form submission
            $('#transaction-form').submit(function(e) {
                e.preventDefault();
                
                const transactionData = {
                    user_id: $('#user-id').val(),
                    atm_id: $('#atm-id').val(),
                    amount: $('#amount').val(),
                    transaction_type: $('#transaction-type').val()
                };
                
                $.ajax({
                    url: '../api/process_transaction.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(transactionData),
                    success: function(response) {
                        $('#result').html(JSON.stringify(response, null, 2));
                        
                        if (response.fraud_detected) {
                            alert('Potential fraud detected! An alert has been sent to the user.');
                        }
                    },
                    error: function(xhr) {
                        $('#result').html('Error: ' + xhr.responseText);
                    }
                });
            });
        });
    </script>
</body>
</html>
