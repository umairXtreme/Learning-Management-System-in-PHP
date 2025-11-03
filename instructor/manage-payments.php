<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$instructorId = $_SESSION['user_id'];

// 📊 Dashboard Stats
$totalRevenue = $conn->query("SELECT SUM(p.amount) AS revenue 
    FROM payments p 
    JOIN courses c ON p.course_id = c.id 
    WHERE p.status = 'Success' AND c.instructor_id = $instructorId
")->fetch_assoc()['revenue'] ?? 0;

$totalTransactions = $conn->query("SELECT COUNT(*) AS total 
    FROM payments p 
    JOIN courses c ON p.course_id = c.id 
    WHERE c.instructor_id = $instructorId
")->fetch_assoc()['total'] ?? 0;

$successPayments = $conn->query("SELECT COUNT(*) AS success 
    FROM payments p 
    JOIN courses c ON p.course_id = c.id 
    WHERE p.status = 'Success' AND c.instructor_id = $instructorId
")->fetch_assoc()['success'] ?? 0;

$failedPayments = $conn->query("SELECT COUNT(*) AS failed 
    FROM payments p 
    JOIN courses c ON p.course_id = c.id 
    WHERE p.status = 'Failed' AND c.instructor_id = $instructorId
")->fetch_assoc()['failed'] ?? 0;

// 💳 All Payments
$payments = $conn->query("
    SELECT p.*, u.full_name AS user_name, c.title AS course_title 
    FROM payments p 
    JOIN users u ON u.id = p.user_id 
    JOIN courses c ON c.id = p.course_id 
    WHERE c.instructor_id = $instructorId
    ORDER BY p.transaction_date DESC
");

// 📥 Proofs
$proofs = $conn->query("
    SELECT pp.*, u.full_name, c.title AS course_title 
    FROM payment_proofs pp 
    JOIN users u ON u.id = pp.user_id 
    JOIN courses c ON c.id = pp.course_id 
    WHERE c.instructor_id = $instructorId
    ORDER BY pp.submitted_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Payments - Instructor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 20px;
        }
        .table th, .table td {
            vertical-align: middle;
        }
       @media screen and (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
                padding: 10px;
            }
            .summary-card{
                margin-bottom: 1rem !important;
            }
            
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="admin-page-wrapper">
<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-money-check-alt"></i> Manage Payments</h3>
        <a href="payment-methods.php" class="btn btn-outline-success">
            <i class="fas fa-plus-circle"></i> Add Payment Method
        </a>
    </div>

    <!-- SweetAlert Trigger -->
    <script>
        <?php if (isset($_GET['deleted'])): ?>
        Swal.fire({ icon: 'warning', title: 'Payment Deleted', toast: true, timer: 3000, showConfirmButton: false, position: 'top-end' });
        <?php elseif (isset($_GET['proof_updated'])): ?>
        Swal.fire({ icon: 'info', title: 'Proof Status Updated', toast: true, timer: 3000, showConfirmButton: false, position: 'top-end' });
        <?php endif; ?>
    </script>

    <!-- Summary Cards -->
    <!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 summary-card">
        <div class="card text-center p-3">
            <h6>💰 Total Revenue</h6>
            <strong>$ <?= number_format($totalRevenue, 2) ?></strong>
        </div>
    </div>
    <div class="col-md-3 summary-card">
        <div class="card text-center p-3">
            <h6>🧾 Transactions</h6>
            <strong><?= $totalTransactions ?></strong>
        </div>
    </div>
    <div class="col-md-3 summary-card">
        <div class="card text-center p-3">
            <h6>✅ Success</h6>
            <strong><?= $successPayments ?></strong>
        </div>
    </div>
    <div class="col-md-3 summary-card">
        <div class="card text-center p-3">
            <h6>❌ Failed</h6>
            <strong><?= $failedPayments ?></strong>
        </div>
    </div>
</div>

    <!-- 💳 Payment Records -->
    <h4 class="mb-3">📑 Payment Records</h4>
    <div class="table-responsive mb-5">
        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>User</th><th>Course</th><th>Amount</th><th>Status</th>
                    <th>Method</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $payments->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= htmlspecialchars($row['course_title']) ?></td>
                        <td>$ <?= number_format($row['amount'], 2) ?></td>
                        <td>
                            <span class="badge bg-<?= $row['status'] === 'Success' ? 'success' : ($row['status'] === 'Pending' ? 'warning' : 'danger') ?>">
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

    <!-- 🧾 Payment Proofs -->
    <h4 class="mb-3">🧾 Payment Proofs</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>User</th><th>Course</th><th>Account</th><th>Txn ID</th>
                    <th>Status</th><th>Proof</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $proofs->fetch_assoc()): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                        <td><?= htmlspecialchars($p['course_title']) ?></td>
                        <td><?= htmlspecialchars($p['account_number']) ?></td>
                        <td><?= htmlspecialchars($p['reference_number']) ?></td>
                        <td>
                            <span class="badge bg-<?= $p['status'] === 'approved' ? 'success' : ($p['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                <?= ucfirst($p['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#proofModal<?= $p['id'] ?>">
                                View
                            </button>

                            <!-- Modal -->
                            <div class="modal fade" id="proofModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Payment Proof</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item"><strong>User:</strong> <?= htmlspecialchars($p['full_name']) ?></li>
                                                <li class="list-group-item"><strong>Course:</strong> <?= htmlspecialchars($p['course_title']) ?></li>
                                                <li class="list-group-item"><strong>Account:</strong> <?= htmlspecialchars($p['account_number']) ?></li>
                                                <li class="list-group-item"><strong>Amount:</strong> $ <?= number_format($p['amount'], 2) ?></li>
                                                <li class="list-group-item"><strong>Ref ID:</strong> <?= htmlspecialchars($p['reference_number']) ?></li>
                                                <li class="list-group-item"><strong>Submitted:</strong> <?= date('d M Y h:i A', strtotime($p['submitted_at'])) ?></li>
                                                <li class="list-group-item"><strong>Notes:</strong> <?= $p['notes'] ? htmlspecialchars($p['notes']) : 'N/A' ?></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
                </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>