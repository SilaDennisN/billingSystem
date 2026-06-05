<?php
//user/subscription/index.php
require_once "../core/db.php";
require_once "../core/auth.php";
require_once "../core/app.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}

$user_id = $_SESSION['user']['id'];

/* ======================
   ENCRYPTION HELPERS
====================== */
function encrypt_key(string $plain): string
{
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);
    return openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
}

function decrypt_key(string $cipher): string
{
    $key = hash('sha256', APP_KEY);
    $iv  = substr(hash('sha256', APP_KEY), 0, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv);
}

/* ======================
   FETCH SUBSCRIPTION
====================== */
$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$subscription = $stmt->fetch();

/* ======================
   FETCH USER'S OWN GATEWAY API KEYS (any provider)
====================== */
$stmt = $pdo->prepare("SELECT * FROM user_api_keys WHERE user_id = ? AND provider IN ('mpesa','pesaflux','intasend') LIMIT 1");
$stmt->execute([$user_id]);
$ownApiKeys  = $stmt->fetch();
$hasOwnKeys  = !empty($ownApiKeys);
$ownProvider = $hasOwnKeys ? strtolower($ownApiKeys['provider']) : null;
// M-Pesa and IntaSend own keys are free of gateway fee — PesaFlux uses platform billing
$ownKeysFree = ($ownProvider === 'mpesa' || $ownProvider === 'intasend');

/* ======================
   MONTHLY BILLED INCOME
====================== */
$stmt = $pdo->prepare("SELECT router_id FROM user_router_access WHERE user_id = ?");
$stmt->execute([$user_id]);
$router_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$monthlyIncome = 0;
if (!empty($router_ids)) {
    $placeholders = implode(',', array_fill(0, count($router_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(amount), 0) FROM payments
        WHERE status = 'used'
          AND router_id IN ($placeholders)
          AND MONTH(created_at) = MONTH(CURDATE())
          AND YEAR(created_at)  = YEAR(CURDATE())
    ");
    $stmt->execute($router_ids);
    $monthlyIncome = (float) $stmt->fetchColumn();
}

$platformFee       = round($monthlyIncome * 0.05, 2);
$gatewayActive     = $subscription && $subscription['gateway_enabled'] == 1;
$gatewayFeeApplies = $gatewayActive && !($hasOwnKeys && $ownKeysFree);
$totalDue          = $platformFee;

/* ======================
   GATEWAY STATUS
====================== */
$gatewayExpired   = false;
$gatewayDaysLeft  = 0;
$gatewayPlan      = $subscription['gateway_plan']       ?? null;
$gatewayType      = $subscription['gateway_type']       ?? null;
$gatewayId        = $subscription['gateway_identifier'] ?? null;
$gatewayBank      = $subscription['gateway_bank']       ?? null;
$gatewayExpiresAt = $subscription['gateway_expires_at'] ?? null;

// For M-Pesa own-keys, partyb is the stored till/paybill number
$mpesaPartyB = ($hasOwnKeys && $ownProvider === 'mpesa') ? ($ownApiKeys['partyb'] ?? $gatewayId) : null;

if ($gatewayActive && $gatewayExpiresAt && !($hasOwnKeys && $ownKeysFree)) {
    $now     = new DateTime();
    $expiry  = new DateTime($gatewayExpiresAt);
    $gatewayExpired  = $expiry <= $now;
    $gatewayDaysLeft = max(0, (int)$now->diff($expiry)->days);
    if ($gatewayExpired) $gatewayActive = false;
}

/* ======================
   SUBSCRIPTION STATUS
====================== */
$isActive    = false;
$statusLabel = 'No Subscription';
$statusClass = 'danger';

if ($subscription) {
    if ($subscription['status'] === 'active') {
        $isActive    = true;
        $statusLabel = 'Active';
        $statusClass = 'success';
    } elseif ($subscription['status'] === 'suspended') {
        $statusLabel = 'Suspended';
        $statusClass = 'danger';
    } elseif ($subscription['status'] === 'cancelled') {
        $statusLabel = 'Cancelled';
        $statusClass = 'danger';
    }
}

/* ======================
   BILLING HISTORY
====================== */
$stmt = $pdo->prepare("SELECT * FROM subscription_invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
$stmt->execute([$user_id]);
$invoices = $stmt->fetchAll();

/* ======================
   GATEWAY BILLING HISTORY
====================== */
$stmt = $pdo->prepare("SELECT * FROM gateway_invoices WHERE user_id = ? ORDER BY created_at DESC LIMIT 12");
$stmt->execute([$user_id]);
$gatewayInvoices = $stmt->fetchAll();

/* ======================
   USER PHONE
====================== */
$stmt = $pdo->prepare("SELECT phone_number, full_names FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$userProfile = $stmt->fetch();
$userPhone   = $userProfile['phone_number'] ?? '';

/* ======================
   KENYA BANK LIST
====================== */
$keBanks = [
    'Absa Bank Kenya',
    'African Banking Corporation (ABC Bank)',
    'Bank of Africa Kenya',
    'Bank of Baroda Kenya',
    'Bank of India Kenya',
    'Citibank Kenya',
    'Co-operative Bank of Kenya',
    'Consolidated Bank of Kenya',
    'Credit Bank',
    'Diamond Trust Bank (DTB)',
    'DIB Bank Kenya',
    'Ecobank Kenya',
    'Equity Bank Kenya',
    'Family Bank',
    'First Community Bank (FCB)',
    'Guaranty Trust Bank Kenya',
    'Guardian Bank',
    'Gulf African Bank',
    'Housing Finance (HFC)',
    'I&M Bank Kenya',
    'KCB Bank Kenya',
    'Kingdom Bank',
    'Mayfair CIB Bank',
    'Middle East Bank Kenya',
    'National Bank of Kenya',
    'NCBA Bank Kenya',
    'Paramount Bank',
    'Prime Bank',
    'SBM Bank Kenya',
    'Sidian Bank',
    'Stanbic Bank Kenya',
    'Standard Chartered Bank Kenya',
    'UBA Kenya Bank',
    'Victoria Commercial Bank',
];

/* ======================
   POST HANDLERS
====================== */
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Activate subscription ─────────────────────────────────
    if ($action === 'subscribe') {
        if (!$subscription) {
            $pdo->prepare("
                INSERT INTO subscriptions (user_id, status, gateway_enabled, created_at, updated_at)
                VALUES (?, 'active', 0, NOW(), NOW())
            ")->execute([$user_id]);
            $successMsg = 'Your account has been activated!';
        } elseif (in_array($subscription['status'], ['suspended', 'cancelled'])) {
            $pdo->prepare("UPDATE subscriptions SET status='active', updated_at=NOW() WHERE user_id=?")
                ->execute([$user_id]);
            $successMsg = 'Subscription reactivated successfully.';
        }
        header("Location: index?success=" . urlencode($successMsg));
        exit;
    }

    // ── Disable gateway ───────────────────────────────────────
    if ($action === 'disable_gateway') {
        $pdo->prepare("
            UPDATE subscriptions
            SET gateway_enabled=0, gateway_type=NULL, gateway_identifier=NULL,
                gateway_bank=NULL, gateway_plan=NULL, gateway_expires_at=NULL, updated_at=NOW()
            WHERE user_id=?
        ")->execute([$user_id]);
        header("Location: index?success=" . urlencode('M-Pesa Gateway disabled.'));
        exit;
    }

    // ── Delete own API keys ───────────────────────────────────
    if ($action === 'delete_own_keys') {
        $stmt = $pdo->prepare("SELECT provider FROM user_api_keys WHERE user_id = ? AND provider IN ('mpesa','pesaflux','intasend') LIMIT 1");
        $stmt->execute([$user_id]);
        $existingKey = $stmt->fetch();
        $delProvider = $existingKey ? $existingKey['provider'] : 'mpesa';
        $pdo->prepare("DELETE FROM user_api_keys WHERE user_id = ? AND provider = ?")->execute([$user_id, $delProvider]);
        $pdo->prepare("UPDATE subscriptions SET gateway_enabled=0, updated_at=NOW() WHERE user_id=?")->execute([$user_id]);
        header("Location: index?success=" . urlencode('Your API keys have been removed.'));
        exit;
    }

    // ── Save own gateway keys ─────────────────────────────────
    if ($action === 'save_own_keys') {
        $selectedProvider = trim($_POST['own_provider'] ?? '');

        if (!in_array($selectedProvider, ['mpesa', 'pesaflux', 'intasend'])) {
            $errorMsg = 'Please select a payment provider.';

            // ─────────────────────────────────────────────────────
            // PesaFlux — user provides Till/Paybill details only
            // Admin supplies API keys manually; user pays gateway fee
            // ─────────────────────────────────────────────────────
        } elseif ($selectedProvider === 'pesaflux') {
            $gwType    = trim($_POST['gateway_type']    ?? '');
            $gwBank    = trim($_POST['gateway_bank']    ?? '');
            $gwAccount = trim($_POST['gateway_account'] ?? '');

            if ($gwType === 'till') {
                $gwId = trim($_POST['gateway_till'] ?? '');
                if (!preg_match('/^\d{5,8}$/', $gwId)) {
                    $errorMsg = 'Enter a valid Till number (5–8 digits).';
                }
            } else {
                $gwType = 'paybill';
                $gwId   = trim($_POST['gateway_identifier'] ?? '');
                if (!preg_match('/^\d{5,8}$/', $gwId)) {
                    $errorMsg = 'Enter a valid Paybill number (5–8 digits).';
                } elseif (empty($gwBank)) {
                    $errorMsg = 'Please select your bank.';
                } elseif (empty($gwAccount)) {
                    $errorMsg = 'Please enter your account number.';
                }
            }

            if (empty($errorMsg)) {
                $pdo->prepare("
                    INSERT INTO user_api_keys (user_id, provider, shortcode, created_at, updated_at)
                    VALUES (?, 'pesaflux', ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE shortcode=VALUES(shortcode), updated_at=NOW()
                ")->execute([$user_id, $gwId]);

                $gwPlan = trim($_POST['gateway_plan'] ?? 'monthly');
                if (!in_array($gwPlan, ['monthly', 'yearly'])) $gwPlan = 'monthly';
                $amount = $gwPlan === 'yearly' ? 3500 : 400;

                $pdo->prepare("
                    UPDATE subscriptions
                    SET gateway_type=?, gateway_identifier=?, gateway_bank=?,
                        gateway_account=?, gateway_plan=?, updated_at=NOW()
                    WHERE user_id=?
                ")->execute([$gwType, $gwId, $gwBank ?: null, $gwAccount ?: null, $gwPlan, $user_id]);

                $currentExpiry = $subscription['gateway_expires_at'] ?? null;
                $base        = ($currentExpiry && strtotime($currentExpiry) > time()) ? $currentExpiry : date('Y-m-d H:i:s');
                $periodStart = date('Y-m-d', strtotime($base));
                $periodEnd   = $gwPlan === 'yearly'
                    ? date('Y-m-d', strtotime('+1 year -1 day',  strtotime($base)))
                    : date('Y-m-d', strtotime('+1 month -1 day', strtotime($base)));

                $stmt = $pdo->prepare("
                    INSERT INTO gateway_invoices (user_id, plan, amount, period_start, period_end, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");
                $stmt->execute([$user_id, $gwPlan, $amount, $periodStart, $periodEnd]);
                $gatewayInvoiceId = $pdo->lastInsertId();
                header("Location: index?pay_gateway={$gatewayInvoiceId}&amount={$amount}");
                exit;
            }

            // ─────────────────────────────────────────────────────
            // M-Pesa Daraja — user provides their own API keys
            //
            // SHORTCODE = head-office shortcode for auth/password
            //             (for BuyGoods this is Safaricom's own
            //              shortcode e.g. 174379; for Paybill it's
            //              the same as the Paybill number)
            //
            // PARTYB    = money destination
            //             (for BuyGoods = till number;
            //              for Paybill  = same as Paybill number)
            //
            // TransactionType is set in pay.php based on gwType.
            // ─────────────────────────────────────────────────────
        } elseif ($selectedProvider === 'mpesa') {
            $consumerKey    = trim($_POST['mpesa_consumer_key']    ?? '');
            $consumerSecret = trim($_POST['mpesa_consumer_secret'] ?? '');
            $passkey        = trim($_POST['mpesa_passkey']         ?? '');
            $shortcode      = trim($_POST['mpesa_shortcode']       ?? '');
            $partyb         = trim($_POST['mpesa_partyb']          ?? '');
            $gwType         = trim($_POST['gateway_type']          ?? '');

            // For Paybill, PartyB is the same number as the Paybill shortcode
            if (empty($partyb) && $gwType === 'paybill') {
                $partyb = $shortcode;
            }

            if (empty($consumerKey) || empty($consumerSecret) || empty($passkey)) {
                $errorMsg = 'Consumer key, consumer secret and passkey are all required.';
            } elseif (!in_array($gwType, ['till', 'paybill'])) {
                $errorMsg = 'Please select Till (BuyGoods) or Paybill.';
            } elseif (!preg_match('/^\d{5,8}$/', $shortcode)) {
                $errorMsg = 'Enter a valid Shortcode (5–8 digits).';
            } elseif (empty($partyb) || !preg_match('/^\d{5,8}$/', $partyb)) {
                $errorMsg = $gwType === 'till'
                    ? 'Enter a valid Till number for PartyB (5–8 digits).'
                    : 'Enter a valid Paybill number for PartyB (5–8 digits).';
            } else {
                $encCK = encrypt_key($consumerKey);
                $encCS = encrypt_key($consumerSecret);
                $encPK = encrypt_key($passkey);

                $pdo->prepare("
                    INSERT INTO user_api_keys
                        (user_id, provider, consumer_key_encrypted, consumer_secret_encrypted,
                         passkey_encrypted, shortcode, partyb, created_at, updated_at)
                    VALUES (?, 'mpesa', ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        consumer_key_encrypted    = VALUES(consumer_key_encrypted),
                        consumer_secret_encrypted = VALUES(consumer_secret_encrypted),
                        passkey_encrypted         = VALUES(passkey_encrypted),
                        shortcode                 = VALUES(shortcode),
                        partyb                    = VALUES(partyb),
                        updated_at                = NOW()
                ")->execute([$user_id, $encCK, $encCS, $encPK, $shortcode, $partyb]);

                // gateway_identifier stores the PartyB (till/paybill displayed in the UI)
                $pdo->prepare("
                    UPDATE subscriptions
                    SET gateway_enabled=1, gateway_type=?, gateway_identifier=?,
                        gateway_bank=NULL, gateway_account=NULL, gateway_plan='own',
                        gateway_expires_at=NULL, updated_at=NOW()
                    WHERE user_id=?
                ")->execute([$gwType, $partyb, $user_id]);

                header("Location: index?success=" . urlencode('M-Pesa API keys saved — gateway activated free of charge.'));
                exit;
            }

            // ─────────────────────────────────────────────────────
            // IntaSend — user provides their own API keys — FREE
            // ─────────────────────────────────────────────────────
        } elseif ($selectedProvider === 'intasend') {
            $publicKey = trim($_POST['intasend_public_key'] ?? '');
            $secretKey = trim($_POST['intasend_secret_key'] ?? '');
            $shortcode = trim($_POST['mpesa_shortcode']     ?? '');
            $gwType    = trim($_POST['gateway_type']        ?? '');

            if (empty($publicKey) || empty($secretKey)) {
                $errorMsg = 'IntaSend public key and secret key are required.';
            } elseif (empty($shortcode) || !preg_match('/^\d{5,8}$/', $shortcode)) {
                $errorMsg = 'Enter a valid shortcode (5–8 digits).';
            } elseif (!in_array($gwType, ['till', 'paybill'])) {
                $errorMsg = 'Please select Till or Paybill.';
            } else {
                $encPub = encrypt_key($publicKey);
                $encSec = encrypt_key($secretKey);

                $pdo->prepare("
                    INSERT INTO user_api_keys
                        (user_id, provider, public_key_encrypted, secret_key_encrypted, shortcode, created_at, updated_at)
                    VALUES (?, 'intasend', ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        public_key_encrypted = VALUES(public_key_encrypted),
                        secret_key_encrypted = VALUES(secret_key_encrypted),
                        shortcode            = VALUES(shortcode),
                        updated_at           = NOW()
                ")->execute([$user_id, $encPub, $encSec, $shortcode]);

                $pdo->prepare("
                    UPDATE subscriptions
                    SET gateway_enabled=1, gateway_type=?, gateway_identifier=?,
                        gateway_bank=NULL, gateway_account=NULL, gateway_plan='own',
                        gateway_expires_at=NULL, updated_at=NOW()
                    WHERE user_id=?
                ")->execute([$gwType, $shortcode, $user_id]);

                header("Location: index?success=" . urlencode('IntaSend API keys saved — gateway activated free of charge.'));
                exit;
            }
        }
    }

    // ── Setup gateway via platform (PesaFlux, billed) ─────────
    if ($action === 'setup_gateway') {
        $gwType    = $_POST['gateway_type'] ?? '';
        $gwPlan    = $_POST['gateway_plan'] ?? 'monthly';
        $gwBank    = '';
        $gwAccount = '';

        if ($gwType === 'till') {
            $gwId = trim($_POST['gateway_till'] ?? '');
        } else {
            $gwId      = trim($_POST['gateway_identifier'] ?? '');
            $gwBank    = trim($_POST['gateway_bank']       ?? '');
            $gwAccount = trim($_POST['gateway_account']    ?? '');
        }

        if (!in_array($gwType, ['till', 'paybill'])) {
            $errorMsg = 'Please select Till or Paybill.';
        } elseif (!preg_match('/^\d{5,8}$/', $gwId)) {
            $errorMsg = $gwType === 'till' ? 'Enter a valid Till number (5–8 digits).' : 'Enter a valid Paybill number (5–8 digits).';
        } elseif ($gwType === 'paybill' && empty($gwBank)) {
            $errorMsg = 'Please select your bank.';
        } elseif ($gwType === 'paybill' && empty($gwAccount)) {
            $errorMsg = 'Please enter the account number.';
        } elseif (!in_array($gwPlan, ['monthly', 'yearly'])) {
            $errorMsg = 'Invalid plan selected.';
        } else {
            $amount = $gwPlan === 'yearly' ? 3500 : 400;

            $pdo->prepare("
                UPDATE subscriptions
                SET gateway_type=?, gateway_identifier=?, gateway_bank=?,
                    gateway_account=?, gateway_plan=?, updated_at=NOW()
                WHERE user_id=?
            ")->execute([$gwType, $gwId, $gwBank ?: null, $gwAccount ?: null, $gwPlan, $user_id]);

            $currentExpiry = $subscription['gateway_expires_at'] ?? null;
            $base        = ($currentExpiry && strtotime($currentExpiry) > time()) ? $currentExpiry : date('Y-m-d H:i:s');
            $periodStart = date('Y-m-d', strtotime($base));
            $periodEnd   = $gwPlan === 'yearly'
                ? date('Y-m-d', strtotime('+1 year -1 day',  strtotime($base)))
                : date('Y-m-d', strtotime('+1 month -1 day', strtotime($base)));

            $stmt = $pdo->prepare("
                INSERT INTO gateway_invoices (user_id, plan, amount, period_start, period_end, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([$user_id, $gwPlan, $amount, $periodStart, $periodEnd]);
            $gatewayInvoiceId = $pdo->lastInsertId();
            header("Location: index?pay_gateway={$gatewayInvoiceId}&amount={$amount}");
            exit;
        }
    }
}

if (isset($_GET['success'])) $successMsg = htmlspecialchars($_GET['success']);
?>
<?php require_once "../partials/head.php"; ?>

<body class="nav-fixed bg-light">

    <?php require_once "../partials/topnav.php"; ?>

    <div id="layoutDrawer">
        <?php require_once "../partials/sidebar.php"; ?>

        <div id="layoutDrawer_content">
            <main>

                <!-- Header -->
                <header class="bg-primary">
                    <div class="container-xl px-1">
                        <div class="d-flex justify-content-between align-items-center py-3">
                            <div>
                                <h1 class="text-white mb-0 display-6">
                                    <i class="fa fa-credit-card me-2"></i>Subscription
                                </h1>
                                <p class="text-white-50 mb-0 mt-1">Manage your plan, billing and payment gateway</p>
                            </div>
                            <div>
                                <span class="badge bg-<?= $statusClass ?>-soft text-<?= $statusClass ?> fs-6 px-3 py-2">
                                    <i class="fa fa-circle me-1"></i><?= $statusLabel ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-xl px-1 mt-4">

                    <?php if ($successMsg): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa fa-check-circle me-2"></i><?= $successMsg ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMsg): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa fa-exclamation-circle me-2"></i><?= $errorMsg ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- =====================
                         NO SUBSCRIPTION YET
                         ===================== -->
                    <?php if (!$subscription): ?>
                        <div class="row justify-content-center">
                            <div class="col-lg-7">
                                <div class="card card-raised border-0 shadow text-center py-5 px-4">
                                    <div class="mb-4">
                                        <div class="avatar avatar-xl bg-primary-soft mx-auto mb-3" style="width:80px;height:80px;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                            <i class="fa fa-rocket fa-2x text-primary"></i>
                                        </div>
                                        <h3 class="fw-bold">Activate Your Account</h3>
                                        <p class="text-muted">Get full access to all features including hotspot management, PPPoE, reports and more.</p>
                                    </div>
                                    <div class="bg-light rounded-3 p-4 mb-4 text-start">
                                        <p class="fw-bold mb-3 text-primary"><i class="fa fa-tag me-2"></i>How Pricing Works</p>
                                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                            <span><i class="fa fa-percent me-2 text-muted"></i>Platform Fee</span>
                                            <strong>5% of monthly billed income</strong>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                            <span>
                                                <i class="fa fa-mobile me-2 text-muted"></i>M-Pesa Gateway
                                                <span class="badge bg-secondary-soft text-secondary ms-1">Optional</span>
                                            </span>
                                            <strong>KES 400 / month <span class="text-muted small fw-normal">or free with own API keys</span></strong>
                                        </div>
                                        <div class="mt-3 p-3 bg-success-soft rounded-3">
                                            <div class="small text-muted mb-1">Example: If you bill KES 30,000/month</div>
                                            <div class="d-flex justify-content-between small fw-bold">
                                                <span>Your total cost</span>
                                                <span class="text-success">KES 1,500 platform + KES 400 gateway = KES 1,900</span>
                                            </div>
                                        </div>
                                        <div class="mt-2 p-3 bg-primary-soft rounded-3">
                                            <div class="small fw-bold text-primary"><i class="fa fa-key me-1"></i>Have your own M-Pesa API keys?</div>
                                            <div class="small text-muted mt-1">Connect them after activation and pay <strong>KES 0</strong> for the gateway.</div>
                                        </div>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="subscribe">
                                        <button type="submit" class="btn btn-primary btn-lg w-100">
                                            <i class="fa fa-rocket me-2"></i>Activate Account
                                        </button>
                                    </form>
                                    <p class="text-muted small mt-3 mb-0">✓ No upfront payment &nbsp;·&nbsp; ✓ Billed monthly on income &nbsp;·&nbsp; ✓ Cancel anytime</p>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="row">

                            <!-- LEFT COLUMN -->
                            <div class="col-lg-8">

                                <!-- Current Plan Card -->
                                <div class="card card-raised border-start border-<?= $statusClass ?> border-4 shadow-sm mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                            <div>
                                                <div class="small text-muted mb-1">Current Plan</div>
                                                <h4 class="fw-bold mb-1">Inovatech Platform</h4>
                                                <span class="badge bg-<?= $statusClass ?> fs-6 px-3 py-2">
                                                    <i class="fa fa-circle me-1"></i><?= $statusLabel ?>
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($subscription['status'] === 'active'): ?>
                                                    <div class="small text-muted">Member since</div>
                                                    <div class="fw-bold"><?= date('M d, Y', strtotime($subscription['created_at'])) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <div class="bg-light rounded-3 p-3 text-center">
                                                    <div class="small text-muted mb-1">This Month's Income</div>
                                                    <div class="h4 fw-bold text-primary mb-0">KES <?= number_format($monthlyIncome, 0) ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="bg-light rounded-3 p-3 text-center">
                                                    <div class="small text-muted mb-1">Platform Fee (5%)</div>
                                                    <div class="h4 fw-bold text-warning mb-0">KES <?= number_format($platformFee, 0) ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="bg-<?= $totalDue > 0 ? 'danger' : 'success' ?>-soft rounded-3 p-3 text-center">
                                                    <div class="small text-muted mb-1">Estimated Due</div>
                                                    <div class="h4 fw-bold text-<?= $totalDue > 0 ? 'danger' : 'success' ?> mb-0">KES <?= number_format($totalDue, 0) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if (in_array($subscription['status'], ['suspended', 'cancelled'])): ?>
                                            <div class="alert alert-danger mt-3 mb-0">
                                                <i class="fa fa-exclamation-triangle me-2"></i>
                                                Your subscription is inactive. Reactivate to continue managing routers and users.
                                                <form method="POST" class="d-inline ms-2">
                                                    <input type="hidden" name="action" value="subscribe">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fa fa-refresh me-1"></i>Reactivate Now
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Billing History -->
                                <div class="card card-raised shadow-sm mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fa fa-history me-2"></i>Billing History
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Invoice</th>
                                                        <th>Period</th>
                                                        <th>Income Billed</th>
                                                        <th>Platform Fee</th>
                                                        <th>Gateway</th>
                                                        <th>Total</th>
                                                        <th>Status</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($invoices as $inv): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-secondary-soft text-secondary font-monospace">
                                                                    #<?= str_pad($inv['id'], 5, '0', STR_PAD_LEFT) ?>
                                                                </span>
                                                            </td>
                                                            <td><small><?= date('M d Y', strtotime($inv['period_start'])) ?> →<br><?= date('M d Y', strtotime($inv['period_end'])) ?></small></td>
                                                            <td>KES <?= number_format($inv['billed_income'], 0) ?></td>
                                                            <td class="text-warning fw-bold">KES <?= number_format($inv['platform_fee'], 0) ?></td>
                                                            <td><?= $inv['gateway_fee'] > 0 ? 'KES ' . number_format($inv['gateway_fee'], 0) : '<span class="text-muted">—</span>' ?></td>
                                                            <td class="fw-bold">KES <?= number_format($inv['total_amount'], 0) ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                                    <?= $inv['status'] === 'paid' ? '✓ Paid' : ucfirst($inv['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if (in_array($inv['status'], ['pending', 'overdue'])): ?>
                                                                    <button class="btn btn-sm btn-success"
                                                                        onclick="openPayModal(<?= $inv['id'] ?>, <?= (int)ceil($inv['total_amount']) ?>)">
                                                                        <i class="fa fa-mobile me-1"></i>Pay
                                                                    </button>
                                                                <?php elseif ($inv['status'] === 'paid'): ?>
                                                                    <span class="text-muted small"><i class="fa fa-check-circle text-success me-1"></i>Done</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($invoices)): ?>
                                                        <tr>
                                                            <td colspan="8" class="text-center text-muted py-4">
                                                                <i class="fa fa-file-invoice fa-2x d-block mb-2 opacity-25"></i>
                                                                No invoices yet. Your first invoice will appear on your billing anniversary.
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gateway Billing History -->
                                <?php if (!empty($gatewayInvoices) && !($hasOwnKeys && $ownKeysFree)): ?>
                                    <div class="card card-raised shadow-sm mb-4">
                                        <div class="card-header bg-primary text-white">
                                            <i class="fa fa-mobile me-2"></i>Gateway Billing History
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>#</th>
                                                            <th>Plan</th>
                                                            <th>Period</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($gatewayInvoices as $gi): ?>
                                                            <tr>
                                                                <td><span class="badge bg-secondary-soft text-secondary font-monospace">#<?= str_pad($gi['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                                                                <td><span class="badge bg-primary-soft text-primary"><?= ucfirst($gi['plan']) ?></span></td>
                                                                <td><small><?= date('M d Y', strtotime($gi['period_start'])) ?> →<br><?= date('M d Y', strtotime($gi['period_end'])) ?></small></td>
                                                                <td class="fw-bold">KES <?= number_format($gi['amount'], 0) ?></td>
                                                                <td><span class="badge bg-<?= $gi['status'] === 'paid' ? 'success' : ($gi['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= $gi['status'] === 'paid' ? '✓ Paid' : ucfirst($gi['status']) ?></span></td>
                                                                <td>
                                                                    <?php if (in_array($gi['status'], ['pending', 'overdue'])): ?>
                                                                        <button class="btn btn-sm btn-success" onclick="openGatewayPayModal(<?= $gi['id'] ?>, <?= (int)$gi['amount'] ?>)">
                                                                            <i class="fa fa-mobile me-1"></i>Pay
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <span class="text-muted small"><i class="fa fa-check-circle text-success me-1"></i>Done</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div><!-- end col-lg-8 -->

                            <!-- RIGHT COLUMN -->
                            <div class="col-lg-4">

                                <!-- Gateway Card -->
                                <div class="card card-raised shadow-sm mb-4">
                                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fa fa-mobile me-2"></i>Payment Gateway
                                            <?php if ($hasOwnKeys): ?>
                                                <small class="ms-1 opacity-75">(<?= $ownProvider === 'pesaflux' ? 'Platform' : ucfirst($ownProvider) ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($gatewayActive && $hasOwnKeys): ?>
                                            <span class="badge bg-success">Active <i class="fa fa-key ms-1"></i></span>
                                        <?php elseif ($gatewayActive): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif ($gatewayExpired): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">

                                        <?php if ($gatewayActive): ?>
                                            <!-- ACTIVE STATE -->
                                            <div class="bg-success-soft rounded-3 p-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-bold text-success">
                                                        <i class="fa fa-check-circle me-1"></i>Gateway Active
                                                    </span>
                                                    <?php if ($hasOwnKeys): ?>
                                                        <span class="badge bg-<?= $ownKeysFree ? 'success' : ($gatewayPlan === 'yearly' ? 'primary' : 'secondary') ?>">
                                                            <i class="fa fa-key me-1"></i><?= $ownProvider === 'pesaflux' ? 'Platform' : ucfirst($ownProvider) ?> Keys<?= $ownKeysFree ? ' · Free' : '' ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-<?= $gatewayPlan === 'yearly' ? 'primary' : 'secondary' ?>">
                                                            <?= $gatewayPlan === 'yearly' ? 'Yearly Plan' : 'Monthly Plan' ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="small text-muted mb-1">
                                                    <i class="fa fa-<?= $gatewayType === 'till' ? 'store' : 'building' ?> me-1"></i>
                                                    <?= $gatewayType === 'till' ? 'Till Number' : 'Paybill Number' ?>:
                                                    <strong class="font-monospace"><?= htmlspecialchars($gatewayId ?? '') ?></strong>
                                                </div>

                                                <?php if ($ownProvider === 'mpesa' && $mpesaPartyB && $mpesaPartyB !== $gatewayId): ?>
                                                    <div class="small text-muted mb-1">
                                                        <i class="fa fa-exchange me-1"></i>PartyB:
                                                        <strong class="font-monospace"><?= htmlspecialchars($mpesaPartyB) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($gatewayBank): ?>
                                                    <div class="small text-muted mb-1">
                                                        <i class="fa fa-university me-1"></i>Bank: <strong><?= htmlspecialchars($gatewayBank) ?></strong>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($subscription['gateway_account'])): ?>
                                                    <div class="small text-muted mb-1">
                                                        <i class="fa fa-hashtag me-1"></i>Account: <strong class="font-monospace"><?= htmlspecialchars($subscription['gateway_account']) ?></strong>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Transaction type badge -->
                                                <div class="mt-2">
                                                    <?php if ($gatewayType === 'till'): ?>
                                                        <span class="badge bg-warning-soft text-warning">
                                                            <i class="fa fa-shopping-cart me-1"></i>BuyGoods (CustomerBuyGoodsOnline)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info-soft text-info">
                                                            <i class="fa fa-university me-1"></i>PayBill (CustomerPayBillOnline)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!($hasOwnKeys && $ownKeysFree) && $gatewayExpiresAt): ?>
                                                    <div class="small text-muted mt-2">
                                                        <i class="fa fa-calendar me-1"></i>Renews: <strong><?= date('M d, Y', strtotime($gatewayExpiresAt)) ?></strong>
                                                        <?php if ($gatewayDaysLeft <= 7): ?>
                                                            <span class="text-danger ms-1"><i class="fa fa-exclamation-triangle"></i> <?= $gatewayDaysLeft ?> day(s) left</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($hasOwnKeys && $ownKeysFree): ?>
                                                    <div class="small text-success mt-2">
                                                        <i class="fa fa-infinity me-1"></i>No expiry — using your own API keys
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="d-grid gap-2">
                                                <button class="btn btn-outline-success btn-sm" onclick="openTestGatewayModal()">
                                                    <i class="fa fa-flask me-1"></i>Test Gateway (KES 1)
                                                </button>
                                                <button class="btn btn-outline-primary btn-sm"
                                                    onclick="document.getElementById('gatewaySetupForm').classList.toggle('d-none')">
                                                    <i class="fa fa-pencil me-1"></i>Change <?= $hasOwnKeys ? ($ownProvider === 'pesaflux' ? 'Platform' : ucfirst($ownProvider)) . ' Keys' : 'Plan / Details' ?>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Disable the payment gateway?')">
                                                    <input type="hidden" name="action" value="<?= $hasOwnKeys ? 'delete_own_keys' : 'disable_gateway' ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                        <i class="fa fa-times me-1"></i>Disable Gateway<?= $hasOwnKeys ? ' & Remove Keys' : '' ?>
                                                    </button>
                                                </form>
                                            </div>

                                        <?php elseif ($gatewayExpired): ?>
                                            <div class="alert alert-danger py-2 mb-3">
                                                <i class="fa fa-exclamation-triangle me-1"></i>
                                                Your gateway subscription expired. Renew below.
                                            </div>

                                        <?php else: ?>
                                            <p class="text-muted small mb-3">Accept M-Pesa payments from your customers automatically via STK Push.</p>
                                        <?php endif; ?>

                                        <!-- ── SETUP FORM ── -->
                                        <div id="gatewaySetupForm" class="<?= ($gatewayActive && !$gatewayExpired) ? 'd-none' : '' ?> mt-2">

                                            <!-- Provider selector -->
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small">Payment Provider</label>
                                                <div class="row g-2 mb-1">
                                                    <div class="col-4">
                                                        <input type="radio" class="btn-check" name="key_source_toggle" id="ks_pesaflux" value="pesaflux"
                                                            <?= ($ownProvider === 'pesaflux' || (!$hasOwnKeys)) ? 'checked' : '' ?>>
                                                        <label class="btn btn-outline-warning w-100 text-start px-2 py-2" for="ks_pesaflux">
                                                            <i class="fa fa-cloud me-1"></i><strong>Plantform</strong><br>
                                                            <!-- <small class="fw-normal text-muted d-block" style="font-size:.7rem">Platform · KES 400/mo</small> -->
                                                        </label>
                                                    </div>
                                                    <div class="col-4">
                                                        <input type="radio" class="btn-check" name="key_source_toggle" id="ks_mpesa" value="mpesa"
                                                            <?= ($ownProvider === 'mpesa') ? 'checked' : '' ?>>
                                                        <label class="btn btn-outline-success w-100 text-start px-2 py-2" for="ks_mpesa">
                                                            <i class="fa fa-key me-1"></i><strong>M-Pesa</strong><br>
                                                            <!-- <small class="fw-normal text-muted d-block" style="font-size:.7rem">Own keys · Free</small> -->
                                                        </label>
                                                    </div>
                                                    <div class="col-4">
                                                        <input type="radio" class="btn-check" name="key_source_toggle" id="ks_intasend" value="intasend"
                                                            <?= ($ownProvider === 'intasend') ? 'checked' : '' ?>>
                                                        <label class="btn btn-outline-info w-100 text-start px-2 py-2" for="ks_intasend">
                                                            <i class="fa fa-key me-1"></i><strong>IntaSend</strong><br>
                                                            <!-- <small class="fw-normal text-muted d-block" style="font-size:.7rem">Own keys · Free</small> -->
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- ── PESAFLUX ── -->
                                            <div id="section_pesaflux" class="<?= ($ownProvider !== 'pesaflux' && $hasOwnKeys) ? 'd-none' : '' ?>">
                                                <div class="alert alert-warning py-2 mb-3 small">
                                                    <i class="fa fa-info-circle me-1"></i>
                                                    We'll set up the gateway on our end. Just enter your M-Pesa details below and choose a billing plan.
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="save_own_keys">
                                                    <input type="hidden" name="own_provider" value="pesaflux">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Account Type</label>
                                                        <div class="d-flex gap-2">
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_type" id="pf_type_till" value="till"
                                                                    <?= ($gatewayType !== 'paybill') ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="pf_type_till">
                                                                    <i class="fa fa-store me-1"></i>Till
                                                                </label>
                                                            </div>
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_type" id="pf_type_paybill" value="paybill"
                                                                    <?= $gatewayType === 'paybill' ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="pf_type_paybill">
                                                                    <i class="fa fa-university me-1"></i>Paybill
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div id="pf_till_fields" class="<?= $gatewayType === 'paybill' ? 'd-none' : '' ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Till Number</label>
                                                            <input type="text" class="form-control" name="gateway_till"
                                                                value="<?= $gatewayType !== 'paybill' ? htmlspecialchars($gatewayId ?? '') : '' ?>"
                                                                placeholder="e.g. 123456" pattern="\d{5,8}">
                                                        </div>
                                                    </div>
                                                    <div id="pf_paybill_fields" class="<?= $gatewayType === 'paybill' ? '' : 'd-none' ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Business / Paybill Number</label>
                                                            <input type="text" class="form-control" name="gateway_identifier"
                                                                value="<?= $gatewayType === 'paybill' ? htmlspecialchars($gatewayId ?? '') : '' ?>"
                                                                placeholder="e.g. 522522" pattern="\d{5,8}">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Account Number</label>
                                                            <input type="text" class="form-control" name="gateway_account"
                                                                value="<?= htmlspecialchars($subscription['gateway_account'] ?? '') ?>"
                                                                placeholder="e.g. 0012345678">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Bank</label>
                                                            <select class="form-select" name="gateway_bank">
                                                                <option value="">— Select Bank —</option>
                                                                <?php foreach ($keBanks as $b): ?>
                                                                    <option value="<?= htmlspecialchars($b) ?>" <?= ($gatewayBank === $b) ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Billing Plan</label>
                                                        <div class="d-flex gap-2">
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_plan" id="pf_plan_monthly" value="monthly"
                                                                    <?= ($gatewayPlan !== 'yearly') ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="pf_plan_monthly">Monthly<br><strong>KES 400</strong></label>
                                                            </div>
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_plan" id="pf_plan_yearly" value="yearly"
                                                                    <?= $gatewayPlan === 'yearly' ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-success w-100" for="pf_plan_yearly">Yearly<br><strong>KES 3,500</strong></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn btn-warning w-100 text-dark">
                                                        <i class="fa fa-mobile me-2"></i>
                                                        <?= ($gatewayActive && $ownProvider === 'pesaflux') ? 'Update & Pay' : 'Activate Gateway' ?>
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- ── M-PESA OWN KEYS ── -->
                                            <div id="section_mpesa" class="<?= ($ownProvider !== 'mpesa') ? 'd-none' : '' ?>">
                                                <div class="alert alert-success py-2 mb-3 small">
                                                    <i class="fa fa-shield me-1"></i>
                                                    Your keys are encrypted with AES-256. No gateway fee — you own these keys.
                                                </div>

                                                <!-- Account type first so the PartyB hint updates -->
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold small">Account Type</label>
                                                    <div class="d-flex gap-2">
                                                        <div class="flex-fill">
                                                            <input type="radio" class="btn-check" name="gateway_type" id="mp_type_till" value="till"
                                                                <?= ($gatewayType !== 'paybill') ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-secondary w-100" for="mp_type_till">
                                                                <i class="fa fa-store me-1"></i>Till (BuyGoods)
                                                            </label>
                                                        </div>
                                                        <div class="flex-fill">
                                                            <input type="radio" class="btn-check" name="gateway_type" id="mp_type_paybill" value="paybill"
                                                                <?= $gatewayType === 'paybill' ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-secondary w-100" for="mp_type_paybill">
                                                                <i class="fa fa-university me-1"></i>Paybill
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>

                                                <form method="POST">
                                                    <input type="hidden" name="action" value="save_own_keys">
                                                    <input type="hidden" name="own_provider" value="mpesa">
                                                    <!-- hidden mirror so PHP sees gateway_type from the radio above -->
                                                    <input type="hidden" name="gateway_type" id="mp_gwtype_hidden"
                                                        value="<?= $gatewayType === 'paybill' ? 'paybill' : 'till' ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Consumer Key</label>
                                                        <input type="text" class="form-control font-monospace" name="mpesa_consumer_key"
                                                            placeholder="Safaricom Daraja consumer key" autocomplete="off">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Consumer Secret</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control font-monospace" name="mpesa_consumer_secret"
                                                                id="consumerSecretInput" placeholder="Safaricom Daraja consumer secret" autocomplete="off">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleVisibility('consumerSecretInput', this)">
                                                                <i class="fa fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Passkey</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control font-monospace" name="mpesa_passkey"
                                                                id="passkeyInput" placeholder="Lipa Na M-Pesa online passkey" autocomplete="off">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleVisibility('passkeyInput', this)">
                                                                <i class="fa fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!--
                                                        SHORTCODE = head-office/business shortcode
                                                        For BuyGoods (Till): Safaricom's own shortcode, e.g. 174379
                                                        For Paybill: your Paybill business number
                                                        Used in: BusinessShortCode field + Password hash
                                                    -->
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">
                                                            Business Shortcode
                                                            <i class="fa fa-info-circle text-muted ms-1"
                                                                title="Used for authentication. For BuyGoods (Till), enter Safaricom head-office shortcode (e.g. 174379). For Paybill, enter your Paybill number."
                                                                data-bs-toggle="tooltip"></i>
                                                        </label>
                                                        <input type="text" class="form-control font-monospace" name="mpesa_shortcode"
                                                            id="mp_shortcode"
                                                            value="<?= ($ownProvider === 'mpesa') ? htmlspecialchars($ownApiKeys['shortcode'] ?? '') : '' ?>"
                                                            placeholder="e.g. 174379 (BuyGoods) or your Paybill number"
                                                            pattern="\d{5,8}">
                                                        <div class="form-text" id="mp_shortcode_hint">
                                                            <span id="mp_shortcode_hint_till">BuyGoods: enter Safaricom's shortcode (e.g. <strong>174379</strong> for Lipa Na M-Pesa).</span>
                                                            <span id="mp_shortcode_hint_paybill" class="d-none">Paybill: enter your business/Paybill number.</span>
                                                        </div>
                                                    </div>

                                                    <!--
                                                        PARTYB = money destination (where the KES goes)
                                                        For BuyGoods (Till): your Till number
                                                        For Paybill: same as your Paybill number above
                                                        Used in: PartyB field of STK push
                                                    -->
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">
                                                            PartyB
                                                            <span class="badge bg-secondary-soft text-secondary ms-1" id="mp_txntype_badge">
                                                                CustomerBuyGoodsOnline
                                                            </span>
                                                            <i class="fa fa-info-circle text-muted ms-1"
                                                                title="The number that receives the money. For BuyGoods (Till): your till number. For Paybill: your Paybill number."
                                                                data-bs-toggle="tooltip"></i>
                                                        </label>
                                                        <input type="text" class="form-control font-monospace" name="mpesa_partyb"
                                                            id="mp_partyb"
                                                            value="<?= ($ownProvider === 'mpesa') ? htmlspecialchars($ownApiKeys['partyb'] ?? $gatewayId ?? '') : '' ?>"
                                                            placeholder="e.g. 123456 (your till number)"
                                                            pattern="\d{5,8}">
                                                        <div class="form-text" id="mp_partyb_hint">
                                                            <span id="mp_partyb_hint_till">Your <strong>Till number</strong> — where customers' payments land.</span>
                                                            <span id="mp_partyb_hint_paybill" class="d-none">Your <strong>Paybill number</strong> (same as above).</span>
                                                        </div>
                                                    </div>

                                                    <button type="submit" class="btn btn-success w-100">
                                                        <i class="fa fa-lock me-2"></i>Save M-Pesa Keys — Free
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- ── INTASEND OWN KEYS ── -->
                                            <div id="section_intasend" class="<?= ($ownProvider !== 'intasend') ? 'd-none' : '' ?>">
                                                <div class="alert alert-info py-2 mb-3 small">
                                                    <i class="fa fa-info-circle me-1"></i>
                                                    Get your API keys from <a href="https://intasend.com" target="_blank" class="alert-link">intasend.com</a>
                                                    → Dashboard → API Keys. No gateway fee — you own these keys.
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="save_own_keys">
                                                    <input type="hidden" name="own_provider" value="intasend">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Public Key</label>
                                                        <input type="text" class="form-control font-monospace" name="intasend_public_key"
                                                            placeholder="ISPubKey_live_..." autocomplete="off">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Secret Key</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control font-monospace" name="intasend_secret_key"
                                                                id="intasendSecretKey" placeholder="ISSecretKey_live_..." autocomplete="off">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleVisibility('intasendSecretKey', this)">
                                                                <i class="fa fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Account Type</label>
                                                        <div class="d-flex gap-2">
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_type" id="is_type_till" value="till"
                                                                    <?= ($gatewayType !== 'paybill') ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="is_type_till">
                                                                    <i class="fa fa-store me-1"></i>Till
                                                                </label>
                                                            </div>
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_type" id="is_type_paybill" value="paybill"
                                                                    <?= $gatewayType === 'paybill' ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="is_type_paybill">
                                                                    <i class="fa fa-university me-1"></i>Paybill
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Shortcode <span class="text-muted fw-normal">(Till or Paybill number)</span></label>
                                                        <input type="text" class="form-control" name="mpesa_shortcode"
                                                            value="<?= ($ownProvider === 'intasend') ? htmlspecialchars($gatewayId ?? '') : '' ?>"
                                                            placeholder="e.g. 123456" pattern="\d{5,8}">
                                                    </div>
                                                    <button type="submit" class="btn btn-info w-100 text-white">
                                                        <i class="fa fa-lock me-2"></i>Save IntaSend Keys — Free
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- ── PLATFORM (PesaFlux billed) FORM — shown when no own keys ── -->
                                            <div id="platformSection" class="<?= $hasOwnKeys ? 'd-none' : '' ?>">
                                                <?php if (!$gatewayActive && !$gatewayExpired): ?>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <div class="border rounded-3 p-2 text-center">
                                                                <div class="fw-bold">Monthly</div>
                                                                <div class="text-primary fs-5 fw-bold">KES 400</div>
                                                                <div class="small text-muted">per month</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="border border-success rounded-3 p-2 text-center">
                                                                <div class="fw-bold">Yearly <span class="badge bg-success-soft text-success" style="font-size:0.65rem">Save KES 1,300</span></div>
                                                                <div class="text-success fs-5 fw-bold">KES 3,500</div>
                                                                <div class="small text-muted">per year</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="setup_gateway">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Account Type</label>
                                                        <div class="d-flex gap-2">
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_type" id="type_till" value="till"
                                                                    <?= ($gatewayType !== 'paybill') ? 'checked' : '' ?> required>
                                                                <label class="btn btn-outline-secondary w-100" for="type_till">
                                                                    <i class="fa fa-store me-1"></i>Till
                                                                </label>
                                                            </div>
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_type" id="type_paybill" value="paybill"
                                                                    <?= $gatewayType === 'paybill' ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="type_paybill">
                                                                    <i class="fa fa-university me-1"></i>Paybill
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div id="tillFields" class="<?= $gatewayType === 'paybill' ? 'd-none' : '' ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Till Number</label>
                                                            <input type="text" class="form-control" name="gateway_till"
                                                                value="<?= $gatewayType !== 'paybill' ? htmlspecialchars($gatewayId ?? '') : '' ?>"
                                                                placeholder="e.g. 123456" pattern="\d{5,8}">
                                                        </div>
                                                    </div>
                                                    <div id="paybillFields" class="<?= $gatewayType === 'paybill' ? '' : 'd-none' ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Bank</label>
                                                            <select class="form-select" name="gateway_bank">
                                                                <option value="">— Select Bank —</option>
                                                                <?php foreach ($keBanks as $b): ?>
                                                                    <option value="<?= htmlspecialchars($b) ?>" <?= ($gatewayBank === $b) ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Business / Paybill Number</label>
                                                            <input type="text" class="form-control" name="gateway_identifier"
                                                                value="<?= $gatewayType === 'paybill' ? htmlspecialchars($gatewayId ?? '') : '' ?>"
                                                                placeholder="e.g. 522522" pattern="\d{5,8}">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold small">Account Number</label>
                                                            <input type="text" class="form-control" name="gateway_account"
                                                                value="<?= htmlspecialchars($subscription['gateway_account'] ?? '') ?>"
                                                                placeholder="e.g. 0012345678">
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">Billing Plan</label>
                                                        <div class="d-flex gap-2">
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_plan" id="plan_monthly" value="monthly"
                                                                    <?= ($gatewayPlan !== 'yearly') ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-secondary w-100" for="plan_monthly">Monthly<br><strong>KES 400</strong></label>
                                                            </div>
                                                            <div class="flex-fill">
                                                                <input type="radio" class="btn-check" name="gateway_plan" id="plan_yearly" value="yearly"
                                                                    <?= $gatewayPlan === 'yearly' ? 'checked' : '' ?>>
                                                                <label class="btn btn-outline-success w-100" for="plan_yearly">Yearly<br><strong>KES 3,500</strong></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn btn-success w-100">
                                                        <i class="fa fa-mobile me-2"></i>
                                                        <?= ($gatewayActive && !$hasOwnKeys) ? 'Update & Pay' : 'Activate Gateway' ?>
                                                    </button>
                                                </form>
                                            </div>

                                        </div><!-- end gatewaySetupForm -->
                                    </div>
                                </div>

                                <!-- Pricing summary -->
                                <div class="card card-raised shadow-sm mb-4">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fa fa-calculator me-2"></i>This Month Estimate
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span class="text-muted">Billed Income</span>
                                            <strong>KES <?= number_format($monthlyIncome, 0) ?></strong>
                                        </div>
                                        <div class="d-flex justify-content-between py-2 border-bottom">
                                            <span class="text-muted">Platform Fee (5%)</span>
                                            <strong class="text-warning">KES <?= number_format($platformFee, 0) ?></strong>
                                        </div>
                                        <?php if ($gatewayActive): ?>
                                            <div class="d-flex justify-content-between py-2 border-bottom">
                                                <span class="text-muted">Payment Gateway</span>
                                                <?php if ($hasOwnKeys && $ownKeysFree): ?>
                                                    <strong class="text-success"><i class="fa fa-key me-1"></i>Free (own keys)</strong>
                                                <?php else: ?>
                                                    <strong>KES <?= $gatewayPlan === 'yearly' ? '3,500' : '400' ?> <span class="text-muted small">separate</span></strong>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="d-flex justify-content-between py-2 mt-1">
                                            <span class="fw-bold">Platform Fee Due</span>
                                            <strong class="text-primary fs-5">KES <?= number_format($totalDue, 0) ?></strong>
                                        </div>
                                        <div class="small text-muted mt-2">
                                            <i class="fa fa-info-circle me-1"></i>
                                            Platform fee invoiced on your billing anniversary.
                                            <?= ($hasOwnKeys && $ownKeysFree) ? 'Gateway fee waived — using own API keys.' : 'Gateway billed separately.' ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Support -->
                                <div class="card card-raised shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <i class="fa fa-life-ring me-2"></i>Need Help?
                                    </div>
                                    <div class="card-body d-grid gap-2">
                                        <a href="https://wa.me/?text=Hello+Inovatech+Support" target="_blank" class="btn btn-success">
                                            <i class="fa fa-whatsapp me-2"></i>WhatsApp Support
                                        </a>
                                        <a href="mailto:support@inovatech.co.ke" class="btn btn-outline-primary">
                                            <i class="fa fa-envelope me-2"></i>Email Support
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>

                    <?php endif; ?>

                </div>
            </main>

            <?php require_once "../partials/footer.php"; ?>
        </div>
    </div>

    <?php require_once "../partials/scripts.php"; ?>

    <!-- =====================================================
         M-PESA PAYMENT MODAL (platform/gateway invoices)
         ===================================================== -->
    <div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">

                <div id="step-phone">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="payModalLabel"><i class="fa fa-mobile me-2"></i>Pay via M-Pesa</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="text-center mb-4">
                            <div class="bg-success-soft rounded-3 p-3 d-inline-block mb-2">
                                <i class="fa fa-mobile fa-2x text-success"></i>
                            </div>
                            <div class="small text-muted">Amount to pay</div>
                            <div class="h3 fw-bold text-primary mb-0">KES <span id="display-amount">0</span></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">M-Pesa Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                <input type="tel" class="form-control form-control-lg" id="pay-phone"
                                    placeholder="07XXXXXXXX" value="<?= htmlspecialchars($userPhone) ?>">
                            </div>
                            <div class="form-text">Enter the M-Pesa number to receive the STK push.</div>
                        </div>
                        <div id="phone-error" class="alert alert-danger d-none py-2"></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success px-4" id="btn-send-stk">
                            <i class="fa fa-paper-plane me-2"></i>Send STK Push
                        </button>
                    </div>
                </div>

                <div id="step-waiting" class="d-none">
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><i class="fa fa-spinner fa-spin me-2 text-primary"></i>Waiting for Payment</h5>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <div class="mb-4">
                            <div style="width:72px;height:72px;border-radius:50%;background:rgba(22,81,245,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <i class="fa fa-mobile fa-2x text-primary"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Check your phone</h6>
                            <p class="text-muted small mb-0">An M-Pesa prompt has been sent to <strong id="waiting-phone"></strong>.<br>Enter your PIN to complete the payment.</p>
                        </div>
                        <div class="progress mb-3" style="height:6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success w-100"></div>
                        </div>
                        <div class="text-muted small" id="poll-status">Checking payment status...</div>
                        <div class="alert alert-warning d-none mt-3 py-2" id="timeout-warning">
                            <i class="fa fa-clock me-1"></i>Taking longer than usual. Still checking...
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 justify-content-center">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btn-cancel-poll">Cancel</button>
                    </div>
                </div>

                <div id="step-success" class="d-none">
                    <div class="modal-body p-4 text-center">
                        <div style="width:80px;height:80px;border-radius:50%;background:#f0fdf4;border:3px solid #16a34a;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="fa fa-check fa-2x text-success"></i>
                        </div>
                        <h5 class="fw-bold text-success mb-1">Payment Confirmed!</h5>
                        <p class="text-muted small mb-3">Your subscription is now active.</p>
                        <div class="bg-success-soft rounded-3 p-3 mb-3">
                            <div class="small text-muted mb-1">M-Pesa Transaction Code</div>
                            <div class="fw-bold font-monospace fs-5 text-success" id="mpesa-code"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 justify-content-center">
                        <button type="button" class="btn btn-success px-4" onclick="location.reload()">
                            <i class="fa fa-refresh me-2"></i>Done
                        </button>
                    </div>
                </div>

                <div id="step-failed" class="d-none">
                    <div class="modal-body p-4 text-center">
                        <div style="width:80px;height:80px;border-radius:50%;background:#fff3f3;border:3px solid #dc3545;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="fa fa-times fa-2x text-danger"></i>
                        </div>
                        <h5 class="fw-bold text-danger mb-1">Payment Failed</h5>
                        <p class="text-muted small mb-3" id="fail-reason">The payment was not completed.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="btn-retry">
                            <i class="fa fa-refresh me-2"></i>Try Again
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- =====================================================
         TEST GATEWAY MODAL (KES 1 test via user's own keys)
         ===================================================== -->
    <div class="modal fade" id="testGatewayModal" tabindex="-1" aria-labelledby="testGatewayLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">

                <div id="tg-step-confirm">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="testGatewayLabel">
                            <i class="fa fa-flask me-2"></i>Test Your Gateway
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fa fa-info-circle me-1"></i>
                            We'll send a <strong>KES 1</strong> STK push to your phone.
                            The money goes to your <?= $gatewayType === 'till' ? 'Till' : 'Paybill' ?> — confirming your gateway works.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Your M-Pesa Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                <input type="tel" class="form-control" id="tg-phone"
                                    placeholder="07XXXXXXXX" value="<?= htmlspecialchars($userPhone) ?>">
                            </div>
                        </div>
                        <div id="tg-phone-error" class="alert alert-danger d-none py-2"></div>
                        <div class="bg-light rounded-3 p-3 small text-muted">
                            <i class="fa fa-<?= $gatewayType === 'till' ? 'store' : 'university' ?> me-1"></i>
                            Payment destination:
                            <strong>
                                <?php if ($ownProvider === 'mpesa'): ?>
                                    <?= $gatewayType === 'till' ? 'Till' : 'Paybill' ?>
                                    <?= htmlspecialchars($mpesaPartyB ?? $gatewayId ?? '') ?>
                                <?php else: ?>
                                    <?= $gatewayType === 'till' ? 'Till' : 'Paybill' ?>
                                    <?= htmlspecialchars($gatewayId ?? '') ?>
                                <?php endif; ?>
                            </strong>
                            <?php if ($gatewayBank): ?> — <?= htmlspecialchars($gatewayBank) ?><?php endif; ?>
                                <br>
                                <span class="badge bg-secondary-soft text-secondary mt-1">
                                    <?= $gatewayType === 'till' ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline' ?>
                                </span>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success px-4" id="tg-btn-send">
                            <i class="fa fa-paper-plane me-2"></i>Send KES 1 Test
                        </button>
                    </div>
                </div>

                <div id="tg-step-waiting" class="d-none">
                    <div class="modal-header border-0">
                        <h5 class="modal-title"><i class="fa fa-spinner fa-spin me-2 text-success"></i>Waiting for Payment</h5>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <div class="mb-4">
                            <div style="width:72px;height:72px;border-radius:50%;background:rgba(25,135,84,.1);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <i class="fa fa-mobile fa-2x text-success"></i>
                            </div>
                            <h6 class="fw-bold mb-1">Check your phone</h6>
                            <p class="text-muted small mb-0">Enter your M-Pesa PIN to complete the KES 1 test payment.</p>
                        </div>
                        <div class="progress mb-3" style="height:6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success w-100"></div>
                        </div>
                        <div class="text-muted small" id="tg-poll-status">Checking payment status...</div>
                    </div>
                    <div class="modal-footer border-0 pt-0 justify-content-center">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="tg-btn-cancel">Cancel</button>
                    </div>
                </div>

                <div id="tg-step-success" class="d-none">
                    <div class="modal-body p-4 text-center">
                        <div style="width:80px;height:80px;border-radius:50%;background:#f0fdf4;border:3px solid #16a34a;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="fa fa-check fa-2x text-success"></i>
                        </div>
                        <h5 class="fw-bold text-success mb-1">Gateway Working!</h5>
                        <p class="text-muted small mb-3">
                            KES 1 was successfully received by your
                            <?= $gatewayType === 'till' ? 'Till' : 'Paybill' ?>.
                            Your customers can now pay via STK push.
                        </p>
                        <div class="bg-success-soft rounded-3 p-3 mb-3">
                            <div class="small text-muted mb-1">M-Pesa Receipt</div>
                            <div class="fw-bold font-monospace fs-5 text-success" id="tg-mpesa-code"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 justify-content-center">
                        <button type="button" class="btn btn-success px-4" data-bs-dismiss="modal">
                            <i class="fa fa-check me-2"></i>Done
                        </button>
                    </div>
                </div>

                <div id="tg-step-failed" class="d-none">
                    <div class="modal-body p-4 text-center">
                        <div style="width:80px;height:80px;border-radius:50%;background:#fff3f3;border:3px solid #dc3545;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="fa fa-times fa-2x text-danger"></i>
                        </div>
                        <h5 class="fw-bold text-danger mb-1">Test Failed</h5>
                        <p class="text-muted small mb-3" id="tg-fail-reason">The test payment was not completed.</p>
                    </div>
                    <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="tg-btn-retry">
                            <i class="fa fa-refresh me-2"></i>Try Again
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // ── Password visibility toggle ──────────────────────────────
        function toggleVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.innerHTML = isPassword ? '<i class="fa fa-eye-slash"></i>' : '<i class="fa fa-eye"></i>';
        }

        // ── Provider section toggle ─────────────────────────────────
        function toggleKeySource() {
            const sel = document.querySelector('input[name="key_source_toggle"]:checked');
            const val = sel ? sel.value : 'pesaflux';
            ['pesaflux', 'mpesa', 'intasend'].forEach(function(p) {
                var el = document.getElementById('section_' + p);
                if (el) el.classList.toggle('d-none', val !== p);
            });
            // platformSection only shown for PesaFlux
            var ps = document.getElementById('platformSection');
            if (ps) ps.classList.toggle('d-none', val !== 'pesaflux');
        }
        document.querySelectorAll('input[name="key_source_toggle"]').forEach(function(r) {
            r.addEventListener('change', toggleKeySource);
        });
        toggleKeySource();

        // ── PesaFlux till/paybill toggle ───────────────────────────
        function togglePfFields() {
            var sel = document.querySelector('input[id^="pf_type_"]:checked');
            var isPaybill = sel && sel.value === 'paybill';
            var tf = document.getElementById('pf_till_fields');
            var pf = document.getElementById('pf_paybill_fields');
            if (tf) tf.classList.toggle('d-none', isPaybill);
            if (pf) pf.classList.toggle('d-none', !isPaybill);
        }
        document.querySelectorAll('input[id^="pf_type_"]').forEach(function(r) {
            r.addEventListener('change', togglePfFields);
        });
        togglePfFields();

        // ── Platform gateway till/paybill toggle ───────────────────
        function togglePlatformFields() {
            var sel = document.querySelector('#type_till, #type_paybill');
            var paybill = document.getElementById('type_paybill');
            var isPaybill = paybill && paybill.checked;
            var tf = document.getElementById('tillFields');
            var pf = document.getElementById('paybillFields');
            if (tf) tf.classList.toggle('d-none', isPaybill);
            if (pf) pf.classList.toggle('d-none', !isPaybill);
        }
        var platRadios = document.querySelectorAll('#type_till, #type_paybill');
        platRadios.forEach(function(r) {
            r.addEventListener('change', togglePlatformFields);
        });
        togglePlatformFields();

        // ── M-Pesa own-keys: account type drives shortcode hint,
        //    PartyB hint, TransactionType badge, and hidden field ──
        function updateMpesaFields() {
            var tillRadio = document.getElementById('mp_type_till');
            var isTill = tillRadio && tillRadio.checked;
            var shortcodeEl = document.getElementById('mp_shortcode');
            var partybEl = document.getElementById('mp_partyb');
            var hiddenType = document.getElementById('mp_gwtype_hidden');
            var txnBadge = document.getElementById('mp_txntype_badge');

            // Shortcode hints
            var hintTill = document.getElementById('mp_shortcode_hint_till');
            var hintPaybill = document.getElementById('mp_shortcode_hint_paybill');
            if (hintTill) hintTill.classList.toggle('d-none', !isTill);
            if (hintPaybill) hintPaybill.classList.toggle('d-none', isTill);

            // PartyB hints
            var pbHintTill = document.getElementById('mp_partyb_hint_till');
            var pbHintPaybill = document.getElementById('mp_partyb_hint_paybill');
            if (pbHintTill) pbHintTill.classList.toggle('d-none', !isTill);
            if (pbHintPaybill) pbHintPaybill.classList.toggle('d-none', isTill);

            // PartyB placeholder
            if (partybEl) {
                partybEl.placeholder = isTill ?
                    'e.g. 123456 (your Till number)' :
                    'Same as your Paybill number above';
            }

            // TransactionType badge
            if (txnBadge) {
                txnBadge.textContent = isTill ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline';
            }

            // Auto-fill PartyB from shortcode when Paybill is selected
            if (!isTill && partybEl && shortcodeEl) {
                partybEl.value = shortcodeEl.value;
            }

            // Keep the hidden field in sync so PHP form submission gets gateway_type
            if (hiddenType) {
                hiddenType.value = isTill ? 'till' : 'paybill';
            }
        }

        // Sync PartyB as shortcode is typed (Paybill only)
        var shortcodeInput = document.getElementById('mp_shortcode');
        if (shortcodeInput) {
            shortcodeInput.addEventListener('input', function() {
                var paybill = document.getElementById('mp_type_paybill');
                var partybEl = document.getElementById('mp_partyb');
                if (paybill && paybill.checked && partybEl) {
                    partybEl.value = this.value;
                }
            });
        }

        document.querySelectorAll('#mp_type_till, #mp_type_paybill').forEach(function(r) {
            r.addEventListener('change', updateMpesaFields);
        });
        updateMpesaFields();

        // ── Init Bootstrap tooltips ─────────────────────────────────
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
            new bootstrap.Tooltip(el);
        });


        // ════════════════════════════════════════════════════════════
        // PAYMENT MODAL — platform / gateway invoices
        // ════════════════════════════════════════════════════════════
        let currentInvoiceId = null;
        let currentGwInvoiceId = null;
        let currentAmount = null;
        let isGatewayPayment = false;
        let pollInterval = null;
        let pollCount = 0;
        const MAX_POLLS = 24;

        function showStep(name) {
            ['step-phone', 'step-waiting', 'step-success', 'step-failed'].forEach(function(id) {
                document.getElementById(id).classList.add('d-none');
            });
            document.getElementById('step-' + name).classList.remove('d-none');
        }

        function openPayModal(invoiceId, amount) {
            currentInvoiceId = invoiceId;
            currentGwInvoiceId = null;
            currentAmount = amount;
            isGatewayPayment = false;
            pollCount = 0;
            document.getElementById('display-amount').textContent = amount.toLocaleString();
            document.getElementById('phone-error').classList.add('d-none');
            resetStkBtn();
            showStep('phone');
            new bootstrap.Modal(document.getElementById('payModal')).show();
        }

        function openGatewayPayModal(gwInvoiceId, amount) {
            currentInvoiceId = null;
            currentGwInvoiceId = gwInvoiceId;
            currentAmount = amount;
            isGatewayPayment = true;
            pollCount = 0;
            document.getElementById('display-amount').textContent = amount.toLocaleString();
            document.getElementById('phone-error').classList.add('d-none');
            resetStkBtn();
            showStep('phone');
            new bootstrap.Modal(document.getElementById('payModal')).show();
        }

        function resetStkBtn() {
            var btn = document.getElementById('btn-send-stk');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane me-2"></i>Send STK Push';
        }

        // Auto-open gateway pay modal from redirect URL
        <?php if (isset($_GET['pay_gateway']) && isset($_GET['amount'])): ?>
            window.addEventListener('DOMContentLoaded', function() {
                openGatewayPayModal(<?= (int)$_GET['pay_gateway'] ?>, <?= (int)$_GET['amount'] ?>);
            });
        <?php endif; ?>

        document.getElementById('btn-send-stk').addEventListener('click', async function() {
            var phone = document.getElementById('pay-phone').value.trim();
            var errEl = document.getElementById('phone-error');
            if (!phone) {
                errEl.textContent = 'Please enter a phone number.';
                errEl.classList.remove('d-none');
                return;
            }
            var btn = document.getElementById('btn-send-stk');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Sending...';

            try {
                var payload = isGatewayPayment ?
                    {
                        action: 'initiate',
                        gateway_invoice_id: currentGwInvoiceId,
                        phone: phone
                    } :
                    {
                        action: 'initiate',
                        invoice_id: currentInvoiceId,
                        phone: phone
                    };

                var res = await fetch('pay.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                var data = await res.json();

                if (!data.success) {
                    errEl.textContent = data.message || 'Failed to send STK push.';
                    errEl.classList.remove('d-none');
                    resetStkBtn();
                    return;
                }
                document.getElementById('waiting-phone').textContent = phone;
                showStep('waiting');
                startPolling(data.transaction_request_id);
            } catch (e) {
                errEl.textContent = 'Network error. Please try again.';
                errEl.classList.remove('d-none');
                resetStkBtn();
            }
        });

        function startPolling(txnId) {
            pollCount = 0;
            clearInterval(pollInterval);
            document.getElementById('timeout-warning').classList.add('d-none');
            document.getElementById('poll-status').textContent = 'Waiting for M-Pesa response...';
            setTimeout(function() {
                doPoll(txnId);
                pollInterval = setInterval(function() {
                    doPoll(txnId);
                }, 5000);
            }, 5000);
        }

        async function doPoll(txnId) {
            pollCount++;
            if (pollCount > MAX_POLLS) {
                clearInterval(pollInterval);
                document.getElementById('fail-reason').textContent = 'Payment timed out. Please try again.';
                showStep('failed');
                return;
            }
            if (pollCount === 8) document.getElementById('timeout-warning').classList.remove('d-none');
            document.getElementById('poll-status').textContent = 'Checking payment status... (' + pollCount + '/' + MAX_POLLS + ')';
            try {
                var payload = isGatewayPayment ?
                    {
                        action: 'verify',
                        transaction_request_id: txnId,
                        gateway_invoice_id: currentGwInvoiceId
                    } :
                    {
                        action: 'verify',
                        transaction_request_id: txnId,
                        invoice_id: currentInvoiceId
                    };
                var res = await fetch('pay.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                var text = await res.text();
                var data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    return;
                }
                if (data.status === 'paid') {
                    clearInterval(pollInterval);
                    document.getElementById('mpesa-code').textContent = data.mpesa_code || '—';
                    showStep('success');
                } else if (data.status === 'failed') {
                    clearInterval(pollInterval);
                    document.getElementById('fail-reason').textContent = data.message || 'Payment failed.';
                    showStep('failed');
                }
            } catch (e) {}
        }

        document.getElementById('btn-cancel-poll').addEventListener('click', function() {
            clearInterval(pollInterval);
            bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
        });
        document.getElementById('btn-retry').addEventListener('click', function() {
            resetStkBtn();
            showStep('phone');
        });
        document.getElementById('payModal').addEventListener('hidden.bs.modal', function() {
            clearInterval(pollInterval);
        });


        // ════════════════════════════════════════════════════════════
        // TEST GATEWAY MODAL
        // ════════════════════════════════════════════════════════════
        var tgPollInterval = null;
        var tgPollCount = 0;
        const TG_MAX_POLLS = 24;

        function tgShowStep(step) {
            ['tg-step-confirm', 'tg-step-waiting', 'tg-step-success', 'tg-step-failed'].forEach(function(id) {
                document.getElementById(id).classList.add('d-none');
            });
            document.getElementById('tg-step-' + step).classList.remove('d-none');
        }

        function openTestGatewayModal() {
            tgShowStep('confirm');
            document.getElementById('tg-phone-error').classList.add('d-none');
            resetTgBtn();
            new bootstrap.Modal(document.getElementById('testGatewayModal')).show();
        }

        function resetTgBtn() {
            var btn = document.getElementById('tg-btn-send');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-paper-plane me-2"></i>Send KES 1 Test';
        }

        function tgStopPolling() {
            if (tgPollInterval) {
                clearInterval(tgPollInterval);
                tgPollInterval = null;
            }
        }

        document.getElementById('tg-btn-send').addEventListener('click', async function() {
            var phoneRaw = document.getElementById('tg-phone').value.trim();
            var errEl = document.getElementById('tg-phone-error');
            errEl.classList.add('d-none');

            var phone = phoneRaw.replace(/\D/g, '');
            if (phone.startsWith('0')) phone = '254' + phone.slice(1);
            if (phone.startsWith('+')) phone = phone.slice(1);
            if (!/^2547\d{8}$/.test(phone)) {
                errEl.textContent = 'Enter a valid M-Pesa number (07XXXXXXXX).';
                errEl.classList.remove('d-none');
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Sending...';

            try {
                var res = await fetch('pay.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'test_gateway',
                        phone: phone
                    })
                });
                var data = await res.json();

                if (!data.success) {
                    errEl.textContent = data.message || 'Failed to send STK push.';
                    errEl.classList.remove('d-none');
                    resetTgBtn();
                    return;
                }

                tgShowStep('waiting');
                tgPollCount = 0;
                var txnId = data.transaction_request_id;

                setTimeout(function() {
                    tgPollInterval = setInterval(async function() {
                        tgPollCount++;
                        document.getElementById('tg-poll-status').textContent =
                            'Checking... (' + tgPollCount + '/' + TG_MAX_POLLS + ')';
                        try {
                            var vRes = await fetch('pay.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    action: 'verify_test',
                                    transaction_request_id: txnId
                                })
                            });
                            var vData = await vRes.json();
                            if (vData.status === 'paid') {
                                tgStopPolling();
                                document.getElementById('tg-mpesa-code').textContent = vData.mpesa_code || '—';
                                tgShowStep('success');
                            } else if (vData.status === 'failed') {
                                tgStopPolling();
                                document.getElementById('tg-fail-reason').textContent = vData.message || 'Payment was not completed.';
                                tgShowStep('failed');
                            } else if (tgPollCount >= TG_MAX_POLLS) {
                                tgStopPolling();
                                document.getElementById('tg-fail-reason').textContent = 'Timed out. Please try again.';
                                tgShowStep('failed');
                            }
                        } catch (e) {}
                    }, 5000);
                }, 5000);

            } catch (e) {
                errEl.textContent = 'Network error. Please try again.';
                errEl.classList.remove('d-none');
                resetTgBtn();
            }
        });

        document.getElementById('tg-btn-cancel').addEventListener('click', function() {
            tgStopPolling();
            bootstrap.Modal.getInstance(document.getElementById('testGatewayModal'))?.hide();
        });
        document.getElementById('tg-btn-retry').addEventListener('click', function() {
            tgStopPolling();
            tgShowStep('confirm');
            resetTgBtn();
        });
        document.getElementById('testGatewayModal').addEventListener('hidden.bs.modal', function() {
            tgStopPolling();
        });
    </script>

</body>

</html>