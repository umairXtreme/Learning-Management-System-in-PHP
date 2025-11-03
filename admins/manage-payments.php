<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

// Delete Payment
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $_SESSION['deleted'] = true;
    header("Location: manage-payments.php");
    exit();
}

// Handle Proof Action
if (isset($_GET['proof_action'], $_GET['proof_id'])) {
    $proofId = intval($_GET['proof_id']);
    $action = $_GET['proof_action'] === 'approve' ? 'approved' : 'Rejected';

    $updateStmt = $conn->prepare("UPDATE payment_proofs SET status = ? WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("si", $action, $proofId);
        $updateStmt->execute();
    } else {
        die("Failed to prepare statement: " . $conn->error);
    }

    $proofStmt = $conn->prepare("SELECT * FROM payment_proofs WHERE id = ?");
    $proofStmt->bind_param("i", $proofId);
    $proofStmt->execute();
    $proof = $proofStmt->get_result()->fetch_assoc();

    $status = $action === 'approved' ? 'Success' : 'Failed';

    $check = $conn->prepare("SELECT id FROM payments WHERE proof_id = ?");
    $check->bind_param("i", $proofId);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $insert = $conn->prepare("INSERT INTO payments (user_id, course_id, method, account_number, amount, status, transaction_date, reference_number, proof_id) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $insert->bind_param("iissdssi",
            $proof['user_id'], $proof['course_id'], $proof['method'],
            $proof['account_number'], $proof['amount'], $status,
            $proof['reference_number'], $proofId
        );
        $insert->execute();
    } else {
        $update = $conn->prepare("UPDATE payments SET status = ?, transaction_date = NOW() WHERE proof_id = ?");
        $update->bind_param("si", $status, $proofId);
        $update->execute();
    }

    $_SESSION['proof_updated'] = true;
    header("Location: manage-payments.php");
    exit();
}

// Stats
$totalRevenue = $conn->query("SELECT SUM(amount) AS revenue FROM payments WHERE status = 'Success'")->fetch_assoc()['revenue'] ?? 0;
$totalTransactions = $conn->query("SELECT COUNT(*) AS total FROM payments")->fetch_assoc()['total'] ?? 0;
$successPayments = $conn->query("SELECT COUNT(*) AS success FROM payments WHERE status = 'Success'")->fetch_assoc()['success'] ?? 0;
$failedPayments = $conn->query("SELECT COUNT(*) AS failed FROM payments WHERE status = 'Failed'")->fetch_assoc()['failed'] ?? 0;

$payments = $conn->query("SELECT p.*, u.full_name AS user_name, c.title AS course_title FROM payments p JOIN users u ON u.id = p.user_id JOIN courses c ON c.id = p.course_id ORDER BY p.transaction_date DESC");
$proofs = $conn->query("SELECT pp.*, u.full_name, c.title AS course_title FROM payment_proofs pp JOIN users u ON u.id = pp.user_id JOIN courses c ON c.id = pp.course_id ORDER BY pp.submitted_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Payments - Admin</title>
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
            .card {
                margin-bottom: 15px !important;
            }
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h3><i class="fas fa-money-check-alt"></i> Manage Payments</h3>
        <a href="payment-methods.php" class="btn btn-outline-success mt-2 mt-md-0">
            <i class="fas fa-plus-circle"></i> Add Payment Method
        </a>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-sm-6 col-md-3"><div class="card p-3"><h6>💰 Total Revenue</h6><strong>$ <?= number_format($totalRevenue, 2) ?></strong></div></div>
        <div class="col-sm-6 col-md-3"><div class="card p-3"><h6>🧾 Transactions</h6><strong><?= $totalTransactions ?></strong></div></div>
        <div class="col-sm-6 col-md-3"><div class="card p-3"><h6>✅ Success</h6><strong><?= $successPayments ?></strong></div></div>
        <div class="col-sm-6 col-md-3"><div class="card p-3"><h6>❌ Failed</h6><strong><?= $failedPayments ?></strong></div></div>
    </div>

    <!-- Payments Table -->
    <h4 class="mb-3">📑 Payment Records</h4>
    <div class="table-responsive mb-5">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>User</th><th>Course</th><th>Amount</th><th>Status</th><th>Method</th><th>Date</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['user_name']) ?></td>
                    <td><?= htmlspecialchars($row['course_title']) ?></td>
                    <td>$ <?= number_format($row['amount'], 2) ?></td>
                    <td><span class="badge bg-<?= $row['status'] === 'Success' ? 'success' : ($row['status'] === 'Pending' ? 'warning' : 'danger') ?>"><?= $row['status'] ?></span></td>
                    <td><?= htmlspecialchars($row['method']) ?></td>
                    <td><?= date('d M Y', strtotime($row['transaction_date'])) ?></td>
                    <td>
                        <a href="edit-transaction.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                        <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirmDelete()" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Payment Proofs Table -->
    <h4 class="mb-3">🧾 Payment Proofs</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th><th>User</th><th>Course</th><th>Account</th><th>Txn ID</th><th>Status</th><th>Proof</th><th>Action</th>
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
                    <td><span class="badge bg-<?= $p['status'] === 'approved' ? 'success' : ($p['status'] === 'Rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#proofModal<?= $p['id'] ?>">View</button>
                        <div class="modal fade" id="proofModal<?= $p['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content p-3">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Payment Proof</h5>
                                        <button class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item"><strong>User:</strong> <?= htmlspecialchars($p['full_name']) ?></li>
                                            <li class="list-group-item"><strong>Course:</strong> <?= htmlspecialchars($p['course_title']) ?></li>
                                            <li class="list-group-item"><strong>Method:</strong> <?= htmlspecialchars($p['method']) ?></li>
                                            <li class="list-group-item"><strong>Account:</strong> <?= htmlspecialchars($p['account_number']) ?></li>
                                            <li class="list-group-item"><strong>Amount:</strong> $ <?= number_format($p['amount'], 2) ?></li>
                                            <li class="list-group-item"><strong>Reference:</strong> <?= htmlspecialchars($p['reference_number']) ?></li>
                                            <li class="list-group-item"><strong>Submitted:</strong> <?= date('d M Y h:i A', strtotime($p['submitted_at'])) ?></li>
                                            <li class="list-group-item"><strong>Notes:</strong> <?= htmlspecialchars($p['notes'] ?: 'N/A') ?></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Change</button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item text-success" href="?proof_action=approve&proof_id=<?= $p['id'] ?>"><i class="fas fa-check-circle"></i> Approve</a></li>
                                <li><a class="dropdown-item text-danger" href="?proof_action=reject&proof_id=<?= $p['id'] ?>"><i class="fas fa-times-circle"></i> Reject</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    <?php if (!empty($_SESSION['txn_updated'])): unset($_SESSION['txn_updated']); ?>
    Swal.fire({ toast: true, icon: 'success', title: 'Transaction updated!', position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true });
    <?php endif; ?>
    <?php if (!empty($_SESSION['deleted'])): unset($_SESSION['deleted']); ?>
    Swal.fire({ toast: true, icon: 'success', title: 'Payment deleted!', position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true });
    <?php endif; ?>
    <?php if (!empty($_SESSION['proof_updated'])): unset($_SESSION['proof_updated']); ?>
    Swal.fire({ toast: true, icon: 'info', title: 'Proof status updated.', position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true });
    <?php endif; ?>
});

function confirmDelete() {
    return confirm("Are you sure you want to delete this payment?");
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>