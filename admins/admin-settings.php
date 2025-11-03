<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit;
}

$adminId = $_SESSION['user_id'];
$profileDir = "../assets/uploads/profileimages/";
$resumeDir = "../assets/uploads/resume/";

if (!file_exists($profileDir)) mkdir($profileDir, 0775, true);
if (!file_exists($resumeDir)) mkdir($resumeDir, 0775, true);

$stmt = $conn->prepare("SELECT username, full_name, email, profile_picture, password, phone, resume, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_image = $admin['profile_picture'];
    $resume_file = $admin['resume'];

    $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmtCheck->bind_param("si", $email, $adminId);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if ($stmtCheck->num_rows > 0) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "⚠️ Email already exists."];
        header("Location: admin-settings.php");
        exit;
    }

    $password_to_update = null;
    if (!empty($old_password) || !empty($new_password) || !empty($confirm_password)) {
        if (strlen($new_password) < 8) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "🔐 Password must be at least 8 characters."];
            header("Location: admin-settings.php");
            exit;
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "🔑 Passwords do not match."];
            header("Location: admin-settings.php");
            exit;
        } elseif (!password_verify($old_password, $admin['password'])) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Old password is incorrect."];
            header("Location: admin-settings.php");
            exit;
        } else {
            $password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedTypes)) {
            $newFileName = "admin_" . $adminId . "_" . time() . "." . $ext;
            $fullPath = $profileDir . $newFileName;

            if ($profile_image && file_exists($profileDir . $profile_image)) {
                unlink($profileDir . $profile_image);
            }

            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $fullPath);
            $profile_image = $newFileName;
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Only JPG, PNG, GIF allowed for image."];
            header("Location: admin-settings.php");
            exit;
        }
    }

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {
        $allowedResumeExt = ['pdf', 'doc', 'docx'];
        $resumeExt = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        if (in_array($resumeExt, $allowedResumeExt)) {
            $resumeFileName = "resume_" . $adminId . "_" . time() . "." . $resumeExt;
            $resumePath = $resumeDir . $resumeFileName;

            if ($resume_file && file_exists($resumeDir . $resume_file)) {
                unlink($resumeDir . $resume_file);
            }

            move_uploaded_file($_FILES['resume']['tmp_name'], $resumePath);
            $resume_file = $resumeFileName;
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "📄 Only PDF, DOC, DOCX formats allowed."];
            header("Location: admin-settings.php");
            exit;
        }
    }

    $query = "UPDATE users SET full_name = ?, email = ?, profile_picture = ?, phone = ?, resume = ?";
    $params = [$full_name, $email, $profile_image, $phone, $resume_file];
    $types = "sssss";

    if ($password_to_update) {
        $query .= ", password = ?";
        $params[] = $password_to_update;
        $types .= "s";
    }

    $query .= " WHERE id = ?";
    $params[] = $adminId;
    $types .= "i";

    $stmtUpdate = $conn->prepare($query);
    $stmtUpdate->bind_param($types, ...$params);
    if ($stmtUpdate->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => "✅ Profile updated successfully!"];
        header("Location: admin-settings.php");
        exit;
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Failed to update profile."];
        header("Location: admin-settings.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>
<div class="main-content">
    <div class="admin-settings-wrapper">
        <h4 class="mb-4"><i class="fas fa-user-cog me-2"></i> Admin Settings</h4>
        <form method="POST" enctype="multipart/form-data" class="row g-4">
            <div class="col-md-4 text-center">
                <div class="profile-picture-wrapper">
                    <img id="preview" src="<?= $admin['profile_picture'] ? '../assets/uploads/profileimages/' . $admin['profile_picture'] : 'https://via.placeholder.com/170?text=Avatar' ?>" class="img-fluid shadow" alt="Profile Picture">
                    <input type="file" name="profile_picture" class="form-control mt-3" onchange="previewImage(event)">
                </div>
            </div>
            <div class="col-md-8">
                <div class="mb-3"><label class="form-label">Username</label><input type="text" class="form-control" value="<?= htmlspecialchars($admin['username']) ?>" disabled></div>
                <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($admin['full_name']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required></div>
                <div class="mb-3"><label class="form-label">Phone Number</label><input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($admin['phone']) ?>" required></div>
                <div class="mb-3">
                    <label class="form-label">Upload Resume</label>
                    <input type="file" class="form-control" name="resume">
                    <?php if ($admin['resume']): ?>
                        <a href="<?= $resumeDir . $admin['resume'] ?>" target="_blank" class="text-success d-block mt-1">📄 View Current Resume</a>
                    <?php endif; ?>
                </div>
                <div class="mb-3"><label class="form-label">Account Created At</label><input type="text" class="form-control" value="<?= htmlspecialchars($admin['created_at']) ?>" disabled></div>
                <hr>
                <div class="mb-3"><label class="form-label">Old Password</label><input type="password" class="form-control" name="old_password"></div>
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password"></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password"></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Settings</button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function () {
        document.getElementById('preview').src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}

document.addEventListener("DOMContentLoaded", () => {
    <?php if (!empty($_SESSION['toast'])):
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
    ?>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: '<?= $toast['type'] === 'error' ? 'warning' : 'success' ?>',
        title: '<?= $toast['message'] ?>',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
    <?php endif; ?>
});
</script>
</body>
</html>