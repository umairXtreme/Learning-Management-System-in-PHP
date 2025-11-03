<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit();
}

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['action'])) {
    $id = intval($_POST['review_id']);
    $action = $_POST['action'];

    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ? AND is_deleted = 0");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $_SESSION['success'] = "✅ Review $status successfully.";
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $_SESSION['success'] = "🗑️ Review permanently deleted.";
    }    

    header("Location: manage-reviews.php");
    exit();
}

// Filters
$where = "WHERE r.is_deleted = 0";
$params = [];
$types = '';

if (!empty($_GET['course'])) {
    $where .= " AND c.title LIKE ?";
    $params[] = "%" . $_GET['course'] . "%";
    $types .= 's';
}
if (!empty($_GET['rating'])) {
    $where .= " AND r.rating = ?";
    $params[] = intval($_GET['rating']);
    $types .= 'i';
}
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $where .= " AND DATE(r.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= 'ss';
}

$perPage = isset($_GET['limit']) && in_array($_GET['limit'], ['10','30','50','all']) ? $_GET['limit'] : '10';
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ($perPage === 'all' ? PHP_INT_MAX : (int)$perPage);

$count_sql = "SELECT COUNT(*) FROM reviews r JOIN courses c ON r.course_id = c.id $where";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_reviews);
$count_stmt->fetch();
$count_stmt->close();

$sql = "SELECT r.id, r.rating, r.content, r.status, c.title AS course_title, u.full_name 
        FROM reviews r 
        JOIN courses c ON r.course_id = c.id 
        JOIN users u ON r.user_id = u.id 
        $where
        ORDER BY r.created_at DESC";

if ($perPage !== 'all') $sql .= " LIMIT $offset, $perPage";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_pages = $perPage === 'all' ? 1 : ceil($total_reviews / (int)$perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reviews - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* 🌐 Base Body Layout */
body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
    overflow-x: hidden; /* 🔐 Prevent unwanted horizontal scroll */
}

/* 📂 Content Wrapper */
.admin-container {
    margin-left: 250px;
    padding: 30px;
    background-color: #f8f9fa;
    min-height: 100vh;
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
/* 📱 Responsive Fix for Mobile */
@media (max-width: 768px) {
    .admin-container {
        margin-left: 0;
        padding: 20px 15px;
    }
}
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-comments me-2"></i> Manage Reviews</h3>
        <a href="add-review.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add New Review</a>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <input type="text" name="course" class="form-control" placeholder="Course Title" value="<?= $_GET['course'] ?? '' ?>">
        </div>
        <div class="col-md-2">
            <select name="rating" class="form-select form-control">
                <option value="">Rating</option>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ($_GET['rating'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?> Star</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="date" name="from" class="form-control" value="<?= $_GET['from'] ?? '' ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="to" class="form-control" value="<?= $_GET['to'] ?? '' ?>">
        </div>
        <div class="col-md-1">
            <select name="limit" class="form-select form-control">
                <?php foreach (['10','30','50','all'] as $l): ?>
                    <option value="<?= $l ?>" <?= ($perPage == $l) ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-dark w-100"><i class="fas fa-filter"></i> Filter</button>
        </div>
    </form>

    <table class="table table-bordered table-hover">
        <thead class="table-dark">
            <tr>
                <th>Course</th>
                <th>User</th>
                <th>Rating</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['course_title']) ?></td>
                    <td><?= htmlspecialchars($r['full_name']) ?></td>
                    <td><?= str_repeat("⭐", $r['rating']) . str_repeat("☆", 5 - $r['rating']) ?></td>
                    <td>
                        <?php
                        $status = strtolower(trim($r['status'] ?? 'Pending'));
                        $badge = match ($status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            'pending' => 'warning',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($status) ?></span>
                    </td>
                    <td>
                        <a href="view-review.php?id=<?= $r['id'] ?>" class="text-primary me-2" title="View"><i class="fas fa-eye"></i></a>
                        <a href="edit-review.php?id=<?= $r['id'] ?>" class="text-warning me-2" title="Edit"><i class="fas fa-edit"></i></a>

                        <div class="dropdown d-inline">
                            <a href="#" role="button" id="dropdownMenu<?= $r['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenu<?= $r['id'] ?>">
                                <form method="POST" class="dropdown-item p-0">
                                    <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="approve" class="dropdown-item">✅ Approve</button>
                                    <button type="submit" name="action" value="reject" class="dropdown-item">❌ Reject</button>
                                    <button type="button" class="dropdown-item text-danger" onclick="confirmDelete(<?= $r['id'] ?>)">🗑️ Delete</button>
                                </form>
                            </ul>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="5" class="text-center">No reviews found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4 d-flex justify-content-end">
            <ul class="pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
</div>
<!-- SweetAlert2 logic -->
<script>
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This will permanently delete the review!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement("form");
            form.method = "POST";
            form.style.display = "none";
            const idInput = document.createElement("input");
            idInput.type = "hidden";
            idInput.name = "review_id";
            idInput.value = id;
            form.appendChild(idInput);
            const actionInput = document.createElement("input");
            actionInput.type = "hidden";
            actionInput.name = "action";
            actionInput.value = "delete";
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    });
}

<?php if (isset($_SESSION['success'])): ?>
document.addEventListener("DOMContentLoaded", () => {
    Swal.fire({
        icon: 'success',
        title: <?= json_encode($_SESSION['success']) ?>,
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false
    });
});
<?php unset($_SESSION['success']); endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>