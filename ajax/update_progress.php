<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$enrollment_id = $data['enrollment_id'] ?? null;
$lesson_id = $data['lesson_id'] ?? null;
$percent = $data['progress_percent'] ?? 0;
$is_completed = $data['is_completed'] ?? false;

if (!$enrollment_id || !$lesson_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

// 🧠 Check if row exists
$check = $conn->prepare("SELECT id FROM user_lesson WHERE enrollment_id = ? AND lesson_id = ?");
$check->bind_param("ii", $enrollment_id, $lesson_id);
$check->execute();
$res = $check->get_result();

$now = date('Y-m-d H:i:s');
if ($res->num_rows > 0) {
    $update = $conn->prepare("UPDATE user_lesson SET progress_percent = ?, is_completed = ?, completed_at = ?, last_watched_at = ? WHERE enrollment_id = ? AND lesson_id = ?");
    $completed_at = $is_completed ? $now : null;
    $update->bind_param("iisiii", $percent, $is_completed, $completed_at, $now, $enrollment_id, $lesson_id);
    $update->execute();
} else {
    $insert = $conn->prepare("INSERT INTO user_lesson (enrollment_id, lesson_id, progress_percent, is_completed, completed_at, last_watched_at) VALUES (?, ?, ?, ?, ?, ?)");
    $completed_at = $is_completed ? $now : null;
    $insert->bind_param("iiisss", $enrollment_id, $lesson_id, $percent, $is_completed, $completed_at, $now);
    $insert->execute();
}

echo json_encode(['status' => 'success']);