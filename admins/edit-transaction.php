<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Transaction ID is missing');
}

$txn_id = intval($_GET['id']);

// Get payment
$stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
$stmt->bind_param("i", $txn_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("Transaction not found");
}
$payment = $result->fetch_assoc();

// Fetch methods
$methods = $conn->query("SELECT method FROM payment_methods WHERE status = 'active' ORDER BY method ASC")->fetch_all(MYSQLI_ASSOC);

// Update logic
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statusInput = ucfirst(strtolower(trim($_POST['status'])));
    $validStatuses = ['Success', 'Pending', 'Failed'];
    $status = in_array($statusInput, $validStatuses) ? $statusInput : 'Pending';

    $method = $_POST['method'];
    $amount = floatval($_POST['amount']);
    $account_holder_name = $_POST['account_holder_name'];
    $billing_address = $_POST['billing_address'];
    $account_number = $_POST['account_number'];
    $bank_name = $_POST['bank_name'];
    $branch_code = $_POST['branch_code'];
    $swift_code = $_POST['swift_code'];
    $iban = $_POST['iban'];
    $admin_note = $_POST['admin_note'];

    $stmt = $conn->prepare("UPDATE payments SET 
        status=?, method=?, amount=?, account_holder_name=?, billing_address=?, 
        account_number=?, bank_name=?, branch_code=?, swift_code=?, iban=?, admin_note=? 
        WHERE id=?");

    $stmt->bind_param("ssdssssssssi",
        $status, $method, $amount, $account_holder_name, $billing_address,
        $account_number, $bank_name, $branch_code, $swift_code, $iban, $admin_note,
        $txn_id
    );

    if ($stmt->execute()) {
        $_SESSION['txn_updated'] = true;
        header("Location: manage-payments.php");
        exit();
    } else {
        $_SESSION['txn_error'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Transaction - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
          background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        .admin-container {
            margin-left: 250px;
            padding: 30px;
        }
        @media (max-width: 768px) {
            .admin-container {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
<div class="container mt-5">
    <h3><i class="fas fa-edit me-2"></i> Edit Transaction</h3>

    <form method="POST" class="mt-4">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label>Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['Success', 'Pending', 'Failed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $payment['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label>Method</label>
                <select name="method" class="form-select">
                    <?php foreach ($methods as $m): ?>
                        <option value="<?= $m['method'] ?>" <?= $payment['method'] === $m['method'] ? 'selected' : '' ?>>
                            <?= $m['method'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label>Amount</label>
                <input type="number" name="amount" step="0.01" value="<?= $payment['amount'] ?>" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
                <label>Account Holder</label>
                <input type="text" name="account_holder_name" value="<?= htmlspecialchars($payment['account_holder_name']) ?>" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label>Billing Address</label>
                <input type="text" name="billing_address" value="<?= htmlspecialchars($payment['billing_address']) ?>" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
                <label>Account Number</label>
                <input type="text" name="account_number" value="<?= htmlspecialchars($payment['account_number']) ?>" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
                <label>Bank Name</label>
                <input type="text" name="bank_name" value="<?= htmlspecialchars($payment['bank_name']) ?>" class="form-control">
            </div>

            <div class="col-md-4 mb-3">
                <label>Branch Code</label>
                <input type="text" name="branch_code" value="<?= htmlspecialchars($payment['branch_code']) ?>" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label>SWIFT Code</label>
                <input type="text" name="swift_code" value="<?= htmlspecialchars($payment['swift_code']) ?>" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <label>IBAN</label>
                <input type="text" name="iban" value="<?= htmlspecialchars($payment['iban']) ?>" class="form-control">
            </div>

            <div class="col-12 mb-3">
                <label>Admin Note</label>
                <textarea name="admin_note" class="form-control"><?= htmlspecialchars($payment['admin_note']) ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Transaction</button>
        <a href="manage-payments.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Cancel</a>
    </form>
</div>
                    </div>
<script>
document.addEventListener("DOMContentLoaded", () => {
    // 🔥 Error Alert (on failed update)
    <?php if (!empty($_SESSION['txn_error'])): unset($_SESSION['txn_error']); ?>
        Swal.fire({
            icon: 'error',
            title: 'Update Failed!',
            text: 'Something went wrong while updating the transaction.',
            confirmButtonColor: '#d33'
        });
    <?php endif; ?>

    // 🛡️ Confirm before submission
    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault(); // Block native submission

            Swal.fire({
                title: 'Confirm Update',
                text: "Are you sure you want to update this transaction?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Update',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit(); // Safe to submit now
                }
            });
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>