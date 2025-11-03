<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

// Filter Logic
$where = "WHERE 1=1";
$binds = [];
$types = "";

if (!empty($_GET['name'])) {
    $where .= " AND u.full_name LIKE ?";
    $binds[] = "%" . $_GET['name'] . "%";
    $types .= "s";
}
if (!empty($_GET['course'])) {
    $where .= " AND c.title LIKE ?";
    $binds[] = "%" . $_GET['course'] . "%";
    $types .= "s";
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(e.enrolled_at) BETWEEN ? AND ?";
    $binds[] = $_GET['from'];
    $binds[] = $_GET['to'];
    $types .= "ss";
}

$sql = "SELECT e.*, u.full_name, u.id as user_id, c.id as course_id, c.title as course_title, c.price
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        $where
        ORDER BY e.enrolled_at DESC";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$binds);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = $conn->query("
    SELECT c.title, COUNT(e.id) as total
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    GROUP BY e.course_id
    ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// Delete Logic
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $check = $conn->prepare("SELECT id FROM enrollments WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $del = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) $_SESSION['success'] = "Enrollment deleted successfully.";
        else $_SESSION['error'] = "Failed to delete enrollment.";
    } else {
        $_SESSION['error'] = "Enrollment not found.";
    }
    header("Location: manage-enrollments.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Enrollments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
    background-color: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
}
.form-control {
    border-radius: 6px;
    border: 1px solid #0d6efd;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: border 0.2s, box-shadow 0.2s;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
}
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .progress-bar {
            min-width: 40px;
        }

        @media (max-width: 768px) {
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 4px;
                padding-bottom: 10px;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="main-content p-4">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manage Enrollments</h2>
            <a href="add-enrollments.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add New Enrollment</a>
        </div>

        <!-- Filter -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="Student Name" value="<?= $_GET['name'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="course" class="form-control" placeholder="Course Title" value="<?= $_GET['course'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="<?= $_GET['from'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="<?= $_GET['to'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>

        <!-- Stats -->
        <h5>📊 Enrollment Stats</h5>
        <ul class="list-group mb-4">
            <?php foreach ($stats as $stat): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($stat['title']) ?>
                    <span class="badge bg-primary"><?= $stat['total'] ?> Enrolled</span>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Date</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$enrollments): ?>
                    <tr><td colspan="7" class="text-center">No records found</td></tr>
                <?php else: ?>
                    <?php foreach ($enrollments as $en): ?>
                        <?php
                        $status = strtolower(trim($en['status']));
                        if (!$status) {
                            $status = ($en['price'] <= 0) ? 'active' : 'pending';
                            $proof = $conn->prepare("SELECT status FROM payment_proofs WHERE user_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1");
                            $proof->bind_param("ii", $en['user_id'], $en['course_id']);
                            $proof->execute();
                            $proofRes = $proof->get_result()->fetch_assoc();
                            if ($proofRes && $proofRes['status'] === 'approved') {
                                $pay = $conn->prepare("SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND status = 'success' LIMIT 1");
                                $pay->bind_param("ii", $en['user_id'], $en['course_id']);
                                $pay->execute();
                                $status = $pay->get_result()->num_rows > 0 ? 'active' : 'pending';
                            } elseif ($proofRes && $proofRes['status'] === 'rejected') {
                                $status = 'dropped';
                            }
                        }
                        $badge = match($status) {
                            'active' => 'warning',
                            'completed' => 'success',
                            'dropped' => 'danger',
                            'pending' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <tr>
                            <td><?= $en['enrollment_id'] ?></td>
                            <td><?= htmlspecialchars($en['full_name']) ?></td>
                            <td><?= htmlspecialchars($en['course_title']) ?></td>
                            <td><?= date('Y-m-d', strtotime($en['enrolled_at'])) ?></td>
                            <td>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: <?= $en['progress'] ?>%;">
                                        <?= $en['progress'] ?>%
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($status) ?></span></td>
                            <td>
                                <a href="edit-enrollment.php?id=<?= $en['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?delete=<?= $en['id'] ?>" onclick="return confirm('Are you sure to delete this enrollment?')" class="btn btn-sm btn-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Notifications -->
<?php if (isset($_SESSION['success'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
        toast: true,
        icon: 'success',
        title: <?= json_encode($_SESSION['success']) ?>,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
});
</script>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
        toast: true,
        icon: 'error',
        title: <?= json_encode($_SESSION['error']) ?>,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
});
</script>
<?php unset($_SESSION['error']); endif; ?>

</body>
</html>