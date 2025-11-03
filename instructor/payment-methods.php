<?php
session_start();
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../validate/login.php");
    exit;
}

$instructorId = $_SESSION['user_id'];
$errors = [];

// Count active methods
$activeCount = $conn->query("SELECT COUNT(*) AS total FROM payment_methods WHERE user_id = $instructorId AND status = 'active'")
    ->fetch_assoc()['total'] ?? 0;

// === Handle Add/Edit ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? '';
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = $_POST['bank_name'] ?? null;
    $branch_name = $_POST['branch_name'] ?? null;
    $swift_code = $_POST['swift_code'] ?? null;
    $iban = $_POST['iban'] ?? null;
    $billing_address = $_POST['billing_address'] ?? null;

    if (!$method || !$account_holder_name || !$account_number) {
        $errors[] = "Required fields are missing.";
    }

    if ($method === 'BankTransfer' && (!$bank_name || !$iban)) {
        $errors[] = "Bank Name and IBAN are required for Bank Transfer.";
    }

    if (!empty($_POST['edit_method_id'])) {
        $editId = intval($_POST['edit_method_id']);
        $stmt = $conn->prepare("UPDATE payment_methods SET method = ?, account_holder_name = ?, account_number = ?, bank_name = ?, branch_name = ?, swift_code = ?, iban = ?, billing_address = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssssssssii", $method, $account_holder_name, $account_number, $bank_name, $branch_name, $swift_code, $iban, $billing_address, $editId, $instructorId);
        $stmt->execute();
        header("Location: payment-methods.php?updated=1");
        exit;
    }

    if (!empty($_POST['add_method'])) {
        if ($activeCount >= 3) {
            $errors[] = "Maximum of 3 active methods allowed.";
        } else {
            $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, method, account_holder_name, account_number, bank_name, branch_name, swift_code, iban, billing_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("issssssss", $instructorId, $method, $account_holder_name, $account_number, $bank_name, $branch_name, $swift_code, $iban, $billing_address);
            $stmt->execute();
            header("Location: payment-methods.php?created=1");
            exit;
        }
    }
}

// === Toggle Status ===
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("SELECT status FROM payment_methods WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $instructorId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        $newStatus = $result['status'] === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active' && $activeCount >= 3) {
            $errors[] = "Cannot activate more than 3 methods.";
        } elseif ($newStatus === 'inactive' && $activeCount <= 1) {
            $errors[] = "At least one active payment method must exist.";
        } else {
            $stmt = $conn->prepare("UPDATE payment_methods SET status = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $newStatus, $id, $instructorId);
            $stmt->execute();
            header("Location: payment-methods.php?statuschanged=1");
            exit;
        }
    }
}

// === Delete Method ===
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Prevent deletion if only 1 active method remains
    $activeCheck = $conn->prepare("SELECT status FROM payment_methods WHERE id = ? AND user_id = ?");
    $activeCheck->bind_param("ii", $id, $instructorId);
    $activeCheck->execute();
    $result = $activeCheck->get_result()->fetch_assoc();

    if ($result && $result['status'] === 'active' && $activeCount <= 1) {
        $errors[] = "You must have at least one active payment method.";
    } else {
        $conn->query("DELETE FROM payment_methods WHERE id = $id AND user_id = $instructorId");
        header("Location: payment-methods.php?deleted=1");
        exit;
    }
}

// === Fetch All Methods
$methods = $conn->query("SELECT * FROM payment_methods WHERE user_id = $instructorId ORDER BY created_at DESC");

// === Edit Mode
$isEditing = false;
$editData = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM payment_methods WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $editId, $instructorId);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    if ($editData) $isEditing = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Methods</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .admin-container {
      margin-left: 250px;
      padding: 20px;
    }
    @media screen and (max-width: 768px) {
      .admin-container {
        margin-left: 0;
      }
      
    }
  </style>
</head>
<body>

<?php include '../includes/header.php'; ?>
<?php include_once '../includes/instructor-sidebar.php'; ?>

<div class="admin-container">
<div class="container mt-5">
  <h2><i class="fas fa-wallet"></i> Manage Payment Methods</h2>

  <!-- SweetAlerts -->
  <script>
    <?php if (isset($_GET['created'])): ?>
    Swal.fire({ icon: 'success', title: 'Method Added', toast: true, timer: 3000, position: 'top-end', showConfirmButton: false });
    <?php elseif (isset($_GET['updated'])): ?>
    Swal.fire({ icon: 'success', title: 'Method Updated', toast: true, timer: 3000, position: 'top-end', showConfirmButton: false });
    <?php elseif (isset($_GET['deleted'])): ?>
    Swal.fire({ icon: 'warning', title: 'Method Deleted', toast: true, timer: 3000, position: 'top-end', showConfirmButton: false });
    <?php elseif (isset($_GET['statuschanged'])): ?>
    Swal.fire({ icon: 'info', title: 'Status Changed', toast: true, timer: 3000, position: 'top-end', showConfirmButton: false });
    <?php endif; ?>
  </script>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mt-3"><?= implode('<br>', $errors) ?></div>
  <?php endif; ?>

  <form method="POST" class="bg-white p-4 shadow-sm rounded mt-4 mb-5">
    <h5><i class="fas fa-<?= $isEditing ? 'edit' : 'plus-circle' ?>"></i> <?= $isEditing ? 'Edit' : 'Add' ?> Payment Method</h5>
    <?php if ($isEditing): ?>
      <input type="hidden" name="edit_method_id" value="<?= $editData['id'] ?>">
    <?php else: ?>
      <input type="hidden" name="add_method" value="1">
    <?php endif; ?>

    <div class="row g-3 mt-2">
      <div class="col-md-6">
        <label class="form-label">Method</label>
        <select name="method" id="methodSelect" class="form-select" required onchange="toggleFields(this.value)">
          <option value="">-- Select --</option>
          <?php foreach (['JazzCash', 'EasyPaisa', 'BankTransfer'] as $m): ?>
            <option value="<?= $m ?>" <?= ($editData['method'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Account Holder Name</label>
        <input type="text" name="account_holder_name" class="form-control" required value="<?= htmlspecialchars($editData['account_holder_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Account Number</label>
        <input type="text" name="account_number" class="form-control" required value="<?= htmlspecialchars($editData['account_number'] ?? '') ?>">
      </div>

      <!-- Bank Transfer Only -->
      <div class="bank-fields col-md-6 d-none">
        <label class="form-label">Bank Name</label>
        <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($editData['bank_name'] ?? '') ?>">
      </div>
      <div class="bank-fields col-md-6 d-none">
        <label class="form-label">Branch</label>
        <input type="text" name="branch_name" class="form-control" value="<?= htmlspecialchars($editData['branch_name'] ?? '') ?>">
      </div>
      <div class="bank-fields col-md-6 d-none">
        <label class="form-label">SWIFT Code</label>
        <input type="text" name="swift_code" class="form-control" value="<?= htmlspecialchars($editData['swift_code'] ?? '') ?>">
      </div>
      <div class="bank-fields col-md-6 d-none">
        <label class="form-label">IBAN</label>
        <input type="text" name="iban" class="form-control" value="<?= htmlspecialchars($editData['iban'] ?? '') ?>">
      </div>
      <div class="bank-fields col-12 d-none">
        <label class="form-label">Billing Address</label>
        <textarea name="billing_address" class="form-control"><?= htmlspecialchars($editData['billing_address'] ?? '') ?></textarea>
      </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save"></i> <?= $isEditing ? 'Update' : 'Save' ?></button>
  </form>

  <!-- Payment Method List -->
<p class="alert alert-warning d-flex align-items-center" style="font-size: 0.95rem;">
  <i class="fas fa-exclamation-triangle me-2"></i> Click on the&nbsp; <strong>Status</strong> &nbsp;badge to toggle between &nbsp;<em>Active</em> &nbsp;and&nbsp; <em>Disabled</em>.
</p>
  <div class="table-responsive">
    <table class="table table-bordered bg-white shadow-sm">
      <thead class="table-dark">
        <tr>
          <th>Method</th>
          <th>Account</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $methods->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['method']) ?></td>
            <td><?= htmlspecialchars($row['account_holder_name']) ?><br><?= htmlspecialchars($row['account_number']) ?></td>
            <td>
              <a href="?toggle=<?= $row['id'] ?>" title="Toggle Status">
                <span class="badge bg-<?= $row['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($row['status']) ?></span>
              </a>
            </td>
            <td>
              <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>)">Delete</button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
        </div>
<script>
  function toggleFields(method) {
    document.querySelectorAll('.bank-fields').forEach(el => {
      el.classList.toggle('d-none', method !== 'BankTransfer');
    });
  }

  function confirmDelete(id) {
    Swal.fire({
      title: 'Are you sure?',
      text: "You are about to delete this payment method.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      confirmButtonColor: '#d33'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = "?delete=" + id;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    toggleFields(document.getElementById('methodSelect').value);
  });
</script>
</body>
</html>