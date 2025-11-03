<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$errors = [];

// 🔐 Verify Enrollment and Prevent Duplicate
$check = $conn->prepare("
    SELECT c.title, i.full_name AS instructor_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users i ON i.id = c.instructor_id
    WHERE e.user_id = ? AND e.course_id = ?
      AND NOT EXISTS (
          SELECT 1 FROM reviews r 
          WHERE r.user_id = ? AND r.course_id = ? AND r.is_deleted = 0
      )
");
$check->bind_param("iiii", $student_id, $course_id, $student_id, $course_id);
$check->execute();
$course = $check->get_result()->fetch_assoc();

if (!$course) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Access denied or review already submitted."];
    header("Location: my-reviews.php");
    exit();
}

// ✅ Handle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['content'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "⭐ Please select a rating between 1–5."];
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, course_id, rating, content, status, created_at, is_deleted) VALUES (?, ?, ?, ?, 'pending', NOW(), 0)");
        $stmt->bind_param("iiis", $student_id, $course_id, $rating, $comment);
        if ($stmt->execute()) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => "✅ Review submitted! Waiting approval."];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Failed to submit review."];
        }
        header("Location: my-reviews.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Review</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .rating-stars i {
            font-size: 1.5rem;
            cursor: pointer;
            color: #ccc;
        }
        .rating-stars i.selected {
            color: #ffc107;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
    <div class="container mt-5">
        <h4 class="mb-4"><i class="fas fa-pen-to-square me-2"></i> Leave a Review</h4>

        <div class="card p-4 shadow-sm">
            <h5 class="mb-3">📚 <?= htmlspecialchars($course['title']) ?></h5>
            <p><strong>Instructor:</strong> <?= htmlspecialchars($course['instructor_name']) ?></p>

<form method="POST" id="reviewForm">
                <div class="mb-3">
                    <label class="form-label">Rating</label>
                    <div class="rating-stars" id="starContainer">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" data-value="<?= $i ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput">
                </div>
                <div class="mb-3">
                    <label class="form-label">Comments (optional)</label>
                    <textarea name="content" class="form-control" rows="4" placeholder="Share your thoughts..."></textarea>
                </div>
<button type="button" id="submitReviewBtn" class="btn btn-primary">
    <i class="fas fa-paper-plane me-1"></i> Submit Review
  </button>

                <a href="my-reviews.php" class="btn btn-secondary ms-2">Cancel/Back to My Reviews</a>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const stars = document.querySelectorAll('.rating-stars i');
    const ratingInput = document.getElementById('ratingInput');

    // ⭐ Star click logic
    stars.forEach(star => {
        star.addEventListener('click', () => {
            const rating = parseInt(star.getAttribute('data-value'));
            ratingInput.value = rating;
            stars.forEach(s => {
                s.classList.toggle('selected', parseInt(s.getAttribute('data-value')) <= rating);
            });
        });
    });

    // 🔐 Confirmation before form submission
    document.getElementById('submitReviewBtn')?.addEventListener('click', () => {
        Swal.fire({
            title: 'Submit Your Review?',
            text: "Once submitted, it will be sent for approval.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, submit it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0d6efd',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('reviewForm').submit();
            }
        });
    });

    // ✅ Toast alert
    <?php if (!empty($_SESSION['toast'])):
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
    ?>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: '<?= $toast['type'] ?>',
        title: '<?= $toast['message'] ?>',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true
    });
    <?php endif; ?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>