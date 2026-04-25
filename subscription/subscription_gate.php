<?php
/**
 * subscription_gate.php
 * 
 * DROP-IN GUARD — include at the top of any page or action that requires an active subscription.
 * 
 * Usage:
 *   require_once "../core/subscription_gate.php";
 *   check_subscription_gate($pdo, $user_id);
 *
 * If the user has no active subscription, they are redirected to the subscriptions page.
 * On AJAX/JSON requests it returns a JSON error response instead.
 */

function check_subscription_gate(PDO $pdo, int $user_id): void
{
    $stmt = $pdo->prepare("
        SELECT status, trial_ends_at 
        FROM subscriptions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $sub = $stmt->fetch();

    $allowed = false;

    if ($sub) {
        if ($sub['status'] === 'active') {
            $allowed = true;
        } elseif ($sub['status'] === 'trial') {
            $trialEnd = new DateTime($sub['trial_ends_at']);
            $allowed  = new DateTime() < $trialEnd;
        }
    }

    if (!$allowed) {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Active subscription required. Please subscribe to continue.',
                'redirect' => '../subscription/index'
            ]);
            exit;
        }

        // Standard redirect with a flash message
        session_start_if_not_started();
        $_SESSION['subscription_error'] = 'You need an active subscription to add or manage routers.';
        header('Location: ../subscription/index');
        exit;
    }
}

/**
 * Helper — only starts session if one isn't already running.
 */
function session_start_if_not_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}