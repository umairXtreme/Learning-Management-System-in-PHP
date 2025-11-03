<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit();
}

$studentId = $_SESSION['user_id'];

// 📊 Payment Summary
$totalPaid = $conn->query("SELECT SUM(amount) AS paid FROM payments WHERE user_id = $studentId AND status = 'Success'")
    ->fetch_assoc()['paid'] ?? 0;
$totalTxns = $conn->query("SELECT COUNT(*) AS total FROM payments WHERE user_id = $studentId")
    ->fetch_assoc()['total'] ?? 0;
$successTxns = $conn->query("SELECT COUNT(*) AS total FROM payments WHERE user_id = $studentId AND status = 'Success'")
    ->fetch_assoc()['total'] ?? 0;
$failedTxns = $conn->query("SELECT COUNT(*) AS total FROM payments WHERE user_id = $studentId AND status = 'Failed'")
    ->fetch_assoc()['total'] ?? 0;

// 💰 Payment Records
$payments = $conn->query("
    SELECT p.*, c.title AS course_title 
    FROM payments p 
    JOIN courses c ON c.id = p.course_id 
    WHERE p.user_id = $studentId 
    ORDER BY p.transaction_date DESC
");

// 📤 Payment Proofs
$proofs = $conn->query("
    SELECT pp.*, c.title AS course_title 
    FROM payment_proofs pp 
    JOIN courses c ON c.id = pp.course_id 
    WHERE pp.user_id = $studentId 
    ORDER BY pp.submitted_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- ✅ Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            padding: 20px;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
            <h3><i class="fas fa-wallet me-2"></i> My Payment History</h3>
        </div>

        <!-- 📊 Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>💰 Total Paid</h6>
                    <strong>$<?= number_format($totalPaid, 2) ?></strong>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>🧾 Total Transactions</h6>
                    <strong><?= $totalTxns ?></strong>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>✅ Successful</h6>
                    <strong><?= $successTxns ?></strong>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card p-3 shadow-sm">
                    <h6>❌ Failed</h6>
                    <strong><?= $failedTxns ?></strong>
                </div>
            </div>
        </div>

        <!-- 💳 Transactions Table -->
        <div class="table-responsive mb-5">
            <h4 class="mb-3">Transactions</h4>
            <table class="table table-bordered table-striped shadow-sm">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Course</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['course_title']) ?></td>
                            <td>$<?= number_format($row['amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $row['status'] === 'Success' ? 'success' : 
                                    ($row['status'] === 'Pending' ? 'warning' : 'danger') 
                                ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['method']) ?></td>
                            <td><?= date('d M Y', strtotime($row['transaction_date'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- 📎 Proofs -->
        <div class="table-responsive">
            <h4 class="mb-3">Submitted Payment Proofs</h4>
            <table class="table table-bordered shadow-sm">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Course</th>
                        <th>Account</th>
                        <th>Txn ID</th>
                        <th>Status</th>
                        <th>Proof</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($proof = $proofs->fetch_assoc()): ?>
                        <tr>
                            <td><?= $proof['id'] ?></td>
                            <td><?= htmlspecialchars($proof['course_title']) ?></td>
                            <td><?= htmlspecialchars($proof['account_number']) ?></td>
                            <td><?= htmlspecialchars($proof['reference_number']) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $proof['status'] === 'approved' ? 'success' : 
                                    ($proof['status'] === 'rejected' ? 'danger' : 'warning') 
                                ?>">
                                    <?= ucfirst($proof['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#proofModal<?= $proof['id'] ?>">
                                    View
                                </button>
                            </td>
                            <td>
                                <?php if ($proof['status'] === 'rejected'): ?>
                                    <a href="update-proof.php?id=<?= $proof['id'] ?>" class="btn btn-sm btn-warning">Update</a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <!-- 📦 Modal -->
                        <div class="modal fade" id="proofModal<?= $proof['id'] ?>" tabindex="-1" aria-labelledby="proofModalLabel<?= $proof['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content p-4">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Payment Proof Detail</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul class="list-group">
                                            <li class="list-group-item"><strong>Course:</strong> <?= htmlspecialchars($proof['course_title']) ?></li>
                                            <li class="list-group-item"><strong>Account:</strong> <?= htmlspecialchars($proof['account_number']) ?></li>
                                            <li class="list-group-item"><strong>Amount:</strong> $<?= number_format($proof['amount'], 2) ?></li>
                                            <li class="list-group-item"><strong>Ref No:</strong> <?= htmlspecialchars($proof['reference_number']) ?></li>
                                            <li class="list-group-item"><strong>Notes:</strong> <?= $proof['notes'] ?: 'N/A' ?></li>
                                            <li class="list-group-item"><strong>Submitted:</strong> <?= date('d M Y h:i A', strtotime($proof['submitted_at'])) ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ✅ SweetAlert2 Toast Script -->
<?php if (!empty($_SESSION['toast'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: '<?= $_SESSION['toast']['type'] ?>',
        title: '<?= $_SESSION['toast']['message'] ?>',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
});
</script>
<?php unset($_SESSION['toast']); endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>