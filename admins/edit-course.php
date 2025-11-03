<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ Invalid course ID.");
}

$course_id = intval($_GET['id']);

$course_stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();

if ($course_result->num_rows === 0) {
    die("❌ Course not found.");
}

$course = $course_result->fetch_assoc();

$lessons = [];
$lesson_stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id = ?");
$lesson_stmt->bind_param("i", $course_id);
$lesson_stmt->execute();
$lesson_result = $lesson_stmt->get_result();
while ($row = $lesson_result->fetch_assoc()) $lessons[] = $row;

// ✅ Use full_name for instructors
$instructors = [];
$res = $conn->query("SELECT id, full_name FROM users WHERE role = 'instructor'");
while ($row = $res->fetch_assoc()) $instructors[] = $row;

$assigned_instructors = [];
$res = $conn->query("SELECT instructor_id FROM course_instructors WHERE course_id = $course_id");
while ($row = $res->fetch_assoc()) $assigned_instructors[] = $row['instructor_id'];

$categories = $durations = [];
$res = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''");
while ($row = $res->fetch_assoc()) $categories[] = $row['category'];
$res = $conn->query("SELECT DISTINCT duration FROM courses WHERE duration IS NOT NULL AND duration != ''");
while ($row = $res->fetch_assoc()) $durations[] = $row['duration'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $short_description = $_POST['short_description'];
    $long_description = $_POST['long_description'];
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $duration = $_POST['duration'];
    $status = $_POST['status'];
    $updated_at = date('Y-m-d H:i:s');
    $instructors_selected = $_POST['instructors'] ?? [];
    $lessons_post = $_POST['lessons'] ?? [];

    if (!$title || !$short_description || !$long_description || !$category || !$duration || !$status) {
        $errors[] = "All fields are required.";
    }
    if ($price != 0 && $price < 5) {
        $errors[] = "Minimum paid course price must be at least $5.";
    }
    if (count($instructors_selected) < 1 || count($instructors_selected) > 3) {
        $errors[] = "Select at least 1 and at most 3 instructors.";
    }

    function uploadImage($file, $targetDir) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = uniqid() . '.' . $ext;
        $targetPath = $targetDir . $newName;
        move_uploaded_file($file['tmp_name'], $targetPath);
        return $targetPath;
    }

    $image_path = $course['image'];
    $thumb_path = $course['thumbnail'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $image_path = uploadImage($_FILES['image'], '../assets/uploads/images/');
    }
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
        $thumb_path = uploadImage($_FILES['thumbnail'], '../assets/uploads/thumbnails/');
    }

    if (empty($errors)) {
        $instructors_csv = implode(',', $instructors_selected);
        $stmt = $conn->prepare("UPDATE courses SET title=?, short_description=?, long_description=?, image=?, thumbnail=?, category=?, price=?, duration=?, instructor_id=?, status=?, updated_at=? WHERE id=?");
        $stmt->bind_param("ssssssdssssi", $title, $short_description, $long_description, $image_path, $thumb_path, $category, $price, $duration, $instructors_csv, $status, $updated_at, $course_id);

        if ($stmt->execute()) {
            $conn->query("DELETE FROM course_instructors WHERE course_id = $course_id");
            $ci_stmt = $conn->prepare("INSERT INTO course_instructors (course_id, instructor_id) VALUES (?, ?)");
            foreach ($instructors_selected as $inst_id) {
                $ci_stmt->bind_param("ii", $course_id, $inst_id);
                $ci_stmt->execute();
            }

            $conn->query("DELETE FROM lessons WHERE course_id = $course_id");
            $lesson_stmt = $conn->prepare("INSERT INTO lessons (course_id, title, video_url, is_demo) VALUES (?, ?, ?, ?)");
            foreach ($lessons_post as $i => $lesson) {
                $ltitle = $lesson['title'];
                $lurl = $lesson['url'];
                $isdemo = $i == 0 ? 1 : 0;
                $lesson_stmt->bind_param("issi", $course_id, $ltitle, $lurl, $isdemo);
                $lesson_stmt->execute();
            }

            $_SESSION['message'] = ['success', "✅ Course <strong>$title</strong> updated successfully!"];
            header("Location: edit-course.php?id=$course_id");
            exit();
        } else {
            $errors[] = "Database error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Course - Admin</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tiny.cloud/1/61dt0ngnybdk5gtgch9xzkquh71uvuuormyjo07p9jj5vhh5/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="../assets/js/add-course.js"></script>
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
    <h2 class="mb-4">Edit Course</h2>

    <form method="POST" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Course Name</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($course['title']) ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= $cat === $course['category'] ? 'selected' : '' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Short Description</label>
            <textarea name="short_description" class="form-control rich"><?= htmlspecialchars($course['short_description']) ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Long Description</label>
            <textarea name="long_description" class="form-control rich" required><?= htmlspecialchars($course['long_description']) ?></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Replace Image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>

        <div class="col-md-6">
            <label class="form-label">Replace Thumbnail</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*">
        </div>

        <div class="col-md-6">
            <label class="form-label">Price ($0 = Free)</label>
            <input type="number" name="price" class="form-control" value="<?= $course['price'] ?>" min="0" step="0.01" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Duration</label>
            <select name="duration" class="form-select" required>
                <option value="">Select Duration</option>
                <?php foreach ($durations as $d): ?>
                    <option value="<?= $d ?>" <?= $d === $course['duration'] ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Instructors (1–3)</label>
            <select name="instructors[]" class="form-select" multiple required>
                <?php foreach ($instructors as $inst): ?>
                    <option value="<?= $inst['id'] ?>" <?= in_array($inst['id'], $assigned_instructors) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($inst['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="Draft" <?= $course['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Published" <?= $course['status'] === 'Published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>

        <div class="col-12">
            <h5 class="mt-4">Lessons (first one is demo)</h5>
            <div id="lesson-container" class="row g-2">
                <?php foreach ($lessons as $i => $lesson): ?>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <input type="text" name="lessons[<?= $i ?>][title]" class="form-control" value="<?= htmlspecialchars($lesson['title']) ?>" required>
                        </div>
                        <div class="col-md-6 d-flex">
                            <input type="url" name="lessons[<?= $i ?>][url]" class="form-control" value="<?= htmlspecialchars($lesson['video_url']) ?>" required>
                            <?php if ($i !== 0): ?>
                                <button type="button" class="btn btn-danger ms-2" onclick="this.closest('.row').remove()">🗑</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary mt-2" onclick="addLesson()">Add Lesson</button>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-success mt-3">Update Course</button>
        </div>
    </form>
  </div>
</div>

<!-- SweetAlert Errors -->
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