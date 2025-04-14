<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Response Simulator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { margin-top: 30px; }
        .card { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">User Response Simulator</h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Simulate User Response to Alert</h5>
                <form id="response-form">
                    <div class="mb-3">
                        <label for="transaction-id" class="form-label">Transaction ID</label>
                        <select class="form-select" id="transaction-id" required>
                            <option value="">Select Transaction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="response" class="form-label">Response</label>
                        <select class="form-select" id="response" required>
                            <option value="YES">YES (Approve Transaction)</option>
                            <option value="NO">NO (Deny Transaction)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Response</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Response Result</h5>
            </div>
            <div class="card-body">
                <pre id="result">No response sent yet.</pre>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Load flagged transactions
            loadFlaggedTransactions();
            
            // Handle form submission
            $('#response-form').submit(function(e) {
                e.preventDefault();
                
                const responseData = {
                    transaction_id: $('#transaction-id').val(),
                    response: $('#response').val()
                };
                
                $.ajax({
                    url: '../api/process_user_response.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(responseData),
                    success: function(response) {
                        $('#result').html(JSON.stringify(response, null, 2));
                        setTimeout(loadFlaggedTransactions, 1000);
                    },
                    error: function(xhr) {
                        $('#result').html('Error: ' + xhr.responseText);
                    }
                });
            });
        });
        
        function loadFlaggedTransactions() {
            $.getJSON('../api/admin/get_flagged_transactions.php', function(data) {
                let options = '<option value="">Select Transaction</option>';
                data.forEach(function(transaction) {
                    options += `<option value="${transaction.transaction_id}">ID: ${transaction.transaction_id} - $${transaction.amount} at ${transaction.atm_name}</option>`;
                });
                $('#transaction-id').html(options);
            });
        }
    </script>
</body>
</html>
