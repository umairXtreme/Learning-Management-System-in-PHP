<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

$editMode = isset($_GET['id']);
$enrollment = [
    'user_id' => '',
    'course_id' => '',
    'status' => 'Active',
    'progress' => 0,
    'enrolled_at' => date('Y-m-d\TH:i'),
    'completed_at' => ''
];
$errors = [];

if ($editMode) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows < 1) {
        die("Enrollment not found.");
    }
    $enrollment = $result->fetch_assoc();
}

$users = $conn->query("SELECT id, full_name FROM users WHERE role IN ('student', 'instructor') OR role IS NULL OR role = ''");
$courses = $conn->query("SELECT id, title FROM courses WHERE status = 'Published'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $course_id = intval($_POST['course_id']);
    $status = $_POST['status'];
    $progress = intval($_POST['progress']);
    $enrolled_at = $_POST['enrolled_at'];
    $completed_at = $_POST['completed_at'];

    if ($progress === 100) {
        $status = 'Completed';
    }

    if ($user_id < 1 || $course_id < 1 || $progress < 0 || $progress > 100 || !in_array($status, ['Active', 'Completed', 'Dropped'])) {
        $errors[] = "Invalid input. Please verify fields.";
    }

    if (!$enrolled_at) $enrolled_at = date('Y-m-d H:i:s');

    if (empty($errors)) {
        if ($editMode) {
            $stmt = $conn->prepare("UPDATE enrollments SET user_id=?, course_id=?, status=?, progress=?, enrolled_at=?, completed_at=? WHERE id=?");
            $stmt->bind_param("iissssi", $user_id, $course_id, $status, $progress, $enrolled_at, $completed_at, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO enrollments (user_id, course_id, status, progress, enrolled_at, completed_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $user_id, $course_id, $status, $progress, $enrolled_at, $completed_at);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = $editMode ? "✅ Enrollment updated." : "✅ User enrolled successfully.";
            header("Location: manage-enrollments.php");
            exit();
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $editMode ? "Edit" : "Manual Enroll" ?> Enrollment - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-page-wrapper">
    <div class="container mt-5">
        <h2 class="mb-4"><i class="fas fa-edit me-2"></i><?= $editMode ? "Edit Enrollment" : "Manually Enroll User" ?></h2>
        <a href="manage-enrollments.php" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left"></i> Back to Enrollments</a>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Student</label>
                <select name="user_id" class="form-select" <?= $editMode ? 'disabled' : '' ?> required>
                    <option value="">Select User</option>
                    <?php while($user = $users->fetch_assoc()): ?>
                        <option value="<?= $user['id'] ?>" <?= ($editMode && $user['id'] == $enrollment['user_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['full_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if ($editMode): ?>
                    <input type="hidden" name="user_id" value="<?= $enrollment['user_id'] ?>">
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Course</label>
                <select name="course_id" class="form-select" required>
                    <option value="">Select Course</option>
                    <?php while ($c = $courses->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>" <?= $enrollment['course_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                    <option value="Active" <?= $enrollment['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Completed" <?= $enrollment['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="Dropped" <?= $enrollment['status'] === 'Dropped' ? 'selected' : '' ?>>Dropped</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Progress %</label>
                <input type="number" name="progress" class="form-control" min="0" max="100" value="<?= $enrollment['progress'] ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Enrollment Date</label>
                <input type="datetime-local" name="enrolled_at" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($enrollment['enrolled_at'])) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Completion Date (Optional)</label>
                <input type="datetime-local" name="completed_at" class="form-control" value="<?= $enrollment['completed_at'] ? date('Y-m-d\TH:i', strtotime($enrollment['completed_at'])) : '' ?>">
            </div>

            <button type="submit" class="btn btn-success">
                <i class="fas fa-check-circle me-1"></i><?= $editMode ? "Update Enrollment" : "Enroll User" ?>
            </button>
        </form>
    </div>
</div>
</body>
</html>