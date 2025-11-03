<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage-reviews.php");
    exit();
}

$review_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT reviews.*, 
           courses.title AS course_title, 
           users.full_name AS reviewer_name, 
           instructors.full_name AS instructor_name
    FROM reviews
    JOIN courses ON reviews.course_id = courses.id
    JOIN users ON reviews.user_id = users.id
    LEFT JOIN users AS instructors ON courses.instructor_id = instructors.id
    WHERE reviews.id = ? AND reviews.is_deleted = 0
");

$stmt->bind_param("i", $review_id);
$stmt->execute();
$result = $stmt->get_result();
$review = $result->fetch_assoc();

if (!$review) {
    $_SESSION['error'] = "❌ Review not found.";
    header("Location: manage-reviews.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Review - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }
        .admin-page-wrapper {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        @media (max-width: 768px) {
            .admin-page-wrapper {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
        .student-comment {
            background-color: #e9f7ef;
            border-left: 4px solid #28a745;
        }
        .instructor-reply {
            background-color: #fff;
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-page-wrapper">
    <div class="container">
        <h3><i class="fas fa-eye me-2"></i> Review Details</h3>

        <div class="card mt-4 shadow-sm">
            <div class="card-body">
                <p><strong>Course Name:</strong> <?= htmlspecialchars($review['course_title']) ?></p>
                <p><strong>Course Instructor:</strong> <span class="text-primary"><?= htmlspecialchars($review['instructor_name']) ?></span></p>

                <p><strong>Rating:</strong>
                    <?= str_repeat("⭐", $review['rating']) . str_repeat("☆", 5 - $review['rating']) ?>
                </p>

                <p><strong>Review Comment:</strong></p>
                <div class="border rounded p-3 student-comment mb-3">
                    <?= nl2br(htmlspecialchars($review['content'])) ?>
                </div>

                <?php if (!empty($review['instructor_reply'])): ?>
                    <p class="mt-4"><strong>Instructor's Reply:</strong></p>
                    <div class="border rounded p-3 instructor-reply shadow-sm text-dark mb-3">
                        <?= nl2br(htmlspecialchars($review['instructor_reply'])) ?>
                    </div>
                <?php endif; ?>

                <p class="mt-3"><strong>Reviewed by:</strong> <?= htmlspecialchars($review['reviewer_name']) ?></p>
                <p class="text-muted">
                    <i class="far fa-calendar-alt"></i>
                    <strong>Published:</strong> <?= date('F j, Y \a\t g:i A', strtotime($review['created_at'])) ?>
                    <?php if (!empty($review['updated_at']) && $review['updated_at'] !== $review['created_at']): ?>
                        <br><i class="fas fa-edit"></i> <strong>Last Updated:</strong> <?= date('F j, Y \a\t g:i A', strtotime($review['updated_at'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <a href="manage-reviews.php" class="btn btn-secondary mt-3">
            <i class="fas fa-arrow-left"></i> Back to Manage Reviews
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>