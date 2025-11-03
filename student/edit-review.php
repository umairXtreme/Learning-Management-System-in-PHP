<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my-reviews.php");
    exit;
}

$review_id = intval($_GET['id']);

$stmt = $conn->prepare("
    SELECT r.*, 
           c.title AS course_title, 
           u.full_name AS instructor_name
    FROM reviews r
    JOIN courses c ON c.id = r.course_id
    JOIN users u ON u.id = c.instructor_id
    WHERE r.id = ? AND r.user_id = ? AND r.is_deleted = 0
");
$stmt->bind_param("ii", $review_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
$review = $result->fetch_assoc();

if (!$review) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Review not found."];
    header("Location: my-reviews.php");
    exit;
}

// === Update logic ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_rating = intval($_POST['rating']);
    $new_content = trim($_POST['content']);

    if ($new_rating > 0 && $new_rating <= 5 && $new_content !== '') {
        $status = $review['status'] === 'Approved' ? 'Pending' : $review['status'];

        $stmt = $conn->prepare("UPDATE reviews SET rating = ?, content = ?, updated_at = NOW(), status = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("issii", $new_rating, $new_content, $status, $review_id, $student_id);
        $stmt->execute();

        $_SESSION['toast'] = [
            'type' => 'success',
            'message' => $review['status'] === 'Approved'
                ? "✅ Review updated! Sent again for approval."
                : "✅ Review updated successfully."
        ];

        header("Location: my-reviews.php");
        exit;
    } else {
        $error = "⚠️ Please provide valid rating and comment.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit My Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            padding: 20px;
        }
        .card {
            background: #ffffff;
            box-shadow: 0 0 8px rgba(0,0,0,0.05);
            border-radius: 8px;
        }
        textarea.form-control {
            resize: vertical;
        }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
    <div class="container mt-4">

        <h3 class="mb-4"><i class="fas fa-pen-to-square"></i> Review for: <strong><?= htmlspecialchars($review['course_title']) ?></strong></h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <p><strong>Instructor:</strong> <?= htmlspecialchars($review['instructor_name']) ?></p>
                <p><strong>Original Rating:</strong> <?= str_repeat("⭐", $review['rating']) . str_repeat("☆", 5 - $review['rating']) ?></p>
                <p><strong>Your Comment:</strong></p>
                <div class="border rounded p-3 bg-light"><?= nl2br(htmlspecialchars($review['content'])) ?></div>

                <?php if (!empty($review['instructor_reply'])): ?>
                    <div class="mt-4">
                        <p><strong>Instructor's Reply:</strong></p>
                        <div class="border rounded p-3 bg-white shadow-sm"><?= nl2br(htmlspecialchars($review['instructor_reply'])) ?></div>
                    </div>
                <?php endif; ?>

                <p class="mt-4 text-muted">
                    <i class="far fa-calendar-alt me-1"></i> <strong>Created:</strong> <?= date('F j, Y g:i A', strtotime($review['created_at'])) ?>
                    <?php if ($review['updated_at'] !== null && $review['updated_at'] !== $review['created_at']): ?>
                        <br><i class="fas fa-edit me-1"></i> <strong>Last Edited:</strong> <?= date('F j, Y g:i A', strtotime($review['updated_at'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <h5 class="mb-3">✏️ Edit Your Review</h5>
        <form id="editReviewForm" method="POST">
            <div class="mb-3">
                <label class="form-label">Rating</label>
                <select name="rating" class="form-select" required>
                    <option value="">-- Select Rating --</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= $review['rating'] == $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Your Comment</label>
                <textarea name="content" class="form-control" rows="4" required><?= htmlspecialchars($review['content']) ?></textarea>
            </div>

            <button type="button" id="saveChangesBtn" class="btn btn-success">
                <i class="fas fa-save me-1"></i> Update Review
            </button>
            <a href="my-reviews.php" class="btn btn-secondary ms-2">
                <i class="fas fa-arrow-left me-1"></i> Back to My Reviews
            </a>
        </form>
    </div>
</div>

<script>
document.getElementById('saveChangesBtn').addEventListener('click', () => {
    Swal.fire({
        title: 'Are you sure?',
        icon: 'question',
        html: `You are about to <b>update</b> your review.<br>
              <?php if ($review['status'] === 'approved') echo 'This will send it again for instructor approval.'; ?>`,
        showCancelButton: true,
        confirmButtonText: 'Yes, update it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('editReviewForm').submit();
        }
    });
});
</script>

</body>
</html>