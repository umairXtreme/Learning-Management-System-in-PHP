<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- 🌐 FontAwesome + Sidebar CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="../assets/css/sidebar.css">
<script src="../assets/js/sidebar.js" defer></script>
<!--Toggle Button-->
<!-- ☰ Hamburger Toggle -->
<button id="sidebarToggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>
<!-- 📚 Student Sidebar -->
<nav class="student-sidebar">
  <div class="sidebar-header">
    <h4 class="sidebar-title">Student Panel</h4>
  </div>
  <ul class="sidebar-menu">
    <li>
      <a href="student-dashboard.php" class="<?= $current_page == 'student-dashboard.php' ? 'active' : '' ?>">
        <i class="fas fa-home"></i> Dashboard
      </a>
    </li>
    <li>
      <a href="my-courses.php" class="<?= $current_page == 'my-courses.php' ? 'active' : '' ?>">
        <i class="fas fa-book"></i> My Courses
      </a>
    </li>
    <li>
      <a href="enrollment-history.php" class="<?= $current_page == 'enrollment-history.php' ? 'active' : '' ?>">
        <i class="fas fa-history"></i> Enrollment History
      </a>
    </li>
    <li>
      <a href="my-reviews.php" class="<?= $current_page == 'my-reviews.php' ? 'active' : '' ?>">
        <i class="fas fa-star"></i> My Reviews
      </a>
    </li>
    <li>
      <a href="payment-history.php" class="<?= $current_page == 'payment-history.php' ? 'active' : '' ?>">
        <i class="fas fa-wallet"></i> Payments History
      </a>
    </li>
    <li>
      <a href="student-profile.php" class="<?= $current_page == 'student-profile.php' ? 'active' : '' ?>">
        <i class="fas fa-user-cog"></i> Profile Settings
      </a>
    </li>
    <li>
      <a href="../validate/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </li>
  </ul>
</nav>
<!-- 📚 End of Student Sidebar -->