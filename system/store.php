<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

$me     = (int)$_SESSION['user']['id'];
$action = $_POST['action'] ?? '';

/* ── Helper: get routers the logged-in admin is allowed to assign ── */
function getMyRouterIds(PDO $pdo, int $me): array {
    $s = $pdo->prepare("SELECT router_id FROM user_router_access WHERE user_id = ?");
    $s->execute([$me]);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

/* ── Helper: ensure target user shares at least one router with me (scope guard) ── */
function userInScope(PDO $pdo, int $me, int $targetId): bool {
    if ($me === $targetId) return false; // never act on yourself via store.php
    $myRouters = getMyRouterIds($pdo, $me);
    if (empty($myRouters)) return false;
    $ph   = implode(',', array_fill(0, count($myRouters), '?'));
    $stmt = $pdo->prepare("
        SELECT 1 FROM user_router_access
        WHERE user_id = ? AND router_id IN ($ph)
        LIMIT 1
    ");
    $stmt->execute(array_merge([$targetId], $myRouters));
    return (bool)$stmt->fetch();
}

switch ($action) {

    /* ======================================================
       CREATE USER
    ====================================================== */
    case 'create':
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '') ?: null;
        $role     = in_array($_POST['role'] ?? '', ['staff','admin']) ? $_POST['role'] : 'staff';
        $password = $_POST['password'] ?? '';

        if (!$username || strlen($password) < 6) {
            header("Location: users?error=invalid");
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$username, $email, $hash, $role]);
        $newId = (int)$pdo->lastInsertId();

        /* Assign routers — only those the admin themselves has access to */
        $myRouters    = getMyRouterIds($pdo, $me);
        $postedRouters = array_map('intval', $_POST['router_ids'] ?? []);
        $toAssign     = array_intersect($postedRouters, $myRouters);

        if (!empty($toAssign)) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_router_access (user_id, router_id) VALUES (?, ?)");
            foreach ($toAssign as $rid) {
                $ins->execute([$newId, $rid]);
            }
        }

        header("Location: users?success=created");
        exit;


    /* ======================================================
       UPDATE USER
    ====================================================== */
    case 'update':
        $targetId = (int)($_POST['user_id'] ?? 0);

        if (!$targetId || !userInScope($pdo, $me, $targetId)) {
            header("Location: users?error=unauthorized");
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '') ?: null;
        $role     = in_array($_POST['role']   ?? '', ['staff','admin']) ? $_POST['role']   : 'staff';
        $status   = in_array($_POST['status'] ?? '', ['active','disabled']) ? $_POST['status'] : 'active';

        $pdo->prepare("
            UPDATE users SET username=?, email=?, role=?, status=? WHERE user_id=?
        ")->execute([$username, $email, $role, $status, $targetId]);

        header("Location: users?success=updated");
        exit;


    /* ======================================================
       ASSIGN ROUTERS
    ====================================================== */
    case 'assign_routers':
        $targetId = (int)($_POST['user_id'] ?? 0);

        if (!$targetId || !userInScope($pdo, $me, $targetId)) {
            header("Location: users?error=unauthorized");
            exit;
        }

        $myRouters     = getMyRouterIds($pdo, $me);
        $postedRouters = array_map('intval', $_POST['router_ids'] ?? []);
        $toAssign      = array_intersect($postedRouters, $myRouters); // only my routers

        /* Delete existing assignments for routers I manage, then re-insert */
        if (!empty($myRouters)) {
            $ph = implode(',', array_fill(0, count($myRouters), '?'));
            $pdo->prepare("
                DELETE FROM user_router_access
                WHERE user_id = ? AND router_id IN ($ph)
            ")->execute(array_merge([$targetId], $myRouters));
        }

        if (!empty($toAssign)) {
            $ins = $pdo->prepare("INSERT IGNORE INTO user_router_access (user_id, router_id) VALUES (?, ?)");
            foreach ($toAssign as $rid) {
                $ins->execute([$targetId, $rid]);
            }
        }

        header("Location: users?success=updated");
        exit;


    /* ======================================================
       RESET PASSWORD
    ====================================================== */
    case 'reset_password':
        $targetId = (int)($_POST['user_id'] ?? 0);

        if (!$targetId || !userInScope($pdo, $me, $targetId)) {
            header("Location: users?error=unauthorized");
            exit;
        }

        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            header("Location: users?error=invalid_password");
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([$hash, $targetId]);

        header("Location: users?success=reset");
        exit;


    /* ======================================================
       TOGGLE STATUS
    ====================================================== */
    case 'toggle_status':
        $targetId = (int)($_POST['user_id'] ?? 0);

        if (!$targetId || !userInScope($pdo, $me, $targetId)) {
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


    /* ======================================================
       DELETE USER
    ====================================================== */
    case 'delete':
        $targetId = (int)($_POST['user_id'] ?? 0);

        if (!$targetId || !userInScope($pdo, $me, $targetId)) {
            header("Location: users?error=unauthorized");
            exit;
        }

        /* Remove router assignments first, then delete user */
        $pdo->prepare("DELETE FROM user_router_access WHERE user_id = ?")->execute([$targetId]);
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$targetId]);

        header("Location: users?success=deleted");
        exit;


    default:
        header("Location: users");
        exit;
}