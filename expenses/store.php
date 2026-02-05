<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) exit;

$stmt = $pdo->prepare("
    INSERT INTO expenses
    (category, title, description, amount, expense_date, router_id, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $_POST['category'],
    $_POST['title'],
    $_POST['description'] ?? null,
    $_POST['amount'],
    $_POST['expense_date'],
    $_POST['router_id'] ?: null,
    $_SESSION['user']['id']
]);

header("Location: index.php");
exit;
