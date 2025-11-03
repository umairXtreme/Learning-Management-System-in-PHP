<?php
$password = "Cyb3r@123"; // The new admin password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
echo "Hashed Password: " . $hashedPassword;
?>