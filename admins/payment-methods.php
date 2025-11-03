<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../validate/login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// Add or Edit Payment Method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? '';
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = $_POST['bank_name'] ?? null;
    $branch_name = $_POST['branch_name'] ?? null;
    $swift_code = $_POST['swift_code'] ?? null;
    $iban = $_POST['iban'] ?? null;
    $billing_address = $_POST['billing_address'] ?? null;

    if (!$account_holder_name || !$account_number || !$method) {
        $errors[] = "All required fields must be filled.";
    }

    if ($method === 'BankTransfer' && (!$bank_name || !$iban)) {
        $errors[] = "Bank Name and IBAN are required for Bank Transfer.";
    }

    if (empty($errors)) {
        if (isset($_POST['edit_method_id']) && is_numeric($_POST['edit_method_id'])) {
            $editId = intval($_POST['edit_method_id']);
            $stmt = $conn->prepare("UPDATE payment_methods SET method=?, account_holder_name=?, account_number=?, bank_name=?, branch_name=?, swift_code=?, iban=?, billing_address=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $method, $account_holder_name, $account_number, $bank_name, $branch_name, $swift_code, $iban, $billing_address, $editId);
            $stmt->execute();
            $_SESSION['toast'] = ['type' => 'updated'];
            header("Location: payment-methods.php");
            exit;
        } elseif (isset($_POST['add_method'])) {
            $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, method, account_holder_name, account_number, bank_name, branch_name, swift_code, iban, billing_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("issssssss", $userId, $method, $account_holder_name, $account_number, $bank_name, $branch_name, $swift_code, $iban, $billing_address);
            $stmt->execute();
            $_SESSION['toast'] = ['type' => 'created'];
            header("Location: payment-methods.php");
            exit;
        }
    }
}

// Toggle status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("SELECT status FROM payment_methods WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $newStatus = $result['status'] === 'active' ? 'inactive' : 'active';
        $stmtToggle = $conn->prepare("UPDATE payment_methods SET status = ? WHERE id = ?");
        $stmtToggle->bind_param("si", $newStatus, $id);
        $stmtToggle->execute();
        $_SESSION['toast'] = ['type' => 'statuschanged'];
        header("Location: payment-methods.php");
        exit();
    }
}

// Delete logic
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmtCount = $conn->prepare("SELECT user_id FROM payment_methods WHERE id = ?");
    $stmtCount->bind_param("i", $id);
    $stmtCount->execute();
    $owner = $stmtCount->get_result()->fetch_assoc();
    if ($owner) {
        $uid = $owner['user_id'];
        $count = $conn->query("SELECT COUNT(*) AS total FROM payment_methods WHERE user_id = $uid")->fetch_assoc()['total'];
        if ($count > 1) {
            $conn->query("DELETE FROM payment_methods WHERE id = $id");
            $_SESSION['toast'] = ['type' => 'deleted'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "You must add another payment method before deleting this one."];
        }
    }
    header("Location: payment-methods.php");
    exit;
}

$adminMethods = $conn->query("SELECT * FROM payment_methods WHERE user_id = $userId ORDER BY created_at DESC");
$allUserMethods = $conn->query("SELECT pm.*, u.full_name FROM payment_methods pm JOIN users u ON u.id = pm.user_id WHERE pm.user_id != $userId ORDER BY pm.created_at DESC");

include '../includes/admin-sidebar.php';

$isEditing = false;
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    if ($editData) {
        $isEditing = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Methods - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI'; overflow-x: hidden; }
        .admin-container { margin-left: 250px; padding: 30px; }
        @media (max-width: 768px) { .admin-container { margin-left: 0; padding: 20px 15px; } }
    </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include '../includes/admin-sidebar.php'; ?>

<div class="admin-container">
<div class="container py-5">
    <h2><i class="fas fa-credit-card me-2"></i> Manage Payment Methods</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>

    <form method="POST" class="border rounded p-4 mb-5">
        <h5><i class="fas fa-<?= $isEditing ? 'edit' : 'plus-circle' ?>"></i> <?= $isEditing ? 'Edit' : 'Add' ?> Payment Method</h5>
        <?php if ($isEditing): ?>
            <input type="hidden" name="edit_method_id" value="<?= $editData['id'] ?>">
        <?php else: ?>
            <input type="hidden" name="add_method" value="1">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <label>Method</label>
                <select name="method" id="methodSelect" class="form-select" required onchange="toggleFields(this.value)">
                    <option value="">-- Select --</option>
                    <?php foreach (['JazzCash', 'EasyPaisa', 'BankTransfer'] as $m): ?>
                        <option value="<?= $m ?>" <?= ($editData['method'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label>Account Holder</label>
                <input type="text" name="account_holder_name" class="form-control" required value="<?= htmlspecialchars($editData['account_holder_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label>Account Number</label>
                <input type="text" name="account_number" class="form-control" required value="<?= htmlspecialchars($editData['account_number'] ?? '') ?>">
            </div>

            <!-- Bank Fields -->
            <div class="bank-fields col-md-6 d-none"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($editData['bank_name'] ?? '') ?>"></div>
            <div class="bank-fields col-md-6 d-none"><label>Branch</label><input type="text" name="branch_name" class="form-control" value="<?= htmlspecialchars($editData['branch_name'] ?? '') ?>"></div>
            <div class="bank-fields col-md-6 d-none"><label>SWIFT</label><input type="text" name="swift_code" class="form-control" value="<?= htmlspecialchars($editData['swift_code'] ?? '') ?>"></div>
            <div class="bank-fields col-md-6 d-none"><label>IBAN</label><input type="text" name="iban" class="form-control" value="<?= htmlspecialchars($editData['iban'] ?? '') ?>"></div>
            <div class="bank-fields col-12 d-none"><label>Billing Address</label><textarea name="billing_address" class="form-control"><?= htmlspecialchars($editData['billing_address'] ?? '') ?></textarea></div>
        </div>

        <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> <?= $isEditing ? 'Update' : 'Save' ?> Method</button>
    </form>

    <h4><i class="fas fa-user-shield"></i> Your Payment Methods</h4>
    <table class="table table-bordered mb-5">
        <thead class="table-dark"><tr><th>Method</th><th>Account</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php while ($row = $adminMethods->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['method']) ?></td>
                <td><?= htmlspecialchars($row['account_holder_name']) ?><br><?= htmlspecialchars($row['account_number']) ?></td>
                <td><a href="?toggle=<?= $row['id'] ?>"><?= $row['status'] === 'active' ? '✅' : '🚫' ?></a></td>
                <td>
                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <button onclick="confirmDeletion(<?= $row['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <h4><i class="fas fa-users"></i> All User Payment Methods</h4>
    <table class="table table-bordered">
        <thead class="table-dark"><tr><th>User</th><th>Method</th><th>Account</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php while ($row = $allUserMethods->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['method']) ?></td>
                <td><?= htmlspecialchars($row['account_holder_name']) ?><br><?= htmlspecialchars($row['account_number']) ?></td>
                <td><a href="?toggle=<?= $row['id'] ?>"><?= $row['status'] === 'active' ? '✅' : '🚫' ?></a></td>
                <td>
                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                    <button onclick="confirmDeletion(<?= $row['id'] ?>)" class="btn btn-sm btn-danger">Delete</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</div>

<script>
function toggleFields(method) {
    document.querySelectorAll('.bank-fields').forEach(el =>
        el.classList.toggle('d-none', method !== 'BankTransfer')
    );
}

function confirmDeletion(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You want to delete this payment method?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!',
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?delete=${id}`;
        }
    });
}

document.addEventListener("DOMContentLoaded", () => {
    toggleFields(document.getElementById('methodSelect').value);

    <?php if (!empty($_SESSION['toast'])):
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
    ?>
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: '<?= $toast['type'] === 'error' ? 'warning' : 'success' ?>',
        title: '<?= $toast['message'] ?? "Payment method {$toast['type']} successfully!" ?>',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true
    });
    <?php endif; ?>
});
</script>
</body>
</html>