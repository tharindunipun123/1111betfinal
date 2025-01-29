<?php
// Database connection
$host = "127.0.0.1";
$dbname = "spin_wheel_db";
$username = "newuser";
$password = "newuser_password";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userName = $_POST['userName'];
    $bankName = $_POST['bankName'];
    $accountNumber = $_POST['accountNumber'];
    $accountHolderName = $_POST['accountHolderName'];
    $ifscCode = $_POST['ifscCode'];
    $amount = $_POST['amount'];
    $notes = $_POST['notes'];
    $userId = $_POST['userId'];

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO withdrawal_requests (user_name, bank_name, account_number, account_holder_name, ifsc_code, amount, notes, user_id) VALUES (:userName, :bankName, :accountNumber, :accountHolderName, :ifscCode, :amount, :notes, :userId)");
    $stmt->bindParam(':userName', $userName);
    $stmt->bindParam(':bankName', $bankName);
    $stmt->bindParam(':accountNumber', $accountNumber);
    $stmt->bindParam(':accountHolderName', $accountHolderName);
    $stmt->bindParam(':ifscCode', $ifscCode);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':notes', $notes);
    $stmt->bindParam(':userId', $userId);

    if ($stmt->execute()) {
        $success = "Withdrawal request submitted successfully!";
    } else {
        $error = "Failed to submit withdrawal request.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Withdrawal Request</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        <form method="POST" id="withdrawalForm">
                            <div class="mb-3">
                                <label for="userName" class="form-label">User Name</label>
                                <input type="text" class="form-control" id="userName" name="userName" required>
                            </div>
                            <div class="mb-3">
                                <label for="bankName" class="form-label">Bank Name</label>
                                <input type="text" class="form-control" id="bankName" name="bankName" required>
                            </div>
                            <div class="mb-3">
                                <label for="accountNumber" class="form-label">Account Number</label>
                                <input type="text" class="form-control" id="accountNumber" name="accountNumber" required>
                            </div>
                            <div class="mb-3">
                                <label for="accountHolderName" class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" id="accountHolderName" name="accountHolderName" required>
                            </div>
                            <div class="mb-3">
                                <label for="ifscCode" class="form-label">IFSC Code</label>
                                <input type="text" class="form-control" id="ifscCode" name="ifscCode" required>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes"></textarea>
                            </div>
                            <input type="hidden" name="userId" id="userId">
                            <button type="submit" class="btn btn-primary w-100">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set user ID from localStorage when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            const userId = localStorage.getItem('user_id');
            if (userId) {
                document.getElementById('userId').value = userId;
            } else {
                console.error('User ID not found in localStorage');
                // You might want to redirect to login page or show an error message
                alert('Please login first');
                // Optionally redirect to login page:
                // window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>