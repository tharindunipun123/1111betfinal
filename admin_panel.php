<?php
// Database connection
$host = "127.0.0.1";
$dbname = "spin_wheel_db";
$username = "newuser";
$password = "newuser_password";


$domain = "369betting.com";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle deposit verification
if (isset($_POST['verify_deposit'])) {
    try {
        // Start transaction
        $conn->beginTransaction();

        $receiptId = $_POST['receiptId'];
        
        // Get deposit details including user_id and amount
        $stmt = $conn->prepare("SELECT user_id, amount FROM payment_receipts WHERE id = :receiptId");
        $stmt->bindParam(':receiptId', $receiptId);
        $stmt->execute();
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($deposit) {
            // Update payment_receipts status
            $stmt = $conn->prepare("UPDATE payment_receipts SET status = 'verified' WHERE id = :receiptId");
            $stmt->bindParam(':receiptId', $receiptId);
            $stmt->execute();

            // Update user's wallet
            $stmt = $conn->prepare("UPDATE users SET wallet = wallet + :amount WHERE id = :userId");
            $stmt->bindParam(':amount', $deposit['amount']);
            $stmt->bindParam(':userId', $deposit['user_id']);
            $stmt->execute();

            // Commit transaction
            $conn->commit();
            $success = "Deposit verified and wallet updated successfully!";
        } else {
            throw new Exception("Deposit record not found");
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = "Error processing verification: " . $e->getMessage();
    }
}

// Handle withdrawal approval
if (isset($_POST['approve_withdrawal'])) {
    $requestId = $_POST['requestId'];
    $stmt = $conn->prepare("UPDATE withdrawal_requests SET status = 'approved' WHERE id = :requestId");
    $stmt->bindParam(':requestId', $requestId);
    $stmt->execute();
}

// Fetch pending deposits with user information
$pendingDeposits = $conn->query("
    SELECT pr.*, u.username 
    FROM payment_receipts pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.status = 'pending'
")->fetchAll();

// Fetch pending withdrawals
$pendingWithdrawals = $conn->query("SELECT * FROM withdrawal_requests WHERE status = 'pending'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Admin Panel</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Pending Deposits -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Pending Deposits</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Bank Name</th>
                            <th>Reference Number</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingDeposits as $deposit): ?>
                            <tr>
                                <td><?= htmlspecialchars($deposit['id']) ?></td>
                                <td><?= htmlspecialchars($deposit['username']) ?></td>
                                <td><?= htmlspecialchars($deposit['bank_name']) ?></td>
                                <td><?= htmlspecialchars($deposit['reference_number']) ?></td>
                                <td><?= htmlspecialchars($deposit['amount']) ?></td>
                                <td>
                                    <?php if ($deposit['receipt_url']): ?>
                                        <a href="http://<?= $domain ?>/bet/<?= htmlspecialchars($deposit['receipt_url']) ?>" target="_blank">View Receipt</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to verify this deposit?');">
                                        <input type="hidden" name="receiptId" value="<?= $deposit['id'] ?>">
                                        <button type="submit" name="verify_deposit" class="btn btn-success">Verify</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Withdrawals section remains the same -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Pending Withdrawals</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Name</th>
                            <th>Bank Name</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                            <tr>
                                <td><?= htmlspecialchars($withdrawal['id']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['user_name']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['bank_name']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['amount']) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to approve this withdrawal?');">
                                        <input type="hidden" name="requestId" value="<?= $withdrawal['id'] ?>">
                                        <button type="submit" name="approve_withdrawal" class="btn btn-success">Approve</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>