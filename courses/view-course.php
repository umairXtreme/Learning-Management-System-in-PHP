<?php
session_start();
require_once '../config/config.php';

$course_id = $_GET['id'] ?? null;
if (!$course_id || !is_numeric($course_id)) die("Invalid course ID");
$course_id = intval($course_id);

// 📦 Course
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) die("Course not found.");

// 🧑‍🏫 Instructors
$instructors = [];
$inst_stmt = $conn->prepare("
  SELECT u.full_name, u.id 
  FROM course_instructors ci 
  JOIN users u ON ci.instructor_id = u.id 
  WHERE ci.course_id = ?
");
$inst_stmt->bind_param("i", $course_id);
$inst_stmt->execute();
$res = $inst_stmt->get_result();
$instructor_ids = [];
while ($row = $res->fetch_assoc()) {
  $instructors[] = $row['full_name'];
  $instructor_ids[] = $row['id'];
}

// 📚 Lessons
$lessons = [];
$lesson_stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lesson_stmt->bind_param("i", $course_id);
$lesson_stmt->execute();
$res = $lesson_stmt->get_result();
while ($row = $res->fetch_assoc()) $lessons[] = $row;

// 🌟 Reviews
$review_stmt = $conn->prepare("
  SELECT r.rating, r.content, r.instructor_reply, u.full_name 
  FROM reviews r 
  JOIN users u ON r.user_id = u.id 
  WHERE r.course_id = ? AND r.is_deleted = 0 AND r.status = 'Approved'
");
$review_stmt->bind_param("i", $course_id);
$review_stmt->execute();
$reviews_result = $review_stmt->get_result();

$reviews = [];
$total_rating = 0;
$count = 0;
while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
    $total_rating += (int)$row['rating'];
    $count++;
}
$average_rating = $count ? round($total_rating / $count, 1) : null;

// 🧠 Enrollment logic
$is_enrolled = false;
$is_instructor_of_course = false;

if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    if (in_array($uid, $instructor_ids)) {
        $is_instructor_of_course = true;
    } else {
        $check_enroll_stmt = $conn->prepare("SELECT 1 FROM enrollments WHERE course_id = ? AND user_id = ?");
        $check_enroll_stmt->bind_param("ii", $course_id, $uid);
        $check_enroll_stmt->execute();
        $check_enroll_stmt->store_result();
        $is_enrolled = $check_enroll_stmt->num_rows > 0;
    }
}

$thumb = (!empty($course['thumbnail']) && file_exists('../' . ltrim($course['thumbnail'], './')))
  ? '../' . ltrim($course['thumbnail'], './')
  : '../assets/images/course-default.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($course['title']) ?> | Course</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="../assets/css/view-course.css">
</head>
<body>
<br>
<!-- 🖼️ Hero Banner -->
<div class="position-relative w-100" style="max-height: 720px; overflow: hidden;">
  <img src="<?= $thumb ?>" alt="Course Cover" class="w-100" style="object-fit: cover; height: 450px;">
  <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-flex align-items-center justify-content-center text-white text-center px-3">
    <div>
      <h1 class="fw-bold"><?= htmlspecialchars($course['title']) ?></h1>
      <p class="lead"><?= htmlspecialchars_decode($course['short_description']) ?></p>
      <div class="mt-3">
        <?php if ($average_rating): ?>
          <?= str_repeat('<i class="fas fa-star text-warning"></i>', floor($average_rating)) ?>
          <?= str_repeat('<i class="far fa-star text-warning"></i>', 5 - floor($average_rating)) ?>
          <span class="text-light ms-2"><?= $average_rating ?>/5</span>
        <?php else: ?>
          <span class="text-light">No ratings yet</span>
        <?php endif; ?>
        <span class="ms-3"><i class="fas fa-clock me-1"></i> <?= htmlspecialchars($course['duration']) ?></span>
      </div>
    </div>
  </div>
</div>

<div class="container py-5">
  <!-- 🧾 Description -->
  <div class="review-card mb-4">
    <h4><i class="fas fa-info-circle me-1"></i> About This Course</h4>
    <div><?= htmlspecialchars_decode($course['long_description']) ?></div>
  </div>

  <!-- 👨‍🏫 Instructors -->
  <div class="review-card mb-4">
    <p><strong><i class="fas fa-user me-1"></i> Instructor(s):</strong> <?= implode(', ', array_map('htmlspecialchars', $instructors)) ?></p>
    <p><strong><i class="fas fa-layer-group me-1"></i> Category:</strong> <?= htmlspecialchars($course['category']) ?></p>
    <p><strong><i class="fas fa-dollar-sign me-1"></i> Price:</strong> <?= $course['price'] == 0 ? 'Free' : '$' . number_format($course['price'], 2) ?></p>
    <p><i class="fas fa-check-circle text-success me-1"></i> <?= htmlspecialchars($course['status']) ?></p>

    <!-- 🎯 Enrollment -->
    <?php if (!isset($_SESSION['user_id'])): ?>
      <a href="../validate/login.php" class="btn btn-primary mt-3">Login to Enroll</a>
    <?php elseif ($is_instructor_of_course): ?>
      <a href="learn.php?id=<?= $course_id ?>" class="btn btn-outline-info mt-3">👨‍🏫 Start Learning</a>
    <?php elseif ($is_enrolled): ?>
      <a href="learn.php?id=<?= $course_id ?>" class="btn btn-success mt-3">📘 Continue Learning</a>
    <?php else: ?>
      <form id="enrollForm" action="enroll.php" method="POST" class="mt-3 d-inline-block">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <button type="submit" class="btn btn-primary">🚀 Enroll Now</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- 🎬 Lessons -->
  <div class="review-card mb-5">
    <h4><i class="fas fa-list-ul me-1"></i> Course Lessons</h4>
    <?php if (empty($lessons)): ?>
      <p class="text-muted">No lessons available yet.</p>
    <?php else: ?>
      <table class="table table-bordered table-hover mt-3 bg-white">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Title</th>
            <th>Type</th>
            <th>Preview</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lessons as $i => $l): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($l['title']) ?></td>
            <td><?= $l['is_demo'] ? 'Demo' : 'Locked' ?></td>
            <td>
              <?php if ($l['is_demo']): ?>
              <button class="btn btn-sm btn-outline-primary" onclick="showDemo('<?= $l['video_url'] ?>')"><i class="fas fa-eye"></i> Preview</button>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ⭐ Reviews -->
  <div class="review-card">
    <h4><i class="fas fa-comments me-1"></i> Student Reviews</h4>
    <?php if (empty($reviews)): ?>
      <p class="text-muted">No reviews yet for this course.</p>
    <?php else: ?>
      <?php foreach ($reviews as $rev): ?>
        <div class="border rounded p-3 mb-3 bg-light">
          <strong><?= htmlspecialchars($rev['full_name']) ?></strong>
          <div class="text-warning"><?= str_repeat('⭐', $rev['rating']) ?> <small>(<?= $rev['rating'] ?>/5)</small></div>
          <p><?= nl2br(htmlspecialchars($rev['content'])) ?></p>

          <?php if ($rev['instructor_reply']): ?>
            <div class="ps-3 pt-2 pb-2 border-start border-4 border-info bg-white rounded mt-2">
              <strong class="text-info"><i class="fas fa-chalkboard-teacher me-1"></i>Instructor Reply:</strong>
              <p class="mb-0"><?= nl2br(htmlspecialchars($rev['instructor_reply'])) ?></p>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- 🎬 Modal -->
<div class="modal fade" id="demoModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark text-white">
      <div class="modal-header border-0">
        <button class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="demoIframe" class="w-100" height="450" frameborder="0" allowfullscreen></iframe>
      </div>
    </div>
  </div>
</div>

<?php include_once '../includes/footer.php'; ?>
<script src="../assets/js/view-course.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>