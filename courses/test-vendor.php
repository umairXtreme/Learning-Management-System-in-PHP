<?php
$autoload = '../lib/dompdf/vendor/autoload.php';
if (file_exists($autoload)) {
    echo "✅ autoload.php found!";
} else {
    echo "❌ autoload.php NOT found at: $autoload";
}