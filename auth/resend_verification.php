<?php
/**
 * auth/resend_verification.php
 *
 * Allows a user to request a fresh verification email.
 * Accessible from both the login error message and the register success message.
 */

session_start();
require_once '../core/db.php';
require_once '../core/mailer.php';

/* ─────────────────────────────────────────────
   GET  →  show the form (pre-filled if possible)
───────────────────────────────────────────────*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $prefill_email = $_SESSION['unverified_email'] ?? '';
    require_once "../partials/head.php";
    ?>
    <body class="bg-pattern-waihou">
    <div id="layoutAuthentication">
      <div id="layoutAuthentication_content">
        <main>
          <div class="container">
            <div class="row justify-content-center">
              <div class="col-xxl-5 col-xl-6 col-lg-7 col-md-9">
                <div class="card card-raised shadow-10 mt-5 mt-xl-10 mb-4">
                  <div class="card-body p-5">

                    <div class="text-center mb-4">
                      <img class="mb-3" src="../assets/favicon.png" alt="Logo" style="height:48px">
                      <h1 class="display-5 mb-0">Resend Verification</h1>
                      <div class="subheading-1 mb-4">Enter your registered email address</div>
                    </div>

                    <?php if (isset($_SESSION['error'])): ?>
                      <div class="alert alert-danger">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                      </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                      <div class="alert alert-success">
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                      </div>
                    <?php endif; ?>

                    <form method="POST" action="resend_verification.php">
                      <div class="mb-4">
                        <input type="email" name="email" class="form-control"
                               placeholder="Email Address"
                               value="<?= htmlspecialchars($prefill_email) ?>"
                               required>
                      </div>
                      <div class="d-flex align-items-center justify-content-between">
                        <a class="small fw-500 text-decoration-none" href="login">Back to Login</a>
                        <button type="submit" class="btn btn-primary">Resend Link</button>
                      </div>
                    </form>

                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
      <div id="layoutAuthentication_footer">
        <?php require_once "../partials/footer.php"; ?>
      </div>
    </div>
    <?php require_once "../partials/scripts.php"; ?>
    </body>
    </html>
    <?php
    exit;
}

/* ─────────────────────────────────────────────
   POST →  validate and resend
───────────────────────────────────────────────*/

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Please enter a valid email address.";
    header('Location: resend_verification.php');
    exit;
}

$stmt = $pdo->prepare("SELECT user_id, full_names, email, email_verified FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

/* ── Give the same response whether found or not (prevents email enumeration) ── */
$generic_ok = "If that email is registered and unverified, a new link has been sent. Please check your inbox.";

if (!$user) {
    $_SESSION['success'] = $generic_ok;
    header('Location: resend_verification.php');
    exit;
}

if (!empty($user['email_verified'])) {
    $_SESSION['success'] = "This email is already verified. You can <a href='login'>log in</a> directly.";
    header('Location: resend_verification.php');
    exit;
}

/* ── Throttle: only allow resend once every 2 minutes ── */
$stmt = $pdo->prepare("SELECT verify_token_expires FROM users WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$row = $stmt->fetch();

// If a token exists and was issued less than 2 minutes ago, hold off
// (token_expires = issue_time + 24h, so issue_time = expires - 24h)
if ($row && $row['verify_token_expires']) {
    $issued_at = strtotime($row['verify_token_expires']) - (24 * 3600);
    if ((time() - $issued_at) < 120) {
        $_SESSION['error'] = "Please wait a moment before requesting another link.";
        header('Location: resend_verification.php');
        exit;
    }
}

/* ── Issue a fresh token ── */
$new_token      = bin2hex(random_bytes(32));
$new_token_hash = hash('sha256', $new_token);
$new_expires    = date('Y-m-d H:i:s', strtotime('+24 hours'));

$pdo->prepare("
    UPDATE users
    SET verify_token = ?, verify_token_expires = ?
    WHERE user_id = ?
")->execute([$new_token_hash, $new_expires, $user['user_id']]);

send_verification_email($user['email'], $user['full_names'], $new_token);

unset($_SESSION['unverified_email']);
$_SESSION['success'] = $generic_ok;
header('Location: resend_verification.php');
exit;