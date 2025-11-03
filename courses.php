<?php
session_start();
require_once './config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Courses</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="assets/css/courses.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container py-5">
  <h2 class="text-center mb-4"><i class="fas fa-book-open me-2"></i> Browse Courses</h2>

  <!-- 🔍 Filters -->
  <form id="courseFilters" class="row g-3 justify-content-center mb-4 text-center">
    <div class="col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search by course title...">
    </div>
    <div class="col-md-2">
      <select name="price" class="form-select">
        <option value="">All Prices</option>
        <option value="free">Free</option>
        <option value="paid">Paid</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="rating" class="form-select">
        <option value="">All Ratings</option>
        <?php for ($i = 5; $i >= 1; $i--): ?>
          <option value="<?= $i ?>"><?= $i ?> Stars & Up</option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
    </div>
  </form>

  <!-- 📦 Results -->
  <div id="coursesGrid" class="row g-4"></div>
  <nav id="paginationLinks" class="mt-4 text-center"></nav>
</div>

<footer>
  <?php include_once 'includes/footer.php'; ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/courses.js"></script>
</body>
</html>