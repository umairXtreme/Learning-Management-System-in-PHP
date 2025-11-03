<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../validate/login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// 🧠 Filters
$where = "e.user_id = ? AND (e.status = 'active' OR (e.status = 'completed' AND e.progress = 100))";
$params = [$student_id];
$types = "i";

// Date filter
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(e.enrolled_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= "ss";
}

// Price filter
if (!empty($_GET['min_price'])) {
    $where .= " AND c.price >= ?";
    $params[] = $_GET['min_price'];
    $types .= "d";
}
if (!empty($_GET['max_price'])) {
    $where .= " AND c.price <= ?";
    $params[] = $_GET['max_price'];
    $types .= "d";
}

// Instructor filter
if (!empty($_GET['instructor'])) {
    $where .= " AND i.full_name LIKE ?";
    $params[] = "%" . $_GET['instructor'] . "%";
    $types .= "s";
}

// Pagination setup
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Count total
$count_sql = "
    SELECT COUNT(*) 
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users i ON c.instructor_id = i.id
    WHERE $where
";

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($totalRows);
$count_stmt->fetch();
$count_stmt->close();

$totalPages = ceil($totalRows / $perPage);

// Fetch courses
$data_sql = "
    SELECT c.title, c.category, c.price, c.id AS course_id, c.created_at,
           e.enrolled_at, e.progress, i.full_name AS instructor_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    JOIN users i ON c.instructor_id = i.id
    WHERE $where
    ORDER BY e.enrolled_at DESC
    LIMIT ?, ?
";
$params[] = $offset;
$params[] = $perPage;
$types .= "ii";

$data_stmt = $conn->prepare($data_sql);
$data_stmt->bind_param($types, ...$params);
$data_stmt->execute();
$result = $data_stmt->get_result();
$courses = $result->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Enrolled Courses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
}
.form-control {
    border-radius: 6px;
    border: 1px solid #0d6efd;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: border 0.2s, box-shadow 0.2s;
}

.form-control:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.25);
}
</style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/student-sidebar.php'; ?>

<div class="main-content">
    <div class="container">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <h2 class="mb-0"><i class="fas fa-book-reader"></i> My Enrolled Courses</h2>
    <a href="../courses.php" class="btn btn-success">
        <i class="fas fa-lightbulb"></i> Learn New Skills
    </a>
</div>
        <!-- 🔍 Filters -->
        <form class="row g-3 mb-4" method="get">
            <div class="col-md-3">
                <input type="date" name="from" class="form-control" placeholder="From Date" value="<?= $_GET['from'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <input type="date" name="to" class="form-control" placeholder="To Date" value="<?= $_GET['to'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" name="min_price" class="form-control" placeholder="Min Price" value="<?= $_GET['min_price'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <input type="number" step="0.01" name="max_price" class="form-control" placeholder="Max Price" value="<?= $_GET['max_price'] ?? '' ?>">
            </div>
            <div class="col-md-2">
                <input type="text" name="instructor" class="form-control" placeholder="Instructor" value="<?= $_GET['instructor'] ?? '' ?>">
            </div>
            <div class="col-12">
                <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Apply Filters</button>
            </div>
        </form>

        <!-- 📋 Course Table -->
        <?php if (empty($courses)): ?>
            <p>No enrolled courses found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Title</th>
                            <th>Instructor</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Enrolled At</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['title']) ?></td>
                                <td><?= htmlspecialchars($c['instructor_name']) ?></td>
                                <td><?= htmlspecialchars($c['category']) ?></td>
                                <td>$<?= number_format($c['price'], 2) ?></td>
                                <td><?= date('d M Y', strtotime($c['enrolled_at'])) ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?= $c['progress'] ?>%;" aria-valuenow="<?= $c['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= $c['progress'] ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
  <?php
    $status = $c['progress'] == 100 ? 'Completed' : 'Active';
    $badge = $status === 'Completed' ? 'success' : 'primary';
  ?>
  <span class="badge bg-<?= $badge ?>"><?= $status ?></span>
</td>

                                <td>
                                    <a href="../courses/learn.php?id=<?= $c['course_id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-play"></i> Continue
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 🔁 Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center mt-4">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>