<?php
$host = "localhost";
$user = "root";  // Change this if using a different user
$password = "";  // Change this if using a different password
$database = "bc190203051lms";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>