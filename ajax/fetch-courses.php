<?php
require_once '../config/config.php';

$search   = $_GET['search'] ?? '';
$price    = $_GET['price'] ?? '';
$rating   = $_GET['rating'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 6;
$offset   = ($page - 1) * $limit;

$where = "c.status = 'Published'";
$params = [];
$types = "";

// 🔍 Filters
if (!empty($search)) {
  $where .= " AND c.title LIKE ?";
  $params[] = "%$search%";
  $types .= "s";
}
if ($price === 'free') {
  $where .= " AND c.price = 0";
} elseif ($price === 'paid') {
  $where .= " AND c.price > 0";
}
if (!empty($rating)) {
  $where .= " AND (SELECT ROUND(AVG(r.rating),0) FROM reviews r WHERE r.course_id = c.id) >= ?";
  $params[] = (int)$rating;
  $types .= "i";
}

// Total Courses
$countQuery = "SELECT COUNT(*) FROM courses c WHERE $where";
$countStmt = $conn->prepare($countQuery);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalCourses = $countStmt->get_result()->fetch_row()[0] ?? 0;
$totalPages = ceil($totalCourses / $limit);

// Course Data
$query = "
  SELECT c.*, u.full_name,
    (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.course_id = c.id) AS avg_rating
  FROM courses c
  JOIN users u ON c.instructor_id = u.id
  WHERE $where
  ORDER BY c.id DESC
  LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$courses = $result->fetch_all(MYSQLI_ASSOC);

// 🔥 Render Course Cards
ob_start();
foreach ($courses as $c):
  $thumb = (!empty($c['thumbnail']) && file_exists('../' . ltrim($c['thumbnail'], './')))
    ? ltrim($c['thumbnail'], './')
    : 'assets/images/course-default.jpg';
  $rating = floatval($c['avg_rating']);
?>
  <div class="col-md-4">
    <div class="card course-card h-100 shadow">
      <img src="<?= $thumb ?>" alt="Thumbnail" class="card-img-top">
      <div class="card-body d-flex flex-column">
        <h5 class="card-title fw-bold">
          <a href="courses/view-course.php?id=<?= $c['id'] ?>" class="text-decoration-none text-primary">
            <?= htmlspecialchars($c['title']) ?>
          </a>
        </h5>
        <p class="mb-2 text-muted">
          <i class="fas fa-clock me-1"></i><?= $c['duration'] ?>
          &nbsp; • &nbsp;
          <i class="me-1"></i><?= $c['price'] == 0 ? '$ Free' : '$' . number_format($c['price'], 2) ?>
        </p>
        <p><span class="badge bg-warning text-dark">
          <i class="fas fa-layer-group me-1"></i><?= $c['category'] ?>
        </span></p>
<p class="rating-stars mb-2">
  <?= str_repeat('<i class="fas fa-star text-warning"></i>', floor($rating)) ?>
  <?= str_repeat('<i class="far fa-star text-warning"></i>', 5 - floor($rating)) ?>
  <small class="text-muted ms-1"><?= $rating ? $rating . '/5' : 'No Reviews' ?></small>
</p>

        <p class="mt-auto text-muted fw-semibold">
          <i class="fas fa-user me-1"></i><?= $c['full_name'] ?>
        </p>
        <div class="mt-3 text-center">
          <a href="courses/view-course.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-primary w-100">
            <i class="fas fa-play-circle me-1"></i> Enroll Now
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endforeach;
$courseHtml = ob_get_clean();

// 🔁 Pagination
ob_start();
if ($totalPages > 1): ?>
  <ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i == $page ? 'active' : '' ?>">
        <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
<?php endif;
$paginationHtml = ob_get_clean();

// 📤 Response
echo json_encode([
  'courses' => $courseHtml,
  'pagination' => $paginationHtml
]);