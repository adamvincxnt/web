<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/login.php');
    exit;
}
