<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$current_instructor_id = $_SESSION['user_id'];

$durations_res = $conn->query("SELECT DISTINCT duration FROM courses WHERE duration IS NOT NULL AND duration != ''");
$durations = [];
while ($row = $durations_res->fetch_assoc()) $durations[] = $row['duration'];

$categories_res = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''");
$categories = [];
while ($row = $categories_res->fetch_assoc()) $categories[] = $row['category'];

$instructors_res = $conn->prepare("SELECT id, full_name FROM users WHERE role = 'instructor' AND id != ?");
$instructors_res->bind_param("i", $current_instructor_id);
$instructors_res->execute();
$result = $instructors_res->get_result();
$instructors = [];
while ($row = $result->fetch_assoc()) $instructors[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $short_description = $_POST['short_description'];
    $long_description = $_POST['long_description'];
    $category = $_POST['category'];
    $price = floatval($_POST['price']);
    $duration = $_POST['duration'];
    $status = $_POST['status'];
    $created_at = $updated_at = date('Y-m-d H:i:s');
    $lessons = $_POST['lessons'] ?? [];
    $selected_instructors = $_POST['instructors'] ?? [];
    $selected_instructors = array_unique(array_merge([$current_instructor_id], $selected_instructors));

    $errors = [];

    if (!$title || !$short_description || !$long_description || !$category || !$duration || !$status) {
        $errors[] = "All fields are required.";
    }
    if ($price != 0 && $price < 5) {
        $errors[] = "Minimum paid course price must be at least $5.";
    }
    if (count($selected_instructors) < 1 || count($selected_instructors) > 3) {
        $errors[] = "You must select up to 2 co-instructors (3 max total).";
    }

    $image_path = $thumb_path = '';

    function uploadImage($file, $targetDir) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = uniqid() . '.' . $ext;
        $targetPath = $targetDir . $newName;
        move_uploaded_file($file['tmp_name'], $targetPath);
        return $targetPath;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0)
        $image_path = uploadImage($_FILES['image'], '../assets/uploads/images/');

    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0)
        $thumb_path = uploadImage($_FILES['thumbnail'], '../assets/uploads/thumbnails/');

    if (empty($errors)) {
        $instructors_csv = implode(',', $selected_instructors);
        $stmt = $conn->prepare("INSERT INTO courses (title, short_description, long_description, image, thumbnail, category, price, duration, instructor_id, created_at, updated_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdsssss", $title, $short_description, $long_description, $image_path, $thumb_path, $category, $price, $duration, $instructors_csv, $created_at, $updated_at, $status);

        if ($stmt->execute()) {
            $course_id = $stmt->insert_id;

            $ci_stmt = $conn->prepare("INSERT INTO course_instructors (course_id, instructor_id) VALUES (?, ?)");
            foreach ($selected_instructors as $inst_id) {
                $ci_stmt->bind_param("ii", $course_id, $inst_id);
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

            $_SESSION['success'] = "✅ Course <strong>$title</strong> added successfully!";
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
    <title>Add New Course - Instructor</title>
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
    <h2 class="mb-4"><i class="fas fa-plus-circle"></i> Add New Course</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: <?= json_encode(strip_tags($_SESSION['success'])) ?>,
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
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">What's this course about?</label>
            <textarea name="short_description" class="form-control rich"></textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Detailed Description</label>
            <textarea name="long_description" class="form-control rich" required></textarea>
        </div>

        <div class="col-md-6">
            <label class="form-label">Course Image (1280x720)</label>
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
            <label class="form-label">Duration</label>
            <select name="duration" class="form-select" required>
                <option value="">Select Duration</option>
                <?php foreach ($durations as $d): ?>
                    <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Add Co-Instructors (optional)</label>
            <select name="instructors[]" class="form-select" multiple size="4">
                <?php foreach ($instructors as $inst): ?>
                    <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="Draft">Draft</option>
                <option value="Published">Published</option>
            </select>
        </div>

        <div class="col-12">
            <h5 class="mt-3"><i class="fas fa-video"></i> Lessons (first one is demo)</h5>
            <div id="lesson-container" class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="lessons[0][title]" class="form-control" placeholder="Lesson Title" required>
                </div>
                <div class="col-md-6">
                    <input type="url" name="lessons[0][url]" class="form-control" placeholder="Video URL" required>
                </div>
            </div>
            <button type="button" class="btn btn-secondary mt-2" onclick="addLesson()"><i class="fas fa-plus"></i> Add Lesson</button>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-success mt-4"><i class="fas fa-check-circle"></i> Publish Course</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/add-course.js"></script>
</body>
</html>