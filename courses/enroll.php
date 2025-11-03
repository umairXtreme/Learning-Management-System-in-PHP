<?php
session_start();
require_once '../config/config.php';
require_once '../lib/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 🔐 Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$course_id = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
if ($course_id <= 0) {
    die("Invalid course selection.");
}

// 📚 Fetch Course Info
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
if (!$course) die("Course not found.");

// 🔁 Check for Existing Enrollment
$check_stmt = $conn->prepare("SELECT 1 FROM enrollments WHERE course_id = ? AND user_id = ?");
$check_stmt->bind_param("ii", $course_id, $user_id);
$check_stmt->execute();
$check_stmt->store_result();
if ($check_stmt->num_rows > 0) {
    header("Location: learn.php?id=$course_id");
    exit;
}

// 🆔 Generate Unique Enrollment ID
$enrollment_id = sprintf("ENR%s%s%s%s", date("Y"), $course_id, $user_id, rand(1000, 9999));

// 💰 Determine Course Price and Status
$price = (float) $course['price'];
$amount_formatted = number_format($price, 2, '.', '');
$status = ($price <= 0.00) ? 'approved' : 'pending'; // "pending" = waiting for admin confirmation

// 🧾 Insert Enrollment Record into DB
$insert_stmt = $conn->prepare("
    INSERT INTO enrollments (user_id, course_id, enrollment_id, status, enrolled_at)
    VALUES (?, ?, ?, ?, NOW())
");
$insert_stmt->bind_param("iiss", $user_id, $course_id, $enrollment_id, $status);
$insert_stmt->execute();

// 📄 Generate PDF Invoice
generateInvoice($course, $user_id, $enrollment_id, $price, ucfirst($status));

// ✅ Free Course Logic: Auto-approve + Redirect
if ($status === 'approved') {
    $_SESSION['success_message'] = "You have successfully enrolled in {$course['title']}!";
    header("Location: learn.php?id=$course_id");
    exit;
}

// 💳 Paid Course → Redirect to payments.php with session payload
$_SESSION['pending_payment'] = [
    'user_id'       => $user_id,
    'course_id'     => $course_id,
    'enrollment_id' => $enrollment_id,
    'amount'        => $amount_formatted,
    'course_title'  => $course['title']
];

header("Location: payments.php");
exit;

// 📦 PDF Generator
function generateInvoice($course, $user_id, $enrollment_id, $amount, $status)
{
    $date = date("Y-m-d H:i");
    $title = htmlspecialchars($course['title']);
    $amount_formatted = number_format($amount, 2);

    $html = <<<HTML
<style>
    body { font-family: DejaVu Sans, sans-serif; }
    h2 { text-align: center; color: #4CAF50; }
    p { margin: 5px 0; }
</style>
<h2>Course Enrollment Invoice</h2>
<p><strong>Enrollment ID:</strong> $enrollment_id</p>
<p><strong>Course:</strong> $title</p>
<p><strong>User ID:</strong> $user_id</p>
<p><strong>Status:</strong> $status</p>
<p><strong>Amount:</strong> \$$amount_formatted</p>
<p><strong>Date:</strong> $date</p>
<hr>
<p style="font-size:12px;">This invoice is automatically generated for your records.</p>
HTML;

    $options = new Options();
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $output = $dompdf->output();
    $file_path = "../courses/invoices/invoice-{$enrollment_id}.pdf";
    file_put_contents($file_path, $output);
}
?>