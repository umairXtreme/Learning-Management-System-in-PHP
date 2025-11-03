<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// === Filters ===
$where = "r.user_id = ? AND r.is_deleted = 0";
$params = [$student_id];
$types = "i";

if (!empty($_GET['course'])) {
    $where .= " AND c.title LIKE ?";
    $params[] = "%" . $_GET['course'] . "%";
    $types .= "s";
}
if (!empty($_GET['rating'])) {
    $where .= " AND r.rating = ?";
    $params[] = intval($_GET['rating']);
    $types .= "i";
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(r.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= "ss";
}

// === Fetch Existing Reviews ===
$sql = "
    SELECT r.id, r.rating, r.status, 
           c.title AS course_title, 
           i.full_name AS instructor_name
    FROM reviews r
    JOIN courses c ON r.course_id = c.id
    JOIN users i ON c.instructor_id = i.id
    WHERE $where
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// === Fetch Enrolled Courses without Review ===
$pendingCourses = $conn->query("
    SELECT c.id, c.title, i.full_name AS instructor_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users i ON c.instructor_id = i.id
    WHERE e.user_id = $student_id
      AND NOT EXISTS (
        SELECT 1 FROM reviews r WHERE r.user_id = $student_id AND r.course_id = c.id AND r.is_deleted = 0
      )
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-control {
            border-radius: 6px;
            border: 1px solid #0d6efd;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
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
            <h3><i class="fas fa-comments"></i> My Reviews</h3>
        </div>

        <!-- 🔍 Filters -->
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" name="course" class="form-control" placeholder="Course Title" value="<?= $_GET['course'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <select name="rating" class="form-select form-control">
                    <option value="">Rating</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= ($_GET['rating'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?> Star</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="from" class="form-control" value="<?= $_GET['from'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="to" class="form-control" value="<?= $_GET['to'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
            </div>
        </form>

        <!-- ✍️ Enrolled Courses Needing Review -->
        <?php if (!empty($pendingCourses)): ?>
        <div class="mb-4">
            <h5><i class="fas fa-pen-to-square"></i> Leave a Review</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Instructor</th>
                            <th>Course</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingCourses as $course): ?>
                            <tr>
                                <td><?= htmlspecialchars($course['instructor_name']) ?></td>
                                <td><?= htmlspecialchars($course['title']) ?></td>
                                <td>
                                    <a href="add-review.php?course_id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-comment-dots me-1"></i> Review Now
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- 📋 Review Table -->
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Instructor</th>
                        <th>Course</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reviews)): ?>
                        <tr><td colspan="5" class="text-center">No reviews found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reviews as $r): ?>
                            <?php
                                $status = strtolower($r['status'] ?? 'pending');
                                $badge = match ($status) {
                                    'approved' => 'success',
                                    'rejected' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary'
                                };
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['instructor_name']) ?></td>
                                <td><?= htmlspecialchars($r['course_title']) ?></td>
                                <td><?= str_repeat("⭐", $r['rating']) . str_repeat("☆", 5 - $r['rating']) ?></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($status) ?></span></td>
                                <td class="text-center">
                                    <a href="edit-review.php?id=<?= $r['id'] ?>" class="text-primary" title="View/Edit Review">
                                        <i class="fas fa-eye"></i>
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
<?php if (!empty($_SESSION['toast'])):
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
?>
<script>
Swal.fire({
    toast: true,
    position: 'top-end',
    icon: '<?= $toast['type'] ?>',
    title: '<?= $toast['message'] ?>',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>