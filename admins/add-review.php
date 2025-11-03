<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

// ✅ Handle AJAX fetch for enrolled users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_enrolled']) && $_POST['fetch_enrolled'] == '1') {
    $course_id = intval($_POST['course_id'] ?? 0);
    if ($course_id > 0) {
        $stmt = $conn->prepare("
            SELECT DISTINCT u.id, u.full_name
            FROM enrollments e
            JOIN users u ON u.id = e.user_id
            WHERE e.course_id = ? AND e.is_deleted = 0
        ");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            echo '<option value="">-- Select Student --</option>';
            while ($row = $res->fetch_assoc()) {
                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['full_name']) . '</option>';
            }
        } else {
            echo '<option value="">-- No Enrolled Students Found --</option>';
        }
    }
    exit(); // return early for AJAX
}

$error = null;
$reviewExists = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['fetch_enrolled'])) {
    $course_id = intval($_POST['course_id']);
    $user_id = intval($_POST['user_id']);
    $rating = intval($_POST['rating']);
    $content = trim($_POST['content']);
    $status = in_array($_POST['status'], ['Approved', 'Pending', 'Rejected']) ? $_POST['status'] : 'Pending';

    $checkStmt = $conn->prepare("SELECT id FROM reviews WHERE course_id = ? AND user_id = ? AND is_deleted = 0");
    $checkStmt->bind_param("ii", $course_id, $user_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $error = "⚠️ A review already exists for this student and course.";
        $reviewExists = true;
    } else {
        if ($course_id && $user_id && $rating && $content) {
            $stmt = $conn->prepare("INSERT INTO reviews (course_id, user_id, rating, content, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiiss", $course_id, $user_id, $rating, $content, $status);
            $stmt->execute();
            $_SESSION['success'] = "✅ Review added successfully.";
            header("Location: manage-reviews.php");
            exit();
        } else {
            $error = "❌ All fields are required.";
        }
    }
}

$courses = $conn->query("SELECT id, title FROM courses ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Review - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        @media (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-page-wrapper">
    <div class="container">
        <h3><i class="fas fa-plus-circle me-2"></i> Add Review</h3>

        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label for="course_id" class="form-label">Select Course</label>
                <select id="course_id" name="course_id" class="form-select" required>
                    <option value="">-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="user_id" class="form-label">Select Enrolled Student</label>
                <select id="user_id" name="user_id" class="form-select" required>
                    <option value="">-- Select Student --</option>
                </select>
            </div>

            <div id="existingReview" class="alert alert-info d-none"></div>

            <div class="mb-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select" required>
                    <option value="">-- Select Rating --</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Review Comments</label>
                <textarea name="content" class="form-control" rows="4" placeholder="Write something..." required></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-1"></i> Submit Review</button>
        </form>
    </div>
</div>

<!-- 🔥 SweetAlert2 Notification -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    <?php if (!empty($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Oops!',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#d33'
        });
    <?php endif; ?>
});
</script>

<script>
document.getElementById('course_id').addEventListener('change', function () {
    const courseId = this.value;
    const userSelect = document.getElementById('user_id');
    const reviewBox = document.getElementById('existingReview');

    if (!courseId) return;

    fetch('add-review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ course_id: courseId, fetch_enrolled: '1' })
    })
    .then(res => res.text())
    .then(options => {
        userSelect.innerHTML = options;
        reviewBox.classList.add('d-none');
        reviewBox.innerHTML = '';
    });
});
</script>
<script src="../assets/js/add-review.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>