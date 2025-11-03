<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-reviews.php");
    exit();
}

$review_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT r.*, 
           c.title AS course_title, 
           u.full_name AS reviewer_name
    FROM reviews r
    JOIN courses c ON r.course_id = c.id
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ? AND r.is_deleted = 0 AND c.instructor_id = ?
");
$stmt->bind_param("ii", $review_id, $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
$review = $result->fetch_assoc();

if (!$review) {
    $_SESSION['error'] = "❌ Review not found or not authorized.";
    header("Location: manage-reviews.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply = trim($_POST['reply']);

    $update = $conn->prepare("UPDATE reviews SET instructor_reply = ?, updated_at = NOW() WHERE id = ? AND is_deleted = 0");
    $update->bind_param("si", $reply, $review_id);
    if ($update->execute()) {
        $_SESSION['success'] = "✅ Reply saved successfully.";
        header("Location: manage-reviews.php");
        exit();
    } else {
        $error = "❌ Failed to save reply. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Review - Instructor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Core -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .card {
            box-shadow: 0 0.2rem 0.5rem rgba(0, 0, 0, 0.05);
        }
        @media screen and (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
                padding: 10px;
            }
            
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="admin-page-wrapper">
<div class="container mt-5">
    <h3><i class="fas fa-eye"></i> Review Details</h3>

    <?php if (!empty($error)): ?>
        <script>
        document.addEventListener("DOMContentLoaded", () => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: <?= json_encode($error) ?>
            });
        });
        </script>
    <?php endif; ?>

    <div class="card mt-4">
        <div class="card-body">
            <p><strong>Course Name:</strong> <?= htmlspecialchars($review['course_title']) ?></p>

            <p><strong>Rating:</strong>
                <?= str_repeat("⭐", $review['rating']) . str_repeat("☆", 5 - $review['rating']) ?>
            </p>

            <p><strong>Review Comment:</strong></p>
            <div class="border rounded p-3 bg-light"><?= nl2br(htmlspecialchars($review['content'])) ?></div>

            <p class="mt-3"><strong>Reviewed by:</strong> <?= htmlspecialchars($review['reviewer_name']) ?></p>

            <p class="text-muted mt-2">
                <i class="far fa-calendar-alt"></i> 
                <strong>Published:</strong> <?= date('F j, Y \a\t g:i A', strtotime($review['created_at'])) ?>
                <?php if (!empty($review['updated_at']) && $review['updated_at'] !== $review['created_at']): ?>
                    <br><i class="fas fa-edit"></i> <strong>Last Updated:</strong> <?= date('F j, Y \a\t g:i A', strtotime($review['updated_at'])) ?>
                <?php endif; ?>
            </p>

            <hr>

            <!-- Reply Form -->
            <form method="POST" class="mt-3">
                <label for="reply" class="form-label"><strong>Your Reply:</strong></label>
                <textarea name="reply" id="reply" class="form-control" rows="4" required><?= htmlspecialchars($review['instructor_reply'] ?? '') ?></textarea>   

                <button type="submit" class="btn btn-success mt-3">
                    <i class="fas fa-reply"></i> Publish Reply
                </button>
            </form>
        </div>
    </div>

    <a href="manage-reviews.php" class="btn btn-secondary mt-4"><i class="fas fa-arrow-left"></i> Back to Manage Reviews</a>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>