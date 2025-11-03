<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberLMS - BC190203051</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

<!-- Header -->
<?php include 'includes/header.php'; ?>

<!-- 🌟 Hero Section -->
<section class="hero-section">
  <div class="container text-center">
    <div class="hero-glass">
      <h1 class="display-5 fw-bold text-white">Unlock Your Potential with Online Learning</h1>
      <p class="lead text-white">Explore thousands of courses and boost your skills with top instructors.</p>
    </div>
  </div>
</section>

<!-- 🚀 Features Section -->
<section class="py-5 bg-white">
  <div class="container">
    <div class="row g-4 text-center">
      <div class="col-md-4">
        <div class="p-4 shadow-sm rounded h-100 bg-light">
          <i class="fas fa-lock fa-2x text-primary mb-3"></i>
          <h5>3D Secure Checkout</h5>
          <p class="text-muted mb-0">Advanced protection on every transaction.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 shadow-sm rounded h-100 bg-light">
          <i class="fas fa-headset fa-2x text-success mb-3"></i>
          <h5>24/7 Support</h5>
          <p class="text-muted mb-0">Get help anytime, anywhere.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="p-4 shadow-sm rounded h-100 bg-light">
          <i class="fas fa-book-open fa-2x text-warning mb-3"></i>
          <h5>Quality Courses</h5>
          <p class="text-muted mb-0">Learn from certified top instructors.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 📚 Courses Section -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="fw-bold">Explore Our Courses</h2>
      <p class="text-muted">Search by course title to find what you're looking for</p>
    </div>

    <!-- Search -->
    <div class="row mb-4 justify-content-center">
      <div class="col-md-6">
        <input type="text" id="courseSearch" class="form-control shadow-sm" placeholder="Type course name...">
      </div>
    </div>

    <!-- Loader -->
    <div id="searchLoader" class="text-center d-none mb-3">
      <div class="spinner-border text-primary"></div>
    </div>

    <!-- Course Cards -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4" id="coursesContainer">
      <!-- AJAX will load here -->
    </div>

    <!-- View All -->
    <div class="text-center mt-4">
      <a href="courses.php" class="btn btn-primary btn-md px-4">
        <i class="fas fa-arrow-right me-1"></i> View All Courses
      </a>
    </div>
  </div>
</section>

<!-- Footer -->
<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('courseSearch');
  const loader = document.getElementById('searchLoader');
  const coursesContainer = document.getElementById('coursesContainer');

  let searchTimeout;

  function fetchCourses(query = '') {
    loader.classList.remove('d-none');
    fetch(`ajax/search-courses.php?search=${encodeURIComponent(query)}`)
      .then(res => res.text())
      .then(html => {
        coursesContainer.innerHTML = html;
        loader.classList.add('d-none');
      })
      .catch(err => {
        loader.classList.add('d-none');
        coursesContainer.innerHTML = '<div class="col-12 text-center text-danger">Failed to load courses.</div>';
      });
  }

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      fetchCourses(searchInput.value.trim());
    }, 400);
  });

  // Initial load
  fetchCourses();
});
</script>
</body>
</html>