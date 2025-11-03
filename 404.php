<?php
session_start();

// 👀 Basic heuristic detection for SQL injection / hacking attempts
$attack_signatures = ['UNION', 'SELECT', 'DROP', '--', '#', 'OR 1=1', 'INSERT', 'xp_', 'sleep(', 'benchmark(', '<script', 'onerror', '../', '%27'];
$request_string = $_SERVER['REQUEST_URI'] . ' ' . file_get_contents('php://input');

$is_attack = false;
foreach ($attack_signatures as $sig) {
    if (stripos($request_string, $sig) !== false) {
        $is_attack = true;
        break;
    }
}

// 🧑 Redirect users to dashboard or homepage
$user_id = $_SESSION['user_id'] ?? null;
$dashboard_url = $user_id ? '/dashboard.php' : '/index.php';

$title = $is_attack ? "⚠ Intrusion Detected!" : "🚫 404 - Page Not Found";
$subtitle = $is_attack 
    ? "Suspicious activity detected. Your request triggered our security systems. 🛡️"
    : "The page you are looking for doesn’t exist or has been moved.";
$icon = $is_attack ? "fa-user-secret text-danger" : "fa-exclamation-triangle text-warning";
$bg_class = $is_attack ? "bg-dark text-danger" : "bg-light text-dark";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $title ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      <?= $is_attack ? "background-color: #000;" : "background-color: #f8f9fa;"; ?>
    }
    .card {
      max-width: 600px;
      padding: 2rem;
    }
    .icon-big {
      font-size: 5rem;
    }
    .btn-group .btn {
      min-width: 130px;
    }
  </style>
</head>
<?php include '../includes/header.php'; ?>
<body class="<?= $bg_class ?>">
  <div class="card shadow text-center <?= $is_attack ? 'border-danger' : 'border-warning' ?>">
    <div class="card-body">
      <i class="fas <?= $icon ?> icon-big mb-3"></i>
      <h1 class="card-title"><?= $title ?></h1>
      <p class="card-text lead"><?= $subtitle ?></p>
      <div class="btn-group mt-4">
        <a href="/" class="btn btn-outline-primary"><i class="fas fa-home"></i> Homepage</a>
        <a href="<?= $dashboard_url ?>" class="btn btn-outline-secondary"><i class="fas fa-user"></i> Dashboard</a>
      </div>
    </div>
  </div>
</body>
</html>