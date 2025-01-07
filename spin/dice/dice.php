<?php
// Database configuration
$host = '127.0.0.1';  // Usually 'localhost' for local development
$dbname = 'spin_wheel_db';
$username = 'newuser';  // Replace with your actual database username
$password = 'newuser_password';  

// Connect to database
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter type (daily, weekly, monthly)
$filter = $_GET['filter'] ?? 'daily';
$startDate = null;
$endDate = null;

// Determine date range based on filter
switch ($filter) {
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
        break;
    default: // Daily
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
}

// Query to fetch report data
$sql = "
    SELECT 
        r.round_number,
        b.userId,
        b.amount,
        b.multiplier,
        b.created_at,
        r.winning_multiplier 
    FROM 
        dice_betting_results b
    JOIN 
        dice_rounds r 
    ON 
        b.round = r.round_number
    WHERE 
        b.created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'
    ORDER BY b.created_at DESC
";
$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dice Betting Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">Dice Betting Reports</h1>

        <div class="mb-3">
            <form method="get" class="d-flex justify-content-center">
                <select name="filter" class="form-select w-auto">
                    <option value="daily" <?= $filter === 'daily' ? 'selected' : '' ?>>Daily</option>
                    <option value="weekly" <?= $filter === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="monthly" <?= $filter === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                </select>
                <button type="submit" class="btn btn-primary ms-2">Filter</button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Round Number</th>
                        <th>User ID</th>
                        <th>Bet Amount</th>
                        <th>Multiplier</th>
                        <th>Winning Multiplier</th>
                        <th>Bet Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['round_number']) ?></td>
                                <td><?= htmlspecialchars($row['userId']) ?></td>
                                <td><?= htmlspecialchars($row['amount']) ?></td>
                                <td><?= htmlspecialchars($row['multiplier']) ?></td>
                                <td><?= htmlspecialchars($row['winning_multiplier']) ?></td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No data available for the selected filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>
