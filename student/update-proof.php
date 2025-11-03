<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit();
}

$studentId = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payment-history.php");
    exit();
}

$proofId = intval($_GET['id']);
$errors = [];
$success = null;

$stmt = $conn->prepare("
    SELECT pp.*, c.title AS course_title 
    FROM payment_proofs pp
    JOIN courses c ON pp.course_id = c.id
    WHERE pp.id = ? AND pp.user_id = ?
");
$stmt->bind_param("ii", $proofId, $studentId);
$stmt->execute();
$proof = $stmt->get_result()->fetch_assoc();

if (!$proof) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Proof not found."];
    header("Location: payment-history.php");
    exit();
}

// === Handle POST via JS-confirmed submission ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = trim($_POST['account_number']);
    $ref = trim($_POST['reference_number']);
    $notes = trim($_POST['notes']);

    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] === 0) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedTypes)) {
            $proofDir = "../assets/uploads/proofs/";
            if (!file_exists($proofDir)) mkdir($proofDir, 0775, true);
            $newProofName = "proof_{$studentId}_" . time() . "." . $ext;
            $destPath = $proofDir . $newProofName;

            move_uploaded_file($_FILES['proof_file']['tmp_name'], $destPath);
            $stmt = $conn->prepare("UPDATE payment_proofs SET proof_file = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $newProofName, $proofId, $studentId);
            $stmt->execute();
        } else {
            $errors[] = "❌ Invalid file type. Only JPG, PNG, PDF allowed.";
        }
    }

    if (empty($account) || empty($ref)) {
        $errors[] = "All fields are required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE payment_proofs 
            SET account_number = ?, reference_number = ?, notes = ?, status = 'pending', submitted_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("sssii", $account, $ref, $notes, $proofId, $studentId);

        if ($stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => "📤 Payment proof resubmitted for verification!"];
            header("Location: payment-history.php");
            exit();
        } else {
            $errors[] = "Something went wrong. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update Payment Proof</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            padding: 30px;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="mb-4"><i class="fas fa-pen-nib me-2"></i> Update Payment Proof (Rejected)</h4>

                <?php if ($errors): ?>
                    <div class="alert alert-danger"><?= implode("<br>", $errors) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="row g-4" id="updateProofForm">
                    <div class="col-12">
                        <label class="form-label">Course Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($proof['course_title']) ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Amount</label>
                        <input type="text" class="form-control" value="$<?= number_format($proof['amount'], 2) ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-control" required value="<?= htmlspecialchars($proof['account_number']) ?>">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Reference / TXN ID</label>
                        <input type="text" name="reference_number" class="form-control" required value="<?= htmlspecialchars($proof['reference_number']) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="notes" rows="3" class="form-control"><?= htmlspecialchars($proof['notes']) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Upload New Payment Proof</label>
                        <input type="file" name="proof_file" class="form-control" accept="image/*,application/pdf">
                        <small class="text-muted mt-1 d-block">📤 Uploading a new file will replace the previous one.</small>
                    </div>

                    <div class="col-12 d-flex justify-content-between mt-4">
                        <a href="payment-history.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                        <button type="button" id="confirmSubmitBtn" class="btn btn-success">
                            <i class="fas fa-upload me-1"></i> Submit Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ✅ SweetAlert2 Toast if set -->
<?php if (!empty($_SESSION['toast'])): ?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: '<?= $_SESSION['toast']['type'] ?>',
    title: '<?= $_SESSION['toast']['message'] ?>',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<!-- ✅ SweetAlert2 Confirmation -->
<script>
document.getElementById('confirmSubmitBtn').addEventListener('click', () => {
    Swal.fire({
        title: 'Submit updated payment proof?',
        text: "Your file and reference will be resubmitted for verification.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, submit it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#198754',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('updateProofForm').submit();
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>