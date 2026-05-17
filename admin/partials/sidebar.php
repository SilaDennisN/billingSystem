<?php
// admin/partials/sidebar.php
// Helper: highlight active link and expand active section
function adminIsActive(string $path): string {
    return str_contains($_SERVER['REQUEST_URI'], $path) ? 'active' : '';
}
function adminIsShow(array $paths): string {
    foreach ($paths as $p) {
        if (str_contains($_SERVER['REQUEST_URI'], $p)) return 'show';
    }
    return '';
}
function adminIsCollapsed(array $paths): string {
    foreach ($paths as $p) {
        if (str_contains($_SERVER['REQUEST_URI'], $p)) return '';
    }
    return 'collapsed';
}
?>

<div id="layoutDrawer_nav">
    <nav class="drawer accordion drawer-light bg-white" id="drawerAccordion">
        <div class="drawer-menu">
            <div class="nav">

                <!-- ── OVERVIEW ───────────────────────────────── -->
                <div class="drawer-menu-heading">Overview</div>

                <a class="nav-link <?= adminIsActive('/admin') ?>" href="../../admin/">
                    <div class="nav-link-icon"><i class="material-icons">space_dashboard</i></div>
                    Dashboard
                </a>

                <a class="nav-link <?= adminIsActive('/admin/analytics') ?>" href="../../admin/analytics">
                    <div class="nav-link-icon"><i class="material-icons">bar_chart</i></div>
                    Analytics
                </a>

                <div class="drawer-menu-divider"></div>

                <!-- ── USERS ──────────────────────────────────── -->
                <div class="drawer-menu-heading">Users</div>

                <!-- Subscriptions -->
                <a class="nav-link <?= adminIsActive('/admin/subscriptions') ?>" href="../../admin/subscriptions">
                    <div class="nav-link-icon"><i class="material-icons">card_membership</i></div>
                    Subscriptions
                </a>

                <!-- Revenue -->
                <a class="nav-link <?= adminIsCollapsed(['/admin/revenue', '/admin/income']) ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseRevenue">
                    <div class="nav-link-icon"><i class="material-icons">trending_up</i></div>
                    Revenue
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>
                <div class="collapse <?= adminIsShow(['/admin/revenue', '/admin/income']) ?>" id="collapseRevenue">
                    <nav class="drawer-menu-nested nav flex-column">
                        <a class="nav-link <?= adminIsActive('/admin/revenue/overview') ?>" href="../../admin/revenue/overview">Per-User Income</a>
                        <a class="nav-link <?= adminIsActive('/admin/revenue/platform-fees') ?>" href="../../admin/revenue/platform-fees">Platform Fees</a>
                        <a class="nav-link <?= adminIsActive('/admin/revenue/payouts') ?>" href="../../admin/revenue/payouts">Payouts</a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- ── GATEWAYS ───────────────────────────────── -->
                <div class="drawer-menu-heading">Gateways</div>

                <!-- M-Pesa Gateways -->
                <a class="nav-link <?= adminIsCollapsed(['/admin/gateways']) ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseGateways">
                    <div class="nav-link-icon"><i class="material-icons">mobile_friendly</i></div>
                    M-Pesa Gateways
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>
                <div class="collapse <?= adminIsShow(['/admin/gateways']) ?>" id="collapseGateways">
                    <nav class="drawer-menu-nested nav flex-column">
                        <a class="nav-link <?= adminIsActive('/admin/gateways/index') ?>" href="../../admin/gateways/index">All Gateways</a>
                        <a class="nav-link <?= adminIsActive('/admin/gateways/pending') ?>" href="../../admin/gateways/pending">Pending Approval</a>
                        <a class="nav-link <?= adminIsActive('/admin/gateways/pesaflux') ?>" href="../../admin/gateways/pesaflux">PesaFlux Keys</a>
                        <a class="nav-link <?= adminIsActive('/admin/gateways/api-keys') ?>" href="../../admin/gateways/api-keys">User API Keys</a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- ── BILLING ─────────────────────────────────── -->
                <div class="drawer-menu-heading">Billing</div>

                <a class="nav-link <?= adminIsCollapsed(['/admin/invoices', '/admin/billing']) ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseBilling">
                    <div class="nav-link-icon"><i class="material-icons">receipt_long</i></div>
                    Invoices
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>
                <div class="collapse <?= adminIsShow(['/admin/invoices', '/admin/billing']) ?>" id="collapseBilling">
                    <nav class="drawer-menu-nested nav flex-column">
                        <a class="nav-link <?= adminIsActive('/admin/invoices/platform') ?>" href="../../admin/invoices/platform">Platform Invoices</a>
                        <a class="nav-link <?= adminIsActive('/admin/invoices/gateway') ?>" href="../../admin/invoices/gateway">Gateway Invoices</a>
                        <a class="nav-link <?= adminIsActive('/admin/invoices/overdue') ?>" href="../../admin/invoices/overdue">Overdue</a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- ── SYSTEM ──────────────────────────────────── -->
                <div class="drawer-menu-heading">System</div>

                <a class="nav-link <?= adminIsActive('/admin/admins') ?>" href="../../admin/admins">
                    <div class="nav-link-icon"><i class="material-icons">admin_panel_settings</i></div>
                    Admin Users
                </a>

                <a class="nav-link <?= adminIsActive('/admin/audit') ?>" href="../../admin/audit">
                    <div class="nav-link-icon"><i class="material-icons">history</i></div>
                    Audit Log
                </a>

                <a class="nav-link <?= adminIsActive('/admin/settings') ?>" href="../../admin/settings">
                    <div class="nav-link-icon"><i class="material-icons">settings</i></div>
                    Settings
                </a>

            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="drawer-footer border-top">
            <div class="d-flex align-items-center">
                <i class="material-icons text-muted">account_circle</i>
                <div class="ms-3">
                    <div class="caption">Logged in as:</div>
                    <div class="small fw-500"><?= htmlspecialchars($_SESSION['admin']['full_names'] ?? 'Admin') ?></div>
                    <div class="small fw-500">Super Admin</div>
                </div>
                <a href="../auth/logout" class="ms-auto" title="Sign Out">
                    <i class="material-icons text-muted">logout</i>
                </a>
            </div>
        </div>

    </nav>
</div>