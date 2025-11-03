<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

$where = "e.user_id = ?";
$params = [$student_id];
$types = "i";

if (!empty($_GET['course'])) {
    $where .= " AND c.title LIKE ?";
    $params[] = "%" . $_GET['course'] . "%";
    $types .= "s";
}
if (!empty($_GET['instructor'])) {
    $where .= " AND i.full_name LIKE ?";
    $params[] = "%" . $_GET['instructor'] . "%";
    $types .= "s";
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(e.enrolled_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= "ss";
}

$sql = "
    SELECT e.*, c.title AS course_title, c.price, c.id AS course_id, 
           i.full_name AS instructor_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    JOIN users i ON c.instructor_id = i.id
    WHERE $where
    ORDER BY e.enrolled_at DESC
";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$enrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enrollment History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
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
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h2 class="mb-0"><i class="fas fa-clock-rotate-left"></i> Enrollment History</h2>
    </div>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="course" class="form-control" placeholder="Course Title" value="<?= $_GET['course'] ?? '' ?>">
        </div>
        <div class="col-md-3">
            <input type="text" name="instructor" class="form-control" placeholder="Instructor" value="<?= $_GET['instructor'] ?? '' ?>">
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

    <?php if (empty($enrollments)): ?>
        <div class="alert alert-info">No enrollment history found.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Enrollment ID</th>
                        <th>Instructor</th>
                        <th>Course</th>
                        <th>Enrolled At</th>
                        <th>Completed At</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $en): ?>
                        <?php
                        $status = strtolower(trim($en['status']));
                        if (!$status) {
                            if ($en['price'] <= 0) {
                                $status = 'active';
                            } else {
                                $proof_q = $conn->prepare("SELECT status FROM payment_proofs WHERE user_id = ? AND course_id = ? ORDER BY id DESC LIMIT 1");
                                $proof_q->bind_param("ii", $student_id, $en['course_id']);
                                $proof_q->execute();
                                $proof = $proof_q->get_result()->fetch_assoc();
                                $status = $proof['status'] === 'approved' ? 'active' : 'pending';
                            }
                        }

                        $displayStatus = ucfirst($status);
                        $badge = match ($status) {
                            'active' => 'warning',
                            'completed' => 'success',
                            'dropped', 'pending' => 'danger',
                            default => 'secondary'
                        };

                        $completedAt = ($en['progress'] >= 100 && $status === 'completed') ? date('d M Y', strtotime($en['updated_at'] ?? $en['enrolled_at'])) : '—';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($en['enrollment_id']) ?></td>
                            <td><?= htmlspecialchars($en['instructor_name']) ?></td>
                            <td><?= htmlspecialchars($en['course_title']) ?></td>
                            <td><?= date('d M Y', strtotime($en['enrolled_at'])) ?></td>
                            <td><?= $completedAt ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-info" role="progressbar"
                                         style="width: <?= $en['progress'] ?>%;" aria-valuenow="<?= $en['progress'] ?>"
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?= $en['progress'] ?>%
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= $badge ?>"><?= $displayStatus ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>