<?php
require_once "db.php";
require_once "router.php";

/**
 * Record a payment and apply plan to user
 */
function process_payment(PDO $pdo, $user_type, $user_id, $plan_id, $router_id, $amount, $method = 'cash')
{
    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments 
        (user_type, user_id, router_id, plan_id, amount, payment_method, status)
        VALUES (?,?,?,?,?,?, 'completed')
    ");
    $stmt->execute([$user_type, $user_id, $router_id, $plan_id, $amount, $method]);

    // Update user/session expiry & plan
    $planStmt = $pdo->prepare("SELECT * FROM billing_plans WHERE plan_id=?");
    $planStmt->execute([$plan_id]);
    $plan = $planStmt->fetch();

    $now = new DateTime();

    if ($user_type === 'admin') {
        $userStmt = $pdo->prepare("SELECT * FROM hotspot_users WHERE user_id=?");
    } else {
        $userStmt = $pdo->prepare("SELECT * FROM hotspot_sessions WHERE session_id=?");
    }
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    $old_expiry = $user['expires_at'] ?? $now->format('Y-m-d H:i:s');
    $new_expiry = new DateTime($old_expiry);
    $new_expiry->modify("+{$plan['validity_minutes']} minutes");

    $client = router_connect($router_id);

    if ($user_type === 'admin') {
        $client->query(
            (new RouterOS\Query('/ip/hotspot/user/set'))
                ->equal('.id', $user['username'])
                ->equal('profile', $plan['hotspot_profile'])
        )->read();

        $pdo->prepare("UPDATE hotspot_users SET plan_id=?, expires_at=?, status='active' WHERE user_id=?")
            ->execute([$plan_id, $new_expiry->format('Y-m-d H:i:s'), $user_id]);

    } else {
        $client->query(
            (new RouterOS\Query('/ip/hotspot/user/add'))
                ->equal('name', $user['mac_address'])
                ->equal('password', $user['mac_address'])
                ->equal('profile', $plan['hotspot_profile'])
        )->read();

        $pdo->prepare("UPDATE hotspot_sessions SET plan_id=?, expires_at=?, paid=1 WHERE session_id=?")
            ->execute([$plan_id, $new_expiry->format('Y-m-d H:i:s'), $user_id]);
    }

    return true;
}
