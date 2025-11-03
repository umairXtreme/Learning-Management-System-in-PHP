<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

$editMode = isset($_GET['id']);
$enrollment = [
    'user_id' => '',
    'course_id' => '',
    'status' => 'Pending',
    'progress' => 0,
    'enrolled_at' => date('Y-m-d\TH:i')
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

    $verify = $conn->prepare("SELECT 1 FROM courses WHERE id = ? AND instructor_id = ?");
    $verify->bind_param("ii", $enrollment['course_id'], $instructor_id);
    $verify->execute();
    $verify->store_result();
    if ($verify->num_rows === 0) {
        die("Unauthorized access.");
    }
}

$student_q = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$student_q->bind_param("i", $enrollment['user_id']);
$student_q->execute();
$student = $student_q->get_result()->fetch_assoc();

$course_q = $conn->prepare("SELECT title FROM courses WHERE id = ?");
$course_q->bind_param("i", $enrollment['course_id']);
$course_q->execute();
$course = $course_q->get_result()->fetch_assoc();

$isEditable = !in_array(strtolower($enrollment['status']), ['pending', 'dropped']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isEditable) {
    $progress = intval($_POST['progress']);
    if ($progress < 0 || $progress > 100) {
        $errors[] = "Progress must be between 0 and 100.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE enrollments SET progress=? WHERE id=?");
        $stmt->bind_param("ii", $progress, $id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "✅ Enrollment progress updated for course: " . htmlspecialchars($course['title']);
            header("Location: enrollments.php");
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
    <title>Edit Enrollment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- 🔗 Bootstrap + Icons + SweetAlert -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-container {
            margin-left: 250px;
            padding: 20px;
            display: flex;
            flex-direction: row;
            margin-top: 20px;
        }
        @media screen and (max-width: 768px) {
            .admin-container {
                margin-left: 0;
                flex-direction: column;
            }
            
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="admin-container">
<div class="container mt-5">
    <h2><i class="fas fa-edit"></i> Edit Enrollment</h2>
    <a href="enrollments.php" class="btn btn-secondary mb-4"><i class="fas fa-arrow-left"></i> Back to Enrollments</a>

    <?php if (!empty($errors)): ?>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: `<?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>`
            });
        });
    </script>
    <?php endif; ?>

    <?php if (!$isEditable): ?>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            Swal.fire({
                icon: 'warning',
                title: 'Not Editable',
                text: 'Progress cannot be updated while status is Pending or Dropped.',
            });
        });
    </script>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Student</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['full_name'] ?? 'Unknown') ?>" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">Course</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($course['title'] ?? 'Unknown') ?>" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($enrollment['status']) ?>" disabled>
        </div>

        <div class="mb-3">
            <label class="form-label">Enrollment Date</label>
            <input type="text" class="form-control" value="<?= date('Y-m-d H:i', strtotime($enrollment['enrolled_at'])) ?>" disabled>
        </div>

        <div class="mb-3">
            <label for="progress" class="form-label">Progress (%)</label>
            <input type="number" name="progress" id="progress" class="form-control" min="0" max="100"
                   value="<?= intval($enrollment['progress']) ?>" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>

        <button type="submit" class="btn btn-success" <?= !$isEditable ? 'disabled' : '' ?>>
            <i class="fas fa-check-circle"></i> Update Progress
        </button>
    </form>
</div>
    </div>
</body>
</html>