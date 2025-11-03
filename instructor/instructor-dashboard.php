<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit;
}

require_once '../config/config.php';

$instructor_id = $_SESSION['user_id'];

// 🧑‍🏫 Instructor Name
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($instructor_name);
$stmt->fetch();
$stmt->close();

// 📊 Total Courses
$stmt = $conn->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($total_courses);
$stmt->fetch();
$stmt->close();

// 👨‍🎓 Total Enrollments
$sql = "SELECT COUNT(*) FROM enrollments JOIN courses ON enrollments.course_id = courses.id WHERE courses.instructor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($total_students);
$stmt->fetch();
$stmt->close();

// ⭐ Average Rating
$sql = "SELECT AVG(reviews.rating) FROM reviews JOIN courses ON reviews.course_id = courses.id WHERE courses.instructor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($avg_rating);
$stmt->fetch();
$stmt->close();
$avg_rating = $avg_rating ? round($avg_rating, 2) : 0;

// 💰 Total Earnings
$sql = "SELECT SUM(payments.amount) FROM payments JOIN courses ON payments.course_id = courses.id WHERE courses.instructor_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$stmt->bind_result($total_earnings);
$stmt->fetch();
$stmt->close();
$total_earnings = $total_earnings ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Instructor Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Styles -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-container">
    <h2>👋 Welcome back, <strong><?= htmlspecialchars($instructor_name) ?></strong>!</h2>

    <div class="row g-4 mt-4">
      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-primary h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-book"></i> Total Courses</h5>
            <p class="card-text fs-3"><?= $total_courses ?></p>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-success h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-user-graduate"></i> Enrollments</h5>
            <p class="card-text fs-3"><?= $total_students ?></p>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-warning h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-star"></i> Avg. Rating</h5>
            <p class="card-text fs-3"><?= $avg_rating ?></p>
          </div>
        </div>
      </div>

      <div class="col-md-3 col-sm-6">
        <div class="card text-white bg-info h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Earnings</h5>
            <p class="card-text fs-3">$<?= number_format($total_earnings, 2) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>