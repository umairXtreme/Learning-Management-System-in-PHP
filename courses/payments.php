<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../validate/login.php");
    exit;
}

if (!isset($_SESSION['pending_payment'])) {
    die("❌ No pending payment session found.");
}

$payment = $_SESSION['pending_payment'];

// 🔍 Fetch admin payment methods only
$stmt = $conn->prepare("
    SELECT 
        pm.method, pm.account_holder_name, pm.account_number,
        pm.bank_name, pm.branch_name, pm.swift_code,
        pm.iban, pm.billing_address
    FROM payment_methods pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.status = 'active' AND u.role = 'admin'
");
$stmt->execute();
$admin_methods = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Instructions | <?= htmlspecialchars($payment['course_title']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- 🌐 Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f7fa;
      font-family: 'Segoe UI', sans-serif;
    }
    .payment-card {
      background: white;
      border-radius: 10px;
      padding: 2rem;
      box-shadow: 0 0 20px rgba(0,0,0,0.05);
    }
    .bank-details {
      background: #f8f9fc;
      border-left: 4px solid #4e73df;
      padding: 1rem;
      border-radius: 5px;
      margin-bottom: 1rem;
    }
    .text-muted small {
      font-size: 0.85rem;
    }
  </style>
</head>
<body>

<?php include_once '../includes/header.php'; ?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="payment-card">
        <h3 class="mb-4"><i class="fas fa-wallet text-primary"></i> Payment Instructions</h3>

        <p><strong>Course:</strong> <?= htmlspecialchars($payment['course_title']) ?></p>
        <p><strong>Amount:</strong> $<?= $payment['amount'] ?></p>
        <p><strong>Enrollment ID:</strong> <code><?= $payment['enrollment_id'] ?></code></p>

        <?php if ($admin_methods->num_rows > 0): ?>
            <?php while ($method = $admin_methods->fetch_assoc()): ?>
                <div class="bank-details">
                    <h5><i class="fas fa-university"></i> <?= htmlspecialchars($method['method']) ?></h5>
                    <p><strong>Account Holder:</strong> <?= htmlspecialchars($method['account_holder_name']) ?></p>
                    <p><strong>Account Number:</strong> <?= htmlspecialchars($method['account_number']) ?></p>
                    <?php if (!empty($method['bank_name'])): ?>
                        <p><strong>Bank:</strong> <?= htmlspecialchars($method['bank_name']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($method['branch_name'])): ?>
                        <p><strong>Branch:</strong> <?= htmlspecialchars($method['branch_name']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($method['swift_code'])): ?>
                        <p><strong>SWIFT Code:</strong> <?= htmlspecialchars($method['swift_code']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($method['iban'])): ?>
                        <p><strong>IBAN:</strong> <?= htmlspecialchars($method['iban']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($method['billing_address'])): ?>
                        <p><strong>Billing Address:</strong> <?= htmlspecialchars($method['billing_address']) ?></p>
                    <?php endif; ?>
                    <div class="alert alert-warning mt-4">
  <i class="fas fa-exclamation-triangle"></i> Please do not close this page until you've completed your payment and uploaded your proof using the button below.
</div>

<a href="upload-payment.php?enrollment_id=<?= urlencode($payment['enrollment_id']) ?>&course_id=<?= (int)$payment['course_id'] ?>" class="btn btn-primary mt-3">
  <i class="fas fa-upload"></i> Upload Payment Proof
</a>

                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="alert alert-warning">⚠️ No active admin payment methods found.</div>
        <?php endif; ?>

        <div class="alert alert-info mt-4">
          <i class="fas fa-info-circle"></i> After making the payment, please contact admin with the enrollment ID above. Once confirmed, you'll get access to the course.
        </div>

        <a href="../index.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Back to Home</a>
      </div>
    </div>
  </div>
</div>

<!-- 🔋 Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>