<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: ../validate/login.php");
  exit;
}

$user_id = (int) $_SESSION['user_id'];
$enrollment_id = $_GET['enrollment_id'] ?? '';
$course_id = (int) ($_GET['course_id'] ?? 0);

// Get Course Info
$course_stmt = $conn->prepare("SELECT title, price FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();
$course_price_usd = number_format((float)$course['price'], 2);

$errors = [];
$success = '';
$redirect = false;

// Upload Dir
$upload_dir = __DIR__ . '/../assets/uploads/paymentproofs/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = trim($_POST['method'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $file = $_FILES['proof_file'] ?? null;

    // Validation
    if (!$method) $errors[] = "Payment method is required.";
    if (!$account_number) $errors[] = "Account number is required.";
    if (!$reference) $errors[] = "Transaction ID is required.";
    if (!$file || $file['error'] !== 0) $errors[] = "Screenshot is required.";
    $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    if ($file && !in_array(mime_content_type($file['tmp_name']), $allowed)) {
        $errors[] = "Upload must be JPG, PNG, or PDF.";
    }

    if (empty($errors)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('proof_') . '.' . $ext;
        $filepath = $upload_dir . $filename;
        move_uploaded_file($file['tmp_name'], $filepath);

        $db_path = 'assets/uploads/paymentproofs/' . $filename;

        $stmt = $conn->prepare("
            INSERT INTO payment_proofs 
            (user_id, enrollment_id, course_id, method, account_number, amount, reference_number, notes, proof_file, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param(
            "isssssdss",
            $user_id, $enrollment_id, $course_id, $method, $account_number,
            $course_price_usd, $reference, $notes, $db_path
        );
        $stmt->execute();

        $success = "✅ Your payment proof for Enrollment ID <b>$enrollment_id</b> and course <b>{$course['title']}</b> has been received. We'll verify and update your enrollment status shortly.";
        $redirect = true;
    }
}
$redirect_url = '../student/student-dashboard.php'; // default

  if ($_SESSION['role'] === 'admin') {
    $redirect_url = '../admin/admin-dashboard.php';
  } elseif ($_SESSION['role'] === 'instructor') {
    $redirect_url = '../instructor/instructor-dashboard.php';
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload Payment Proof</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <script>
  <?php if ($redirect): ?>
    setTimeout(() => {
      alert("Redirecting to your account dashboard...");
      window.location.href = "<?= $redirect_url ?>";
    }, 3000);
  <?php endif; ?>
</script>


</head>
<body class="bg-light">

<?php include_once '../includes/header.php'; ?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-4"><i class="fas fa-upload text-primary"></i> Upload Payment Proof</h4>

          <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
          <?php elseif ($success): ?>
            <div class="alert alert-success"><?= $success ?><br><strong>⏳ Redirecting shortly...</strong></div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
              <label>Payment Method <span class="text-danger">*</span></label>
              <input type="text" name="method" class="form-control" placeholder="e.g., Bank Transfer, UPI, Easypaisa" required>
            </div>

            <div class="mb-3">
              <label>Account Number (payer) <span class="text-danger">*</span></label>
              <input type="text" name="account_number" class="form-control" placeholder="e.g., 0987-XXXX-XXXX" required>
            </div>

            <div class="mb-3">
              <label>Amount Paid (USD)</label>
              <input type="text" class="form-control" value="<?= $course_price_usd ?>" readonly>
              <small class="text-muted">You can pay in your local currency based on today's Google rate. Mention the currency & rate in notes.</small>
            </div>

            <div class="mb-3">
              <label>Reference / Transaction ID <span class="text-danger">*</span></label>
              <input type="text" name="reference" class="form-control" placeholder="e.g., TXN12345678" required>
            </div>

            <div class="mb-3">
              <label>Upload Payment Screenshot <span class="text-danger">*</span></label>
              <input type="file" name="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
            </div>

            <div class="mb-3">
              <label>Special Notes / Request</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="e.g., Paid in PKR at 285/USD, reference from XYZ bank"></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Proof</button>
            <a href="payments.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Back</a>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>