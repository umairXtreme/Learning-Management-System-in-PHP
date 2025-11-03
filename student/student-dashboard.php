<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit;
}

require_once '../config/config.php';

$student_id = $_SESSION['user_id'];
$student_name = "";

// 👤 Student Name
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($student_name);
$stmt->fetch();
$stmt->close();

// 📚 Total Enrolled Courses
$stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($enrolled_courses);
$stmt->fetch();
$stmt->close();

// ✅ Completed Courses
$stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND progress = 100");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($completed_courses);
$stmt->fetch();
$stmt->close();

// ⭐ Avg Rating Given
$stmt = $conn->prepare("SELECT AVG(rating) FROM reviews WHERE user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($avg_rating);
$stmt->fetch();
$stmt->close();
$avg_rating = $avg_rating ? round($avg_rating, 2) : 0;

// 💸 Total Paid (Success + Approved Only)
$stmt = $conn->prepare("SELECT SUM(p.amount)
    FROM payments p
    JOIN payment_proofs pp ON p.proof_id = pp.id
    WHERE p.user_id = ? AND p.status = 'Success' AND pp.status = 'approved'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($total_paid);
$stmt->fetch();
$stmt->close();
$total_paid = $total_paid ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
<div class="dashboard-container">
    <h2>🎓 Welcome back, <strong><?= htmlspecialchars($student_name) ?></strong>!</h2>

    <div class="row g-4">
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book-open-reader"></i> Enrolled Courses</h5>
                    <p class="card-text"><?= $enrolled_courses ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-check-circle"></i> Completed Courses</h5>
                    <p class="card-text"><?= $completed_courses ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-star"></i> Avg Rating Given</h5>
                    <p class="card-text"><?= $avg_rating ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-dark h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-wallet"></i> Total Paid</h5>
                    <p class="card-text">$ <?= number_format($total_paid, 2) ?></p>
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