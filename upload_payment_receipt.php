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
    $bankName = $_POST['bankName'];
    $referenceNumber = $_POST['referenceNumber'];
    $amount = $_POST['amount'];
    $userId = $_POST['userId'];

    // Create uploads directory if it doesn't exist
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Make sure the directory is writable
    if (!is_writable($targetDir)) {
        chmod($targetDir, 0777);
    }

    // Generate unique filename to prevent overwriting
    $timestamp = time();
    $fileName = $timestamp . '_' . basename($_FILES["receipt"]["name"]);
    $targetFile = $targetDir . $fileName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Validate upload
    $uploadOk = 1;
    $error = "";

    // Check if file was actually uploaded
    if (!isset($_FILES["receipt"]) || $_FILES["receipt"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Error during file upload: " . $_FILES["receipt"]["error"];
        $uploadOk = 0;
    }
    // Check if file is an actual image
    elseif (!getimagesize($_FILES["receipt"]["tmp_name"])) {
        $error = "File is not an image.";
        $uploadOk = 0;
    }
    // Check file size (5MB limit)
    elseif ($_FILES["receipt"]["size"] > 5000000) {
        $error = "File is too large (max 5MB).";
        $uploadOk = 0;
    }
    // Allow certain file formats
    elseif ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        $error = "Only JPG, JPEG, & PNG files are allowed.";
        $uploadOk = 0;
    }

    // If everything is ok, try to upload file
    if ($uploadOk == 1) {
        try {
            if (move_uploaded_file($_FILES["receipt"]["tmp_name"], $targetFile)) {
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO payment_receipts (bank_name, reference_number, amount, receipt_url, user_id) VALUES (:bankName, :referenceNumber, :amount, :receiptUrl, :userId)");
                $stmt->bindParam(':bankName', $bankName);
                $stmt->bindParam(':referenceNumber', $referenceNumber);
                $stmt->bindParam(':amount', $amount);
                $stmt->bindParam(':receiptUrl', $targetFile);
                $stmt->bindParam(':userId', $userId);

                if ($stmt->execute()) {
                    $success = "Payment receipt uploaded successfully!";
                } else {
                    $error = "Failed to save to database.";
                    // Remove uploaded file if database insert fails
                    if (file_exists($targetFile)) {
                        unlink($targetFile);
                    }
                }
            } else {
                $error = "Error moving uploaded file. Check directory permissions.";
            }
        } catch (Exception $e) {
            $error = "Error processing upload: " . $e->getMessage();
        }
    }
}

// Rest of the HTML remains the same...
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Payment Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Upload Payment Receipt</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" id="paymentForm">
                            <div class="mb-3">
                                <label for="bankName" class="form-label">Mobile Number</label>
                                <input type="text" class="form-control" id="bankName" name="bankName" required>
                            </div>
                            <div class="mb-3">
                                <label for="referenceNumber" class="form-label">Reference Number</label>
                                <input type="text" class="form-control" id="referenceNumber" name="referenceNumber" required>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label for="receipt" class="form-label">Upload Receipt</label>
                                <input type="file" class="form-control" id="receipt" name="receipt" accept="image/jpeg,image/png" required>
                                <small class="text-muted">Maximum file size: 5MB (JPG or PNG only)</small>
                            </div>
                            <input type="hidden" name="userId" id="userId">
                            <button type="submit" class="btn btn-primary w-100">Upload</button>
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
                // Show error message
                alert('Please login first');
                // Optionally redirect to login page:
                // window.location.href = 'login.php';
            }
        });

        // Optional: Add form submission validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const userId = document.getElementById('userId').value;
            if (!userId) {
                e.preventDefault();
                alert('User ID is required. Please login again.');
                // window.location.href = 'login.php';
            }
        });
    </script>
</body>
</html>