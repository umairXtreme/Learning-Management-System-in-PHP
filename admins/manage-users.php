<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

$query = "SELECT id, username, full_name, email, role, status FROM users ORDER BY role, id DESC";
$result = $conn->query($query);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = preg_replace("/[^a-zA-Z0-9]/", "", trim($_POST['username']));
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count === 0) {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $email, $password, $role, $status);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['success', 'User added successfully!'];
        } else {
            $_SESSION['message'] = ['error', 'Failed to add user. Please try again.'];
        }
    } else {
        $_SESSION['message'] = ['error', 'Username or email already taken. Please try another one.'];
    }
}
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    if ($_SESSION['user_id'] != $delete_id) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $_SESSION['message'] = ['success', 'User deleted successfully'];
    } else {
        $_SESSION['message'] = ['error', 'You cannot delete your own account'];
    }

    header("Location: ../admins/manage-users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/manage-users.css">
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="dashboard-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Users</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus"></i> Add New User
        </button>
    </div>

    <!-- Search Bar -->
    <div class="mb-3">
        <input type="text" id="userSearch" class="form-control" placeholder="🔍 Search users by name, email, role...">
    </div>

    <!-- User Table -->
    <h4 class="mt-4">All Users</h4>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $user['id']; ?></td>
                        <td><?= $user['username']; ?></td>
                        <td><?= htmlspecialchars($user['full_name']); ?></td>
                        <td><?= $user['email']; ?></td>
                        <td><?= ucfirst($user['role']); ?></td>
                        <td>
                            <span class="badge bg-<?= ($user['status'] == 'approved') ? 'success' : 'warning'; ?>">
                                <?= ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit-user.php?id=<?= $user['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="manage-users.php?delete=<?= $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="manage-users.php" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="student">Student</option>
                    <option value="instructor">Instructor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap + Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // Restrict username input to alphanumeric only
  document.querySelector('input[name="username"]').addEventListener("input", function () {
      this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
  });

  // Search filter
  document.getElementById("userSearch").addEventListener("keyup", function () {
      const query = this.value.toLowerCase();
      document.querySelectorAll("table tbody tr").forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
      });
  });
</script>

<!-- Sweet Alert Message -->
<?php if (isset($_SESSION['message'])): ?>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: '<?= $_SESSION['message'][0] === 'success' ? 'success' : 'error' ?>',
            title: '<?= $_SESSION['message'][1] ?>',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    });
</script>
<?php unset($_SESSION['message']); ?>
<?php endif; ?>
</body>
</html>