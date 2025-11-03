<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine user login status and role
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']);
$username   = $_SESSION['username'] ?? '';
$role       = $_SESSION['role'] ?? '';
$dashboardLink = '#';

switch ($role) {
    case 'admin':
        $dashboardLink = '/fyp/admins/admin-dashboard.php';
        break;
    case 'student':
        $dashboardLink = '/fyp/student/student-dashboard.php';
        break;
    case 'instructor':
        $dashboardLink = '/fyp/instructor/instructor-dashboard.php';
        break;
}
?>

<!-- ✅ Sticky + Responsive Navbar with Fixed Routing -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top border-bottom">
  <div class="container">

    <!-- 🔰 Brand Logo -->
    <a class="navbar-brand" href="/fyp/index.php">
      <img src="https://res.cloudinary.com/dv9mwju2y/image/upload/v1741767432/Logowhitebackground_fdnd94.png" alt="CyberLMS" style="height: 50px;">
    </a>

    <!-- 📱 Mobile Toggle -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- 🧭 Links + Buttons -->
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item">
          <a class="nav-link fw-semibold" href="/fyp/courses.php">
            <i class="fas fa-book-open me-1"></i>View All Courses
          </a>
        </li>

        <?php if ($isLoggedIn): ?>
          <li class="nav-item">
            <a class="nav-link fw-semibold" href="<?= $dashboardLink ?>">
              <i class="fas fa-tachometer-alt me-1"></i> Dashboard
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- 🔐 Auth Buttons -->
      <?php if (!$isLoggedIn): ?>
        <div class="d-flex ms-lg-3 mt-3 mt-lg-0">
          <a href="/fyp/validate/login.php" class="btn btn-outline-primary me-2 px-4 fw-semibold">
            <i class="fas fa-sign-in-alt me-1"></i> Login
          </a>
          <a href="/fyp/validate/register.php" class="btn btn-primary px-4 fw-semibold">
            <i class="fas fa-user-plus me-1"></i> Register
          </a>
        </div>
      <?php else: ?>
        <div class="dropdown ms-lg-3 mt-3 mt-lg-0">
          <button class="btn btn-outline-dark dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($username) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/fyp/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
</nav>
<!-- ✅ End of Navbar -->