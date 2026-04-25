<?php
session_start();
require_once "../core/db.php";
require_once "../core/index.php";

$token = $_SESSION['payment_token'] ?? null;
if (!$token) exit("Session expired");

$stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_token=?");
$stmt->execute([$token]);
$payment = $stmt->fetch();

if (!$payment) exit("Invalid payment");


/* SEND STK */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = preg_replace('/^0/', '254', $_POST['phone']);

    $provider = getPaymentProvider($payment['router_id']);

    $response = stkPush(
        $provider,
        $payment['router_id'],
        $payment['amount'],
        $phone,
        "H-Payment"
    );

    error_log("STEP 1 PROVIDER FROM DB: " . $provider);

    $transaction_id = null;

if ($provider === 'mpesa') {
    $transaction_id = $response['CheckoutRequestID'] ?? null;

} elseif ($provider === 'intasend') {
    $transaction_id = $response['invoice']['invoice_id'] ?? null;


} else {
    $transaction_id = $response['transaction_request_id'] ?? null;
}

    if (!empty($transaction_id))  {

        // SAVE PHONE + TX
        $pdo->prepare("
    UPDATE payments
    SET transaction_request_id=?,
        phone=?,
        provider=?
    WHERE payment_id=?
")->execute([
            $transaction_id,
            $phone,
            $provider,
            $payment['payment_id']
        ]);

        header("Location: pay.php?waiting=1");
        exit;
    }

    exit("Failed to send STK");
}
?>


<?php if (isset($_GET['waiting'])): ?>

    <h2>📲 STK Sent</h2>
    <p>Enter your M-Pesa PIN on your phone.</p>
    <p>Connecting you automatically once payment is confirmed...</p>

    <script>
        // check DB directly instead of verify script

// setInterval(() => {

//     fetch("payment_status_intasend.php")
//         .then(res => res.text())
//         .then(status => {

//             if (status === "ACTIVE") {
//                 window.location = "create.php";
//             }

//         });

// }, 3000);


        setInterval(() => {

            fetch("payment_status.php")
                .then(res => res.text())
                .then(status => {

                    if (status === "ACTIVE") {
                        window.location = "create.php";
                    }

                });

        }, 3000);
    </script>

<?php else: ?>

    <form method="post">
        <h2>Pay KES <?= $payment['amount'] ?></h2>

        <input name="phone"
            placeholder="07XXXXXXXX"
            required
            style="padding:10px;width:250px">

        <button type="submit">
            Pay Now
        </button>
    </form>

<?php endif; ?>