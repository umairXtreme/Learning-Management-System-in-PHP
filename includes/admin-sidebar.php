<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Pending review count
$pending_reviews_result = $conn->query("SELECT COUNT(*) AS total FROM reviews WHERE status = 'Pending' AND is_deleted = 0");
$pending_reviews_row = $pending_reviews_result->fetch_assoc();
$pending_reviews = $pending_reviews_row['total'] ?? 0;
?>
<!-- Admin Sidebar CSS & JS Attachement -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha384-dyB6l9g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5z5g5" crossorigin="anonymous">
<link rel="stylesheet" href="../assets/css/sidebar.css">
<script src="../assets/js/sidebar.js" defer></script>
<!-- ☰ Sidebar Toggle for Mobile -->
<button id="sidebarToggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>

<!-- ✅ Sidebar matching .student-sidebar style -->
<nav class="student-sidebar">
  <div class="sidebar-header">
    <h4 class="sidebar-title">Admin Panel</h4>
  </div>
  <ul class="sidebar-menu">
    <li>
      <a href="admin-dashboard.php" class="<?= ($current_page == 'admin-dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Dashboard
      </a>
    </li>
    <li>
      <a href="manage-users.php" class="<?= ($current_page == 'manage-users.php') ? 'active' : '' ?>">
        <i class="fas fa-users"></i> Manage Users
      </a>
    </li>
    <li>
      <a href="manage-courses.php" class="<?= ($current_page == 'manage-courses.php') ? 'active' : '' ?>">
        <i class="fas fa-book"></i> Manage Courses
      </a>
    </li>
    <li>
      <a href="manage-enrollments.php" class="<?= ($current_page == 'manage-enrollments.php') ? 'active' : '' ?>">
        <i class="fas fa-user-graduate"></i> Manage Enrollments
      </a>
    </li>
    <li>
      <a href="manage-reviews.php" class="<?= ($current_page == 'manage-reviews.php') ? 'active' : '' ?>">
        <i class="fas fa-star"></i> Manage Reviews 
        <span class="badge bg-danger"><?= $pending_reviews ?></span>
      </a>
    </li>
    <li>
      <a href="manage-payments.php" class="<?= ($current_page == 'manage-payments.php') ? 'active' : '' ?>">
        <i class="fas fa-wallet"></i> Manage Payments
      </a>
    </li>
    <li>
      <a href="admin-settings.php" class="<?= ($current_page == 'admin-settings.php') ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> Profile Settings
      </a>
    </li>
    <li>
      <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>
  </ul>
</nav>