<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

// === Filter Logic
$where = "WHERE e.is_deleted = 0 AND c.instructor_id = ?";
$binds = [$_SESSION['user_id']];
$types = "i";

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

$stats_res = $conn->query("
    SELECT c.title, COUNT(e.id) as total
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.is_deleted = 0 AND c.instructor_id = " . intval($_SESSION['user_id']) . "
    GROUP BY e.course_id
    ORDER BY total DESC
");
$stats = $stats_res->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Enrollments - Instructor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        @media screen and (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
            }
            
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="admin-page-wrapper">
<div class="container mt-5">

    <!-- SweetAlert2 success message -->
    <?php if (!empty($_SESSION['success'])): ?>
        <script>
        document.addEventListener("DOMContentLoaded", () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: <?= json_encode(strip_tags($_SESSION['success'])) ?>,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-clipboard-list"></i> Enrollments Management</h2>
        <a href="add-enrollments.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add Manual Enrollment
        </a>
    </div>

    <!-- Filter Form -->
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
            <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
        </div>
    </form>

    <!-- Stats -->
    <h5>📊 Enrollment Stats by Course:</h5>
    <ul class="list-group mb-4">
        <?php foreach ($stats as $stat): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($stat['title']) ?>
                <span class="badge bg-primary"><?= $stat['total'] ?> enrolled</span>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- Enrollment Table -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Enrolled At</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($enrollments)): ?>
                <tr><td colspan="7" class="text-center text-muted">No enrollments found.</td></tr>
            <?php else: ?>
                <?php foreach ($enrollments as $en): ?>
                    <?php
                    $status = strtolower(trim($en['status']));

                    if (!$status) {
                        if ((float)$en['price'] <= 0) {
                            $status = 'active';
                        } else {
                            $proof_q = $conn->prepare("SELECT status FROM payment_proofs WHERE user_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1");
                            $proof_q->bind_param("ii", $en['user_id'], $en['course_id']);
                            $proof_q->execute();
                            $proof_result = $proof_q->get_result()->fetch_assoc();

                            if ($proof_result) {
                                if ($proof_result['status'] === 'approved') {
                                    $pay_q = $conn->prepare("SELECT status FROM payments WHERE user_id = ? AND course_id = ? AND status = 'success' ORDER BY id DESC LIMIT 1");
                                    $pay_q->bind_param("ii", $en['user_id'], $en['course_id']);
                                    $pay_q->execute();
                                    $pay_result = $pay_q->get_result()->fetch_assoc();
                                    $status = $pay_result ? 'active' : 'pending';
                                } elseif ($proof_result['status'] === 'rejected') {
                                    $status = 'dropped';
                                } else {
                                    $status = 'pending';
                                }
                            } else {
                                $status = 'pending';
                            }
                        }
                    }

                    $badge = match ($status) {
                        'active' => 'warning',
                        'completed' => 'success',
                        'dropped', 'pending' => 'danger',
                        default => 'secondary'
                    };
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($en['enrollment_id']) ?></td>
                        <td><?= htmlspecialchars($en['full_name']) ?></td>
                        <td><?= htmlspecialchars($en['course_title']) ?></td>
                        <td><?= date('Y-m-d', strtotime($en['enrolled_at'])) ?></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-info text-dark fw-semibold"
                                     role="progressbar"
                                     style="width: <?= $en['progress'] ?>%;"
                                     aria-valuenow="<?= $en['progress'] ?>"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                    <?= $en['progress'] ?>%
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($status) ?></span></td>
                        <td>
                            <a href="edit-enrollment.php?id=<?= $en['id'] ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>