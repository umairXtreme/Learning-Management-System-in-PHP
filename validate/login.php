<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/config.php';

// Already logged-in users redirected
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'student':
            header("Location: ../student/student-dashboard.php");
            break;
        case 'instructor':
            header("Location: ../instructor/instructor-dashboard.php");
            break;
        case 'admin':
            header("Location: ../admins/admin-dashboard.php");
            break;
    }
    exit();
}

// Login processing
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, email, password, role, status FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $dbUsername, $email, $hashedPassword, $role, $status);
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            if ($role === "instructor" && $status === "pending") {
                $_SESSION['message'] = ["warning", "Your instructor account is pending approval."];
                header("Location: login.php");
                exit();
            }

            // Login success: Set session
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $dbUsername;
            $_SESSION['role'] = $role;

            // Redirect by role
            switch ($role) {
                case 'student':
                    header("Location: ../student/student-dashboard.php");
                    break;
                case 'instructor':
                    header("Location: ../instructor/instructor-dashboard.php");
                    break;
                case 'admin':
                    header("Location: ../admins/admin-dashboard.php");
                    break;
            }
            exit();
        }
    }

    // If login fails
    $_SESSION['message'] = ["error", "Invalid username or password."];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CyberLMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<!-- Navigation Menu -->
<?php include '../includes/header.php'; ?>

<!-- Background Overlay with Glass Effect -->
<div class="login-bg">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="login-box p-4 shadow-lg">
            
            <!-- Logo -->
            <div class="text-center">
                <img src="https://res.cloudinary.com/dv9mwju2y/image/upload/v1741767432/Logowhitebackground_fdnd94.png" alt="CyberLMS Logo" class="login-logo">
                <h2 class="mt-3">Login</h2>
            </div>

            <!-- Display Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message'][0]; ?> text-center">
                    <?php echo $_SESSION['message'][1]; ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="Enter Your Username...">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Enter your Password...">
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <p class="text-center mt-3">Don't have an account? <a href="register.php">Register</a></p>
        </div>
    </div>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer Section of All Pages -->
<?php include '../includes/footer.php'; ?>
</body>
</html>