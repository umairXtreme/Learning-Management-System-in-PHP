<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

// Fetch Admin Name
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$stmt->bind_result($admin_name);
$stmt->fetch();
$stmt->close();

// Fetch platform stats
$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$total_courses = $conn->query("SELECT COUNT(*) AS total FROM courses")->fetch_assoc()['total'];
$total_enrollments = $conn->query("SELECT COUNT(*) AS total FROM enrollments")->fetch_assoc()['total'];
$total_revenue = $conn->query("SELECT SUM(amount) AS total FROM payments")->fetch_assoc()['total'] ?? 0;

// Fetch pending instructors
$pending_instructors = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'instructor' AND status = 'pending'")->fetch_assoc()['total'];

// Fetch pending reviews
$pending_reviews = $conn->query("SELECT COUNT(*) AS total FROM reviews WHERE status = 'pending'")->fetch_assoc()['total'];

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-container">
    <h2>👋 Welcome back, <strong><?= htmlspecialchars($admin_name) ?></strong>!</h2>

    <div class="row g-4">
        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-users"></i> Total Users</h5>
                    <p class="card-text"><?= $total_users ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book"></i> Total Courses</h5>
                    <p class="card-text"><?= $total_courses ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-user-check"></i> Total Enrollments</h5>
                    <p class="card-text"><?= $total_enrollments ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-dollar-sign"></i> Total Revenue</h5>
                    <p class="card-text">$<?= number_format($total_revenue, 2) ?></p>
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