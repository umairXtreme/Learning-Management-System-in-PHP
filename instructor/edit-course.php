<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("❌ Invalid course ID.");
}

$course_id = intval($_GET['id']);

// Instructor Ownership Verification
$auth_check = $conn->prepare("SELECT 1 FROM course_instructors WHERE course_id = ? AND instructor_id = ?");
$auth_check->bind_param("ii", $course_id, $user_id);
$auth_check->execute();
$auth_check->store_result();
if ($auth_check->num_rows === 0) {
    die("⛔ You are not authorized to edit this course.");
}

// Fetch Course Info
$course_stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$course = $course_result->fetch_assoc();

// Fetch Lessons
$lessons = [];
$lesson_stmt = $conn->prepare("SELECT * FROM lessons WHERE course_id = ?");
$lesson_stmt->bind_param("i", $course_id);
$lesson_stmt->execute();
$res = $lesson_stmt->get_result();
while ($row = $res->fetch_assoc()) $lessons[] = $row;

// Categories & Durations
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
    $lessons_post = $_POST['lessons'] ?? [];

    if (!$title || !$short_description || !$long_description || !$category || !$duration || !$status) {
        $errors[] = "All fields are required.";
    }

    if ($price != 0 && $price < 5) {
        $errors[] = "Minimum paid course price must be at least $5.";
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
        $stmt = $conn->prepare("UPDATE courses SET title=?, short_description=?, long_description=?, image=?, thumbnail=?, category=?, price=?, duration=?, status=?, updated_at=? WHERE id=?");
        $stmt->bind_param("ssssssdsssi", $title, $short_description, $long_description, $image_path, $thumb_path, $category, $price, $duration, $status, $updated_at, $course_id);

        if ($stmt->execute()) {
            $conn->query("DELETE FROM lessons WHERE course_id = $course_id");
            $lesson_stmt = $conn->prepare("INSERT INTO lessons (course_id, title, video_url, is_demo) VALUES (?, ?, ?, ?)");
            foreach ($lessons_post as $i => $lesson) {
                $ltitle = $lesson['title'];
                $lurl = $lesson['url'];
                $isdemo = $i == 0 ? 1 : 0;
                $lesson_stmt->bind_param("issi", $course_id, $ltitle, $lurl, $isdemo);
                $lesson_stmt->execute();
            }

            $_SESSION['success'] = "✅ Course <strong>$title</strong> updated successfully!";
            header("Location: edit-course.php?id=$course_id");
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
    <title>Edit Course - Instructor</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- UI Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tiny.cloud/1/61dt0ngnybdk5gtgch9xzkquh71uvuuormyjo07p9jj5vhh5/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-label {
            font-weight: 600;
        }
        .form-container {
            max-width: 1180px;
            margin: auto;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="main-content container mt-5 form-container">
    <h2 class="mb-4"><i class="fas fa-pen-to-square"></i> Edit Course</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                toast: true,
                icon: 'success',
                title: <?= json_encode(strip_tags($_SESSION['success'])) ?>,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4">
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
            <label class="form-label">What's this course about?</label>
            <textarea name="short_description" class="form-control rich"><?= htmlspecialchars($course['short_description']) ?></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Detailed Description</label>
            <textarea name="long_description" class="form-control rich"><?= htmlspecialchars($course['long_description']) ?></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Update Course Image (optional)</label>
            <input type="file" name="image" class="form-control" accept="image/*">
        </div>

        <div class="col-md-6">
            <label class="form-label">Update Thumbnail (optional)</label>
            <input type="file" name="thumbnail" class="form-control" accept="image/*">
        </div>

        <div class="col-md-6">
            <label class="form-label">Price ($0 = Free)</label>
            <input type="number" name="price" class="form-control" min="0" step="0.01" value="<?= $course['price'] ?>" required>
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

        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="Draft" <?= $course['status'] === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Published" <?= $course['status'] === 'Published' ? 'selected' : '' ?>>Published</option>
            </select>
        </div>

        <div class="col-12">
            <h5 class="mt-3"><i class="fas fa-video"></i> Lessons (first one is demo)</h5>
            <div id="lesson-container" class="row g-2">
                <?php foreach ($lessons as $i => $lesson): ?>
                    <div class="col-md-6 mb-2">
                        <input type="text" name="lessons[<?= $i ?>][title]" class="form-control" placeholder="Lesson Title" value="<?= htmlspecialchars($lesson['title']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="url" name="lessons[<?= $i ?>][url]" class="form-control" placeholder="Video URL" value="<?= htmlspecialchars($lesson['video_url']) ?>" required>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary mt-2" onclick="addLesson()"><i class="fas fa-plus"></i> Add Lesson</button>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-success mt-4"><i class="fas fa-check-circle"></i> Update Course</button>
        </div>
    </form>
</div>

<script src="../assets/js/add-course.js"></script>
</body>
</html>