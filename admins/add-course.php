<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

$durations_res = $conn->query("SELECT DISTINCT duration FROM courses WHERE duration IS NOT NULL AND duration != ''");
$durations = [];
while ($row = $durations_res->fetch_assoc()) $durations[] = $row['duration'];

$categories_res = $conn->query("
    SELECT category 
    FROM courses 
    WHERE category IS NOT NULL AND TRIM(category) != ''
");

$seen = [];
$categories = [];

while ($row = $categories_res->fetch_assoc()) {
    $key = strtolower(trim($row['category']));
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $categories[] = trim($row['category']); // Preserve original formatting
    }
}

sort($categories, SORT_NATURAL | SORT_FLAG_CASE); // Optional: Sort alphabetically


// Fetch Instructors (FULL NAMES NOW)
$instructors_res = $conn->query("SELECT id, full_name FROM users WHERE role = 'instructor'");
$instructors = [];
while ($row = $instructors_res->fetch_assoc()) {
    $instructors[] = $row;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $short_description = $_POST['short_description'];
    $long_description = $_POST['long_description'];
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $duration = $_POST['duration'];
    $status = $_POST['status'];
    $created_at = $updated_at = date('Y-m-d H:i:s');
    $instructors_selected = $_POST['instructors'] ?? [];
    $lessons = $_POST['lessons'] ?? [];

    if (!$title || !$short_description || !$long_description || !$category || !$duration || !$status) {
        $errors[] = "All fields are required.";
    }
    if ($price != 0 && $price < 5) {
        $errors[] = "Minimum paid course price must be at least $5.";
    }
    if (count($instructors_selected) < 1 || count($instructors_selected) > 3) {
        $errors[] = "Select at least 1 and at most 3 instructors.";
    }

    $image_path = $thumb_path = '';

    function uploadImage($file, $targetDir) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = uniqid() . '.' . $ext;
        $targetPath = $targetDir . $newName;
        move_uploaded_file($file['tmp_name'], $targetPath);
        return $targetPath;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $image_path = uploadImage($_FILES['image'], '../assets/uploads/images/');
    }
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
        $thumb_path = uploadImage($_FILES['thumbnail'], '../assets/uploads/thumbnails/');
    }

    if (empty($errors)) {
        $instructors_csv = implode(',', $instructors_selected);
        $stmt = $conn->prepare("INSERT INTO courses (title, short_description, long_description, image, thumbnail, category, price, duration, instructor_id, created_at, updated_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdsssss", $title, $short_description, $long_description, $image_path, $thumb_path, $category, $price, $duration, $instructors_csv, $created_at, $updated_at, $status);
        if ($stmt->execute()) {
            $course_id = $stmt->insert_id;

            $ci_stmt = $conn->prepare("INSERT INTO course_instructors (course_id, instructor_id) VALUES (?, ?)");
            foreach ($instructors_selected as $instructor_id) {
                $ci_stmt->bind_param("ii", $course_id, $instructor_id);
                $ci_stmt->execute();
            }

            $lesson_stmt = $conn->prepare("INSERT INTO lessons (course_id, title, video_url, is_demo) VALUES (?, ?, ?, ?)");
            foreach ($lessons as $i => $lesson) {
                $ltitle = $lesson['title'];
                $lurl = $lesson['url'];
                $isdemo = $i == 0 ? 1 : 0;
                $lesson_stmt->bind_param("issi", $course_id, $ltitle, $lurl, $isdemo);
                $lesson_stmt->execute();
            }

            $_SESSION['message'] = ['success', "✅ Course <strong>$title</strong> added successfully!"];
            header("Location: add-course.php");
            exit();
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Course - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Core Assets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tiny.cloud/1/61dt0ngnybdk5gtgch9xzkquh71uvuuormyjo07p9jj5vhh5/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<style>
    /* 🌐 Base Layout */
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
}
    .form-label {
        font-weight:600;
    }
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-container">
    <h2 class="mb-4">Add New Course</h2>

    <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Course Name:</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Category:</label>
            <select name="category" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat) echo "<option value='$cat'>$cat</option>"; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">What is this course about?</label>
            <textarea name="short_description" class="form-control rich"></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Long Description:</label>
            <textarea name="long_description" class="form-control rich" required></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Image (1280x720)</label>
            <input type="file" name="image" class="form-control" accept="image/*" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Thumbnail (300x200)</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Price ($0 = Free)</label>
            <input type="number" name="price" class="form-control" min="0" step="0.01" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Duration:</label>
            <select name="duration" class="form-select" required>
                <option value="">Select Duration</option>
                <?php foreach ($durations as $d) echo "<option value='$d'>$d</option>"; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Instructors (1–3):</label>
            <select name="instructors[]" class="form-select" multiple required>
    <?php foreach ($instructors as $inst): ?>
        <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['full_name']) ?></option>
    <?php endforeach; ?>
</select>

        </div>

        <div class="col-md-6">
            <label class="form-label">Status:</label>
            <select name="status" class="form-select" required>
                <option value="Draft">Draft</option>
                <option value="Published">Published</option>
            </select>
        </div>

        <div class="col-12">
            <h5 class="mt-4">Lessons (first one is demo)</h5>
            <div id="lesson-container" class="row g-2">
                <div class="lesson col-md-6">
                    <input type="text" name="lessons[0][title]" class="form-control" placeholder="Lesson Title" required>
                </div>
                <div class="lesson col-md-6">
                    <input type="url" name="lessons[0][url]" class="form-control" placeholder="Lesson Video URL" required>
                </div>
            </div>
            <button type="button" class="btn btn-secondary mt-2" onclick="addLesson()">Add Lesson</button>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-success mt-3">Publish Course</button>
        </div>
    </form>
  </div>
</div>

<script src="../assets/js/add-course.js"></script>

<?php if (!empty($errors)): ?>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
      icon: 'error',
      title: 'Validation Errors',
      html: `<?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>`,
    });
  });
</script>
<?php endif; ?>

<?php if (isset($_SESSION['message'])): ?>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
      toast: true,
      icon: 'success',
      title: <?= json_encode(strip_tags($_SESSION['message'][1] ?? $_SESSION['message'])) ?>,
      position: 'top-end',
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true
    });
  });
</script>
<?php unset($_SESSION['message']); ?>
<?php endif; ?>

</body>
</html>