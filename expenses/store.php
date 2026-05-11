<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in()) {
    exit;
}

$user_id   = $_SESSION['user']['id'];
$router_id = $_POST['router_id'] ?: null;

/* Validate router ownership if router selected */
if ($router_id) {

    $check = $pdo->prepare("
        SELECT 1
        FROM user_router_access
        WHERE user_id = ?
        AND router_id = ?
        LIMIT 1
    ");

    $check->execute([$user_id, $router_id]);

    if (!$check->fetch()) {
        die("Unauthorized router selection.");
    }
}

/* Insert expense */
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
    $router_id,
    $user_id
]);

header("Location: index.php?success=1");
exit;