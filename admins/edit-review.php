<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-reviews.php");
    exit();
}

$review_id = intval($_GET['id']);
$stmt = $conn->prepare("
    SELECT reviews.*, courses.title AS course_title, users.full_name AS user_name
    FROM reviews
    JOIN courses ON courses.id = reviews.course_id
    JOIN users ON users.id = reviews.user_id
    WHERE reviews.id = ? AND reviews.is_deleted = 0
");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$result = $stmt->get_result();
$review = $result->fetch_assoc();

if (!$review) {
    $_SESSION['error'] = "❌ Review not found.";
    header("Location: manage-reviews.php");
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating']);
    $content = trim($_POST['content']);
    $status = in_array($_POST['status'], ['Approved', 'Pending', 'Rejected']) ? $_POST['status'] : 'Pending';

    if ($rating && $content) {
        $stmt = $conn->prepare("UPDATE reviews SET rating = ?, content = ?, status = ? WHERE id = ?");
        $stmt->bind_param("issi", $rating, $content, $status, $review_id);
        $stmt->execute();
        $_SESSION['success'] = "✅ Review updated successfully.";
        header("Location: manage-reviews.php");
        exit();
    } else {
        $error = "❌ Rating and comment are required.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Review - Admin</title>
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
        <h3><i class="fas fa-edit me-2"></i> Edit Review</h3>

        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label">Course</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($review['course_title']) ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label">User</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($review['user_name']) ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select" required>
                    <option value="">-- Select Rating --</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= $review['rating'] == $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Review Comments</label>
                <textarea name="content" class="form-control" rows="4" required><?= htmlspecialchars($review['content']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="Pending" <?= $review['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Approved" <?= $review['status'] === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Rejected" <?= $review['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Update Review</button>
            <a href="manage-reviews.php" class="btn btn-secondary ms-2"><i class="fas fa-arrow-left"></i> Cancel</a>
        </form>
    </div>
</div>

<?php if (!empty($error)): ?>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        Swal.fire({
            icon: 'error',
            title: 'Oops!',
            text: <?= json_encode($error) ?>,
            confirmButtonColor: '#d33'
        });
    });
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>