<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, 'approved')");
    $stmt->bind_param("ssss", $username, $email, $password, $role);

    if ($stmt->execute()) {
        $_SESSION['message'] = ["success", "User added successfully!"];
    } else {
        $_SESSION['message'] = ["error", "Failed to add user."];
    }

    header("Location: manage-users.php");
    exit();
}
?>

<form action="add-user.php" method="POST">
    <label>Username:</label>
    <input type="text" name="username" required>
    
    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required>

    <label>Role:</label>
    <select name="role">
        <option value="admin">Admin</option>
        <option value="moderator">Moderator</option>
        <option value="instructor">Instructor</option>
        <option value="student">Student</option>
    </select>

    <button type="submit">Add User</button>
</form>