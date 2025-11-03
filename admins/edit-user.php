<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: ../admins/manage-users.php");
    exit();
}

$user_id = intval($_GET['id']);

$query = "SELECT id, username, email, role, profile_picture, resume, status FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['message'] = ["error", "User not found."];
    header("Location: ../admins/manage-users.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_OFF); // Prevent fatal errors for duplicate keys

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $status = $_POST['status'];

        if (empty($username) || empty($email)) {
            $_SESSION['message'] = ["error", "Username and Email are required."];
            header("Location: edit-user.php?id=$user_id");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message'] = ["error", "Invalid email format."];
            header("Location: edit-user.php?id=$user_id");
            exit();
        }

        $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, status=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $email, $role, $status, $user_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = ["success", "User updated successfully!"];
        } else {
            if ($conn->errno == 1062) {
                $_SESSION['message'] = ["error", "Username or Email already exists."];
            } else {
                $_SESSION['message'] = ["error", "Failed to update user: " . $conn->error];
            }
        }

        header("Location: edit-user.php?id=$user_id");
        exit();
    }

    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 8) {
            $_SESSION['message'] = ["error", "New password must be at least 8 characters."];
            header("Location: edit-user.php?id=$user_id");
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['message'] = ["error", "Passwords do not match."];
            header("Location: edit-user.php?id=$user_id");
            exit();
        }

        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $user_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = ["success", "Password reset successfully!"];
        } else {
            $_SESSION['message'] = ["error", "Error resetting password."];
        }

        header("Location: edit-user.php?id=$user_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit User - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap & FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    /* 🌐 Base Layout */
    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f8f9fa;
    }
  </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      <h2 class="mb-4 text-center">Edit User Details</h2>

      <!-- Update User Form -->
      <div class="card mb-4 shadow-sm">
        <div class="card-header fw-semibold">User Details</div>
        <div class="card-body">
          <form method="POST">
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                  <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                  <option value="instructor" <?= $user['role'] === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                  <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <option value="approved" <?= $user['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                  <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
              </div>
            </div>

            <div class="text-end">
              <button type="submit" name="update_user" class="btn btn-primary px-4">Update User</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Reset Password -->
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Reset Password</div>
        <div class="card-body">
          <form method="POST">
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required placeholder="New Password">
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm Password">
              </div>
            </div>
            <div class="text-end">
              <button type="submit" name="reset_password" class="btn btn-warning px-4">Reset Password</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($_SESSION['message'])): ?>
<script>
document.addEventListener("DOMContentLoaded", () => {
  Swal.fire({
    icon: <?= json_encode($_SESSION['message'][0]) ?>,
    title: <?= json_encode($_SESSION['message'][1]) ?>,
    toast: true,
    position: 'top-end',
    timer: 4000,
    timerProgressBar: true,
    showConfirmButton: false
  });
});
</script>
<?php unset($_SESSION['message']); endif; ?>

</body>
</html>