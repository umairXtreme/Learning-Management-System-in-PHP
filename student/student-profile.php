<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit;
}

$studentId = $_SESSION['user_id'];
$profileDir = "../assets/uploads/profileimages/";
if (!file_exists($profileDir)) mkdir($profileDir, 0775, true);

// 🧠 Load student info
$stmt = $conn->prepare("SELECT username, full_name, email, profile_picture, password, phone, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_image = $student['profile_picture'];

    // 📧 Email check
    $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmtCheck->bind_param("si", $email, $studentId);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if ($stmtCheck->num_rows > 0) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "⚠️ Email already exists."];
        header("Location: student-profile.php");
        exit;
    }

    // 🔐 Password Update
    $password_to_update = null;
    if (!empty($old_password) || !empty($new_password) || !empty($confirm_password)) {
        if (strlen($new_password) < 8) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "🔐 Password must be at least 8 characters."];
            header("Location: student-profile.php");
            exit;
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "🔑 Passwords do not match."];
            header("Location: student-profile.php");
            exit;
        } elseif (!password_verify($old_password, $student['password'])) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Old password is incorrect."];
            header("Location: student-profile.php");
            exit;
        } else {
            $password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }

    // 📸 Profile Image
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedTypes)) {
            $newFileName = "student_" . $studentId . "_" . time() . "." . $ext;
            $fullPath = $profileDir . $newFileName;

            if ($profile_image && file_exists($profileDir . $profile_image)) {
                unlink($profileDir . $profile_image);
            }

            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $fullPath);
            $profile_image = $newFileName;
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Only JPG, PNG, GIF allowed for image."];
            header("Location: student-profile.php");
            exit;
        }
    }

    // ✅ Final Update
    $query = "UPDATE users SET full_name = ?, email = ?, profile_picture = ?, phone = ?";
    $params = [$full_name, $email, $profile_image, $phone];
    $types = "ssss";

    if ($password_to_update) {
        $query .= ", password = ?";
        $params[] = $password_to_update;
        $types .= "s";
    }

    $query .= " WHERE id = ?";
    $params[] = $studentId;
    $types .= "i";

    $stmtUpdate = $conn->prepare($query);
    $stmtUpdate->bind_param($types, ...$params);
    if ($stmtUpdate->execute()) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => "✅ Profile updated successfully!"];
        header("Location: student-profile.php");
        exit;
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "❌ Failed to update profile."];
        header("Location: student-profile.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content container">
    <div class="profile-wrapper">
        <h4 class="mb-4"><i class="fas fa-user-cog"></i> Student Profile Settings</h4>

        <form method="POST" enctype="multipart/form-data" class="row g-4">
            <div class="col-md-4 text-center">
                <div class="profile-picture-wrapper">
                    <img id="preview" src="<?= $student['profile_picture'] ? '../assets/uploads/profileimages/' . $student['profile_picture'] : 'https://via.placeholder.com/170?text=Avatar' ?>" class="img-fluid rounded-circle shadow" width="170" height="170" alt="Profile Picture">
                    <input type="file" name="profile_picture" class="form-control mt-3" onchange="previewImage(event)">
                </div>
            </div>
            <div class="col-md-8">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['username']) ?>" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($student['full_name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($student['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($student['phone']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Account Created At</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($student['created_at']) ?>" disabled>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Old Password</label>
                    <input type="password" class="form-control" name="old_password">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="new_password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
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