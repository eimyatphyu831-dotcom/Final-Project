<?php
session_start();
$role = $_SESSION['user_role'] ?? 'user';
session_destroy();

if ($role === 'admin') {
    header("Location: login.php");
} else {
    header("Location: ../users/index.php");
}
exit();
?>