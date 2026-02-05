<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['role'] !== 'admin') exit;

$id = $_GET['id'];

$pdo->prepare("
    UPDATE users
    SET status = IF(status='active','disabled','active')
    WHERE user_id=?
")->execute([$id]);

header("Location: index.php");
exit;
