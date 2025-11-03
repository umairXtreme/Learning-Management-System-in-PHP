<?php
session_start();
require_once '../config/config.php';

$course_id = $_GET['id'] ?? null;
$lesson_id = $_GET['lesson'] ?? null;

if (!$course_id || !is_numeric($course_id)) {
    header("Location: ../404.php");
    exit;
}
$course_id = intval($course_id);

// 🧠 Fetch Course
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) {
    header("Location: ../404.php");
    exit;
}

// 🔐 Current User
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: ../404.php");
    exit;
}

// 👨‍🏫 Instructors
$inst_stmt = $conn->prepare("SELECT u.id, u.full_name, u.email, u.phone FROM course_instructors ci JOIN users u ON ci.instructor_id = u.id WHERE ci.course_id = ?");
$inst_stmt->bind_param("i", $course_id);
$inst_stmt->execute();
$instructors = $inst_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$instructor_ids = array_column($instructors, 'id');
$is_instructor = in_array($user_id, $instructor_ids);

// ✅ Enrollment
$enrollment_id = null;
$enrollment_db_id = null;
$is_enrolled = false;

if (!$is_instructor) {
    $enroll_stmt = $conn->prepare("SELECT id FROM enrollments WHERE course_id = ? AND user_id = ?");
    $enroll_stmt->bind_param("ii", $course_id, $user_id);
    $enroll_stmt->execute();
    $enroll = $enroll_stmt->get_result()->fetch_assoc();
    if ($enroll && isset($enroll['id'])) {
        $is_enrolled = true;
        $enrollment_db_id = $enroll['id'];
        // fetch actual public enrollment_id
        $eid_stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE id = ?");
        $eid_stmt->bind_param("i", $enrollment_db_id);
        $eid_stmt->execute();
        $eid_res = $eid_stmt->get_result()->fetch_assoc();
        if ($eid_res && isset($eid_res['enrollment_id'])) {
            $enrollment_id = $eid_res['enrollment_id'];
        }
    }
}

// 🛡️ Access Protection
if (!$is_enrolled && !$is_instructor) {
    header("Location: ../404.php");
    exit;
}

// 📚 Lessons
$lessons_stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY id ASC");
$lessons_stmt->bind_param("i", $course_id);
$lessons_stmt->execute();
$lessons = $lessons_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (empty($lessons)) die("No lessons available.");

// 📌 Current Lesson
$current_lesson = null;
if ($lesson_id) {
    foreach ($lessons as $l) {
        if ($l['id'] == $lesson_id) {
            $current_lesson = $l;
            break;
        }
    }
}
if (!$current_lesson) {
    $current_lesson = $lessons[0];
    $lesson_id = $current_lesson['id'];
}

// 📈 Progress & Completion
$is_completed = false;
$progress_percent = 0;

if ($enrollment_db_id) {
    $track_stmt = $conn->prepare("SELECT is_completed FROM user_lessons WHERE enrollment_id = ? AND lesson_id = ?");
    $track_stmt->bind_param("ii", $enrollment_db_id, $lesson_id);
    $track_stmt->execute();
    $track = $track_stmt->get_result()->fetch_assoc();

    if (!$track) {
        $ins_track = $conn->prepare("INSERT INTO user_lessons (enrollment_id, lesson_id, started_at, last_watched_at, progress_percent, is_completed) VALUES (?, ?, NOW(), NOW(), 0, 0)");
        $ins_track->bind_param("ii", $enrollment_db_id, $lesson_id);
        $ins_track->execute();
    } else {
        $is_completed = $track['is_completed'] == 1;
        $upd_watch = $conn->prepare("UPDATE user_lessons SET last_watched_at = NOW() WHERE enrollment_id = ? AND lesson_id = ?");
        $upd_watch->bind_param("ii", $enrollment_db_id, $lesson_id);
        $upd_watch->execute();
    }

    $completed = $conn->prepare("SELECT COUNT(*) AS total FROM user_lessons WHERE enrollment_id = ? AND is_completed = 1");
    $completed->bind_param("i", $enrollment_db_id);
    $completed->execute();
    $done = $completed->get_result()->fetch_assoc();
    $total_done = $done['total'] ?? 0;
    $progress_percent = round(($total_done / count($lessons)) * 100);

    $status = ($progress_percent >= 100) ? 'completed' : 'active';
    $update_enroll = $conn->prepare("UPDATE enrollments SET progress = ?, status = ?, completed_at = CASE WHEN ? = 100 THEN NOW() ELSE completed_at END WHERE id = ?");
    $update_enroll->bind_param("isii", $progress_percent, $status, $progress_percent, $enrollment_db_id);
    $update_enroll->execute();
}

$video_url = $current_lesson['video_url'];
$is_youtube = (str_contains($video_url, 'youtube.com') || str_contains($video_url, 'youtu.be'));
$embed_url = $is_youtube ? str_replace("watch?v=", "embed/", $video_url) . "?rel=0&modestbranding=1&autohide=1&showinfo=0" : $video_url;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($course['title']) ?> | Learn</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/learn.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<!-- 📱 Mobile Sidebar Toggle -->
<nav class="navbar bg-light d-md-none">
  <div class="container-fluid">
    <button class="btn btn-outline-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#lessonSidebar">
      <i class="fas fa-bars"></i> Lessons
    </button>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <!-- 📱 Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="lessonSidebar">
      <div class="offcanvas-header">
        <h5 class="offcanvas-title">Lessons</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
      </div>
      <div class="offcanvas-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($lessons as $lesson): ?>
            <?php $active = $lesson['id'] == $current_lesson['id'] ? 'bg-warning text-dark fw-bold' : ''; ?>
            <a href="learn.php?id=<?= $course_id ?>&lesson=<?= $lesson['id'] ?>" class="list-group-item list-group-item-action <?= $active ?>">
              <?= htmlspecialchars($lesson['title']) ?>
            </a>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- 🖥️ Desktop Sidebar -->
    <aside class="col-md-3 bg-light border-end vh-100 overflow-auto d-none d-md-block p-0">
      <h5 class="p-3 bg-primary text-white mb-0">📚 Lessons</h5>
      <ul class="list-group list-group-flush">
        <?php foreach ($lessons as $lesson): ?>
          <?php $active = $lesson['id'] == $current_lesson['id'] ? 'bg-warning text-dark fw-bold' : ''; ?>
          <a href="learn.php?id=<?= $course_id ?>&lesson=<?= $lesson['id'] ?>" class="list-group-item list-group-item-action <?= $active ?>">
            <?= htmlspecialchars($lesson['title']) ?>
          </a>
        <?php endforeach; ?>
      </ul>
    </aside>

    <!-- 🎬 Main Lesson Content -->
    <main class="col-md-9 p-4">
      <h3 class="mb-3"><i class="fas fa-play-circle text-success me-2"></i><?= htmlspecialchars($current_lesson['title']) ?></h3>

      <div class="ratio ratio-16x9 mb-4">
        <?php if ($is_youtube): ?>
          <iframe src="<?= $embed_url ?>" allowfullscreen class="rounded shadow"></iframe>
        <?php else: ?>
          <video controls class="w-100 rounded shadow">
            <source src="<?= $video_url ?>" type="video/mp4">
          </video>
        <?php endif; ?>
      </div>

      <!-- 🔁 Progress -->
      <div class="progress mb-4" style="height: 25px;">
        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width: <?= $progress_percent ?>%;">
          <?= $progress_percent ?>% Complete
        </div>
      </div>

      <!-- ✅ Notifications -->
      <?php if (isset($_GET['completed'])): ?>
        <?php if ($is_completed): ?>
          <script>
            Swal.fire({ icon: 'info', title: 'Already Completed', text: 'You’ve already completed this lesson.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
          </script>
        <?php else: ?>
          <script>
            Swal.fire({ icon: 'success', title: 'Lesson Completed', text: 'You’ve successfully completed this lesson.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
          </script>
        <?php endif; ?>
      <?php endif; ?>

      <!-- 🔘 Completion Button -->
      <?php if ($is_enrolled && !$is_instructor && !$is_completed): ?>
        <form method="POST" action="../ajax/mark-complete.php?course_id=<?= $course_id ?>" onsubmit="return confirmComplete();">
          <input type="hidden" name="lesson_id" value="<?= $current_lesson['id'] ?>">
          <input type="hidden" name="enrollment_id" value="<?= $enrollment_db_id ?>">
          <button type="submit" class="btn btn-success btn-lg shadow-sm"><i class="fas fa-check-circle me-1"></i> Mark as Completed</button>
        </form>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- 🎓 Course Completion Certificate -->
<?php if ($progress_percent == 100 && !$is_instructor): ?>
<script>
  window.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
      icon: 'success',
      title: '🎉 Course Completed!',
      html: `
        <p>You’ve successfully completed this course!</p>
        <p>Please contact Instructor to Get your Certificate.</p>
        <hr>
        <p><strong>Enrollment ID:</strong> #<?= $enrollment_id ?></p>
        <p><strong>Instructor:</strong> <?= htmlspecialchars($instructors[0]['full_name']) ?></p>
        <p><strong>Contact:</strong> <?= htmlspecialchars($instructors[0]['phone']) ?></p>
      `,
      confirmButtonText: 'OK, Got it!'
    });
  });
</script>
<?php endif; ?>

<script>
function confirmComplete() {
  return Swal.fire({
    title: 'Mark as Completed?',
    text: 'This will record your lesson as finished.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, mark it!'
  }).then(result => result.isConfirmed);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>