<?php
require_once 'config.php'; // Database connection

if (isset($_GET['q'])) {
    $query = trim($_GET['q']);
    
    // Prevent SQL Injection
    $stmt = $conn->prepare("SELECT id, name, image FROM courses WHERE name LIKE ? LIMIT 5");
    $searchTerm = "%".$query."%";
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

    echo json_encode($courses);
}
?>