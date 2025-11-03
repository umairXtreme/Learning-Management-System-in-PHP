<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_single'])) {
    $courseId = intval($_POST['delete_single']);
    $conn->query("DELETE FROM courses WHERE id = $courseId");
    $_SESSION['message'] = ['success', "Course #$courseId has been deleted successfully."];
}

$courses = $conn->query("
    SELECT 
        c.id, 
        c.title, 
        c.price, 
        c.status, 
        c.updated_at, 
        c.rating, 
        c.category, 
        GROUP_CONCAT(COALESCE(u.full_name, u.username) SEPARATOR ', ') AS instructors
    FROM courses c
    LEFT JOIN course_instructors ci ON ci.course_id = c.id
    LEFT JOIN users u ON ci.instructor_id = u.id AND u.role = 'instructor'
    GROUP BY c.id
    ORDER BY c.updated_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Courses - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
     <style>
        /* 🌐 Base Layout */
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
<?php include '../includes/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Courses</h2>
        <a href="add-course.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Course</a>
    </div>

    <!-- Search Bar -->
    <div class="mb-3">
        <input type="text" id="courseSearch" class="form-control" placeholder="🔍 Search by title, instructor, category...">
    </div>

    <form method="POST">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Instructor</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($course = $courses->fetch_assoc()): ?>
                        <tr>
                            <td><?= $course['id']; ?></td>
                            <td><?= htmlspecialchars($course['title']); ?></td>
                            <td><?= htmlspecialchars($course['instructors']); ?></td>
                            <td><?= htmlspecialchars($course['category']); ?></td>
                            <td><?= $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2); ?></td>
                            <td>
                                <?php
                                $stars = round($course['rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $stars ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-muted"></i>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($course['status'] === 'Published') ? 'success' : (($course['status'] === 'Draft') ? 'secondary' : 'danger'); ?>">
                                    <?= ucfirst(htmlspecialchars($course['status'])); ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($course['updated_at'])); ?></td>
                            <td>
                                <a href="edit-course.php?id=<?= $course['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ This action is irreversible. Do you really want to delete this course?');">
                                    <input type="hidden" name="delete_single" value="<?= $course['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </form>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live Search
document.getElementById("courseSearch").addEventListener("keyup", function () {
    const query = this.value.toLowerCase();
    document.querySelectorAll("table tbody tr").forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
    });
});
</script>

<?php if (isset($_SESSION['message'])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: '<?= $_SESSION['message'][0] === 'success' ? 'success' : 'error' ?>',
            title: '<?= $_SESSION['message'][1] ?>',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    });
</script>
<?php unset($_SESSION['message']); ?>
<?php endif; ?>
</body>
</html>