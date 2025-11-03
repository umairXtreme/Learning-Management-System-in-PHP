<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

$sql = "SELECT r.id, r.rating, r.content, r.status, c.title AS course_title, u.full_name
        FROM reviews r
        JOIN courses c ON r.course_id = c.id
        JOIN users u ON r.user_id = u.id
        WHERE r.is_deleted = 0 AND c.instructor_id = ?
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reviews - Instructor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Icons + SweetAlert -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .table td {
            vertical-align: middle;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 20px;
        }
        @media screen and (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
                padding: 10px;
            }
            
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="admin-page-wrapper mt-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="fas fa-comments"></i> Manage Course Reviews</h3>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Course</th>
                        <th>Student</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['course_title']) ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= str_repeat("⭐", $r['rating']) . str_repeat("☆", 5 - $r['rating']) ?></td>
                        <td>
                            <?php
                            $status = strtolower(trim($r['status'] ?? 'Pending'));
                            $badge = match ($status) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'pending' => 'warning',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($status) ?></span>
                        </td>
                        <td>
                            <a href="view-review.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($reviews)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No reviews found for your courses.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
                </div>

<?php if (!empty($_SESSION['success'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
        toast: true,
        icon: 'success',
        position: 'top-end',
        title: <?= json_encode(strip_tags($_SESSION['success'])) ?>,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
});
</script>
<?php unset($_SESSION['success']); ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>