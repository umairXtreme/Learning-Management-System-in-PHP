<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 🔐 Delete course logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course_id'])) {
    $delete_id = intval($_POST['delete_course_id']);
    $stmt = $conn->prepare("SELECT course_id FROM course_instructors WHERE course_id = ? AND instructor_id = ?");
    $stmt->bind_param("ii", $delete_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $conn->query("DELETE FROM courses WHERE id = $delete_id");
        $_SESSION['message'] = ['success', "✅ Course #$delete_id has been permanently deleted."];
    } else {
        $_SESSION['message'] = ['error', "❌ You are not authorized to delete this course."];
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 🧾 Published Courses
$published_stmt = $conn->prepare("
    SELECT c.id, c.title, c.category, c.price, c.status, c.created_at
    FROM courses c
    INNER JOIN course_instructors ci ON ci.course_id = c.id
    WHERE ci.instructor_id = ? AND c.status = 'Published'
    ORDER BY c.created_at DESC
");
$published_stmt->bind_param("i", $user_id);
$published_stmt->execute();
$published_courses = $published_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 📝 Draft Courses
$draft_stmt = $conn->prepare("
    SELECT c.id, c.title, c.category, c.price, c.status, c.created_at
    FROM courses c
    INNER JOIN course_instructors ci ON ci.course_id = c.id
    WHERE ci.instructor_id = ? AND c.status = 'Draft'
    ORDER BY c.created_at DESC
");
$draft_stmt->bind_param("i", $user_id);
$draft_stmt->execute();
$draft_courses = $draft_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage My Courses</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS & Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

 <style>
  body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }
  .page-title {
    margin: 30px 0 10px;
  }
  .btn-action {
    margin-right: 10px;
    margin-bottom: 8px;
  }
  .section-title {
    margin-top: 40px;
  }
  #courseFilter {
    max-width: 300px;
    margin-bottom: 20px;
  }
.form-control {
    border-radius: 6px;
    border: 1px solid #0d6efd;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: border 0.2s, box-shadow 0.2s;
}

#userSearch:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
}
  /* 📱 Mobile spacing for buttons inside table */
  @media (max-width: 768px) {
    td .btn {
      margin-bottom: 6px !important;
      display: block;
      width: 100%;
    }
    .btn-action {
      display: block;
      width: 100%;
    }
.form-control {
    border-radius: 6px;
    border: 1px solid #0d6efd;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: border 0.2s, box-shadow 0.2s;
}

#userSearch:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
}
  }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-container">
    <h2 class="page-title">📚 Manage Your Courses</h2>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <a href="add-course.php" class="btn btn-primary btn-action">
          <i class="fas fa-plus-circle"></i> Add New Course
        </a>
        <a href="../courses.php" class="btn btn-success btn-action">
          <i class="fas fa-play-circle"></i> Start Learning
        </a>
      </div>
      <input type="text" id="courseFilter" class="form-control" placeholder="🔍 Filter courses...">
    </div>

    <!-- ✅ Published Courses -->
    <h4 class="section-title"><i class="fas fa-globe"></i> Published Courses</h4>
    <?php if (!empty($published_courses)): ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle course-table">
          <thead class="table-dark">
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Price</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($published_courses as $course): ?>
            <tr>
              <td><?= htmlspecialchars($course['title']) ?></td>
              <td><?= htmlspecialchars($course['category']) ?></td>
              <td><?= $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2) ?></td>
              <td><span class="badge bg-success"><?= $course['status'] ?></span></td>
              <td><?= date('d M Y', strtotime($course['created_at'])) ?></td>
              <td>
                <a href="edit-course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-warning">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <a href="../courses/view-course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-info">
                  <i class="fas fa-eye"></i> View
                </a>
                <form method="POST" class="d-inline delete-form" data-id="<?= $course['id'] ?>">
                  <input type="hidden" name="delete_course_id" value="<?= $course['id'] ?>">
                  <button type="button" class="btn btn-sm btn-danger delete-btn">
                    <i class="fas fa-trash-alt"></i> Delete
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-muted">No published courses yet.</p>
    <?php endif; ?>

    <!-- 📝 Draft Courses -->
    <h4 class="section-title"><i class="fas fa-pencil-alt"></i> Draft Courses</h4>
    <?php if (!empty($draft_courses)): ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle course-table">
          <thead class="table-dark">
            <tr>
              <th>Title</th>
              <th>Category</th>
              <th>Price</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($draft_courses as $course): ?>
            <tr>
              <td><?= htmlspecialchars($course['title']) ?></td>
              <td><?= htmlspecialchars($course['category']) ?></td>
              <td><?= $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $course['status'] ?></span></td>
              <td><?= date('d M Y', strtotime($course['created_at'])) ?></td>
              <td>
                <a href="edit-course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-warning">
                  <i class="fas fa-edit"></i> Edit
                </a>
                <form method="POST" class="d-inline delete-form" data-id="<?= $course['id'] ?>">
                  <input type="hidden" name="delete_course_id" value="<?= $course['id'] ?>">
                  <button type="button" class="btn btn-sm btn-danger delete-btn">
                    <i class="fas fa-trash-alt"></i> Delete
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-muted">No draft courses yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // 🔍 Filter logic
  document.getElementById('courseFilter').addEventListener('keyup', function () {
    const keyword = this.value.toLowerCase();
    document.querySelectorAll('.course-table tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
    });
  });

  // ❌ Confirm deletion via SweetAlert2
  document.querySelectorAll(".delete-btn").forEach(btn => {
    btn.addEventListener("click", function () {
      const form = this.closest("form");
      const id = form.dataset.id;

      Swal.fire({
        title: 'Are you absolutely sure?',
        text: `This will permanently delete course #${id}. This action is irreversible and there is no backup.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        customClass: {
          confirmButton: 'btn btn-danger',
          cancelButton: 'btn btn-secondary'
        },
        buttonsStyling: false
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  // ✅ Toast notification
  <?php if (!empty($_SESSION['message'])):
    $msg = $_SESSION['message'];
    unset($_SESSION['message']);
  ?>
  document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: '<?= $msg[0] === 'success' ? 'success' : 'error' ?>',
      title: '<?= addslashes($msg[1]) ?>',
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true
    });
  });
  <?php endif; ?>
</script>
</body>
</html>