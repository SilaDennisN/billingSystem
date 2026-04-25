<?php
/* toggle.php is kept for backwards compatibility with any direct links,
   but all new UI uses store.php?action=toggle_status via modal form. */

require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

$me       = (int)$_SESSION['user']['id'];
$targetId = (int)($_GET['id'] ?? 0);

if (!$targetId || $targetId === $me) {
    header("Location: users");
    exit;
}

/* Scope guard: target must share a router with me */
$stmt = $pdo->prepare("
    SELECT 1 FROM user_router_access ura
    WHERE ura.user_id = ?
    AND ura.router_id IN (
        SELECT router_id FROM user_router_access WHERE user_id = ?
    )
    LIMIT 1
");
$stmt->execute([$targetId, $me]);

if (!$stmt->fetch()) {
    header("Location: users?error=unauthorized");
    exit;
}

$pdo->prepare("
    UPDATE users
    SET status = IF(status='active','disabled','active')
    WHERE user_id = ?
")->execute([$targetId]);

header("Location: users?success=toggled");
exit;