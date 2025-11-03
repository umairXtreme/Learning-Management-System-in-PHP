<?php
session_start();
require_once '../config/config.php';

// 🚫 Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

$user_id = $_SESSION['user_id'] ?? null;
$lesson_id = $_POST['lesson_id'] ?? null;
$enrollment_id = $_POST['enrollment_id'] ?? null;

// 🔐 Input validation
if (!$user_id || !$lesson_id || !$enrollment_id || !is_numeric($lesson_id) || !is_numeric($enrollment_id)) {
    die("Invalid request.");
}

// 🔎 Validate enrollment ownership
$verify_stmt = $conn->prepare("SELECT * FROM enrollments WHERE id = ? AND user_id = ?");
$verify_stmt->bind_param("ii", $enrollment_id, $user_id);
$verify_stmt->execute();
$enrollment = $verify_stmt->get_result()->fetch_assoc();
if (!$enrollment) die("Unauthorized access.");

$course_id = $enrollment['course_id'];

// 🧠 Check if user_lesson exists
$check = $conn->prepare("SELECT id FROM user_lessons WHERE enrollment_id = ? AND lesson_id = ?");
$check->bind_param("ii", $enrollment_id, $lesson_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // 📝 Mark as completed
    $update = $conn->prepare("
        UPDATE user_lessons 
        SET is_completed = 1, completed_at = NOW(), last_watched_at = NOW(), progress_percent = 100 
        WHERE enrollment_id = ? AND lesson_id = ?
    ");
    $update->bind_param("ii", $enrollment_id, $lesson_id);
    $update->execute();
} else {
    // ➕ Insert completed lesson record
    $insert = $conn->prepare("
        INSERT INTO user_lessons 
        (enrollment_id, lesson_id, is_completed, completed_at, progress_percent, started_at, last_watched_at) 
        VALUES (?, ?, 1, NOW(), 100, NOW(), NOW())
    ");
    $insert->bind_param("ii", $enrollment_id, $lesson_id);
    $insert->execute();
}

// 📊 Recalculate progress
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM lessons WHERE course_id = ?");
$total_stmt->bind_param("i", $course_id);
$total_stmt->execute();
$total = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;

$completed_stmt = $conn->prepare("
    SELECT COUNT(*) as completed 
    FROM user_lessons 
    WHERE enrollment_id = ? AND is_completed = 1
");
$completed_stmt->bind_param("i", $enrollment_id);
$completed_stmt->execute();
$completed = $completed_stmt->get_result()->fetch_assoc()['completed'] ?? 0;

$progress = $total > 0 ? min(100, intval(round(($completed / $total) * 100))) : 0;

// 🔁 Update enrollments progress
$update_enroll = $conn->prepare("
    UPDATE enrollments 
    SET progress = ?, 
        completed_at = CASE WHEN ? = 100 THEN NOW() ELSE completed_at END 
    WHERE id = ?
");
$update_enroll->bind_param("iii", $progress, $progress, $enrollment_id);
$update_enroll->execute();

// ✅ Redirect back to learning page
header("Location: learn.php?id=$course_id&lesson=$lesson_id&completed=1");
exit;
?>