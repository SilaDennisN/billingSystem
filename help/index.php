<?php
require_once "../core/auth.php";

if (!is_logged_in()) {
    header("Location: ../auth/login");
    exit;
}
?>

<?php require_once "../partials/head.php"; ?>
<body class="nav-fixed bg-light">
<?php require_once "../partials/topnav.php"; ?>

<div id="layoutDrawer">
<?php require_once "../partials/sidebar.php"; ?>

<div id="layoutDrawer_content">
<main>

<header class="bg-primary">
    <div class="container-xl px-5">
        <h1 class="text-white py-3 mb-0 display-6">Help & Documentation</h1>
    </div>
</header>

<div class="container-xl px-5 mt-4">

<div class="row g-4">

<!-- SYSTEM OVERVIEW -->
<div class="col-md-6">
<div class="card shadow-sm">
<div class="card-header fw-bold">📊 System Overview</div>
<div class="card-body">
<p>This billing system manages:</p>
<ul class="mb-0">
    <li>Hotspot & PPPoE users</li>
    <li>Payments & revenues</li>
    <li>Invoices (Prepaid & Postpaid)</li>
    <li>Expenses tracking</li>
    <li>Reports & exports</li>
</ul>
</div>
</div>
</div>

<!-- BILLING LOGIC -->
<div class="col-md-6">
<div class="card shadow-sm">
<div class="card-header fw-bold">💳 Billing Logic</div>
<div class="card-body">
<ul class="mb-0">
    <li><strong>Hotspot:</strong> Prepaid – payment recorded before access</li>
    <li><strong>PPPoE:</strong> Postpaid – monthly invoices</li>
    <li>Payments must be <strong>confirmed</strong> before service activation</li>
    <li>Payment tokens are single-use</li>
</ul>
</div>
</div>
</div>

<!-- COMMON TASKS -->
<div class="col-md-6">
<div class="card shadow-sm">
<div class="card-header fw-bold">🛠 Common Tasks</div>
<div class="card-body">
<ul class="mb-0">
    <li>Create hotspot / PPPoE plans</li>
    <li>Monitor active users</li>
    <li>Reset system user passwords</li>
    <li>Record expenses</li>
    <li>Generate reports (PDF)</li>
</ul>
</div>
</div>
</div>

<!-- TROUBLESHOOTING -->
<div class="col-md-6">
<div class="card shadow-sm">
<div class="card-header fw-bold">🚨 Troubleshooting</div>
<div class="card-body">
<ul class="mb-0">
    <li>If router is offline → check API credentials</li>
    <li>If user not connecting → verify payment & plan</li>
    <li>If invoice not paid → user remains restricted</li>
    <li>Clear expired users periodically</li>
</ul>
</div>
</div>
</div>

<!-- CONTACT -->
<div class="col-md-12">
<div class="card shadow-sm">
<div class="card-header fw-bold">📞 Support</div>
<div class="card-body">
<p class="mb-1"><strong>System:</strong> Inovatech Billing Platform</p>
<p class="mb-1"><strong>Version:</strong> 1.0.0</p>
<p class="mb-1"><strong>Developer:</strong> In-house / ISP Team</p>
<p class="mb-0"><strong>Support Email:</strong> support@inovatech.local</p>
</div>
</div>
</div>

</div>
</div>

</main>
<?php require_once "../partials/footer.php"; ?>
</div>
</div>

<?php require_once "../partials/scripts.php"; ?>
</body>
</html>
