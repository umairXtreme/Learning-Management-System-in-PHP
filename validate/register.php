<?php
session_start();
require_once '../config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Validate password length
    if (strlen($password) < 8) {
        $_SESSION['message'] = ["error", "Password must be at least 8 characters long."];
        header("Location: ../validate/register.php");
        exit();
    }

    // Check if username or email already exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count > 0) {
    $_SESSION['message'] = ["error", "Username or email already exists."];
    header("Location: ../validate/register.php");
    exit();
}

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $resumePath = NULL;

    // Handle instructor resume upload
    if ($role == "instructor" && isset($_FILES['resume']) && $_FILES['resume']['size'] > 0) {
        $resumePath = "uploads/resumes/" . time() . "_" . basename($_FILES["resume"]["name"]);
        move_uploaded_file($_FILES["resume"]["tmp_name"], $resumePath);
    }

    // Default status: students are auto-approved, instructors require admin approval
    $status = ($role == "instructor") ? "pending" : "approved";

    // Insert user into the database
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, resume, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $email, $hashedPassword, $role, $resumePath, $status);

    if ($stmt->execute()) {
        $_SESSION['message'] = ["success", "Registration successful! You can now log in."];
    } else {
        $_SESSION['message'] = ["error", "Registration failed. Try again."];
    }
    
    header("Location: ../validate/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CyberLMS</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>

<!-- Navigation Menu -->
<?php include '../includes/header.php'; ?>

<div class="register-bg">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="register-box p-4 shadow-lg">
            
            <!-- Logo -->
            <div class="text-center">
                <img src="https://res.cloudinary.com/dv9mwju2y/image/upload/v1741767432/Logowhitebackground_fdnd94.png" alt="CyberLMS Logo" class="register-logo">
                <h2 class="mt-3">Register for CyberLMS</h2>
            </div>

            <!-- Display Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message'][0]; ?> text-center">
                    <?php echo $_SESSION['message'][1]; ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <!-- Tabs for Student & Instructor Registration -->
            <ul class="nav nav-tabs" id="registerTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab">Register as Student</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="instructor-tab" data-bs-toggle="tab" data-bs-target="#instructor" type="button" role="tab">Register as Instructor</button>
                </li>
            </ul>

            <div class="tab-content mt-3">
                <!-- Student Registration Form -->
                <div class="tab-pane fade show active" id="student" role="tabpanel">
                    <form action="register.php" method="POST">
                        <input type="hidden" name="role" value="student">
                        
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

                        <button type="submit" class="btn btn-primary w-100">Register as Student</button>
                        <p class="text-center mt-3">Already have an account? <a href="login.php">Login</a></p>
                    </form>
                </div>

                <!-- Instructor Registration Form -->
                <div class="tab-pane fade" id="instructor" role="tabpanel">
                    <form action="register.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="role" value="instructor">
                        
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
                            <label class="form-label">Upload Resume (PDF/DOC)</label>
                            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx" required>
                        </div>

                        <button type="submit" class="btn btn-warning w-100">Register as Instructor</button>
                        <p class="text-center mt-3">Already have an account? <a href="login.php">Login</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Footer Section of All Pages -->
<?php include '../includes/footer.php'; ?>
</body>
</html>