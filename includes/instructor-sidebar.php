<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Load Shared Sidebar Styles and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="../assets/css/sidebar.css">
<script src="../assets/js/sidebar.js" defer></script>

<!-- ☰ Mobile Toggle Button -->
<button id="sidebarToggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>

<!-- ✅ Unified Sidebar Structure -->
<nav class="student-sidebar">
  <div class="sidebar-header">
    <h4 class="sidebar-title">Instructor Panel</h4>
  </div>
  <ul class="sidebar-menu">
    <li>
      <a href="instructor-dashboard.php" class="<?= ($current_page == 'instructor-dashboard.php') ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Dashboard
      </a>
    </li>
    <li>
      <a href="my-courses.php" class="<?= ($current_page == 'my-courses.php') ? 'active' : '' ?>">
        <i class="fas fa-book"></i> Manage Courses
      </a>
    </li>
    <li>
      <a href="enrollments.php" class="<?= ($current_page == 'enrollments.php') ? 'active' : '' ?>">
        <i class="fas fa-user-graduate"></i> Manage Enrollments
      </a>
    </li>
    <li>
      <a href="manage-reviews.php" class="<?= ($current_page == 'manage-reviews.php') ? 'active' : '' ?>">
        <i class="fas fa-star"></i> Manage Reviews
      </a>
    </li>
    <li>
      <a href="manage-payments.php" class="<?= ($current_page == 'manage-payments.php') ? 'active' : '' ?>">
        <i class="fas fa-wallet"></i> Manage Payments
      </a>
    </li>
    <li>
      <a href="instructor-profile.php" class="<?= ($current_page == 'instructor-profile.php') ? 'active' : '' ?>">
        <i class="fas fa-cog"></i> Profile Settings
      </a>
    </li>
    <li>
      <a href="../validate/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>
  </ul>
</nav>