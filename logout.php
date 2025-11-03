<?php
session_start();
session_destroy();
header("Location: validate/login.php");
exit();
?>