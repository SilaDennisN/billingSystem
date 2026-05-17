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

<style>
/* ── Admin Drawer Overrides ─────────────────────────── */
#adminLayoutDrawer_nav .drawer {
    background: #0f172a !important;
    border-right: 1px solid rgba(99,102,241,.18);
}
#adminLayoutDrawer_nav .drawer-menu-heading {
    color: #475569 !important;
    font-size: .65rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    padding: 16px 20px 6px;
    font-weight: 700;
}
#adminLayoutDrawer_nav .drawer-menu-divider {
    border-color: rgba(99,102,241,.12) !important;
    margin: 8px 0;
}
#adminLayoutDrawer_nav .nav-link {
    color: #94a3b8 !important;
    border-radius: 10px;
    margin: 1px 10px;
    padding: 9px 12px;
    font-size: .85rem;
    transition: background .15s, color .15s;
}
#adminLayoutDrawer_nav .nav-link:hover {
    background: rgba(99,102,241,.1) !important;
    color: #c7d2fe !important;
}
#adminLayoutDrawer_nav .nav-link.active {
    background: rgba(99,102,241,.18) !important;
    color: #a5b4fc !important;
    font-weight: 600;
}
#adminLayoutDrawer_nav .nav-link .nav-link-icon i {
    color: #475569;
    transition: color .15s;
    font-size: 20px;
}
#adminLayoutDrawer_nav .nav-link:hover .nav-link-icon i,
#adminLayoutDrawer_nav .nav-link.active .nav-link-icon i {
    color: #818cf8 !important;
}
#adminLayoutDrawer_nav .drawer-menu-nested .nav-link {
    font-size: .8rem;
    padding: 7px 12px 7px 38px;
    margin: 1px 10px;
    border-radius: 8px;
}
#adminLayoutDrawer_nav .drawer-menu-nested .nav-link::before {
    content: '';
    width: 5px; height: 5px;
    border-radius: 50%;
    background: #334155;
    display: inline-block;
    margin-right: 10px;
    vertical-align: middle;
    transition: background .15s;
}
#adminLayoutDrawer_nav .drawer-menu-nested .nav-link.active::before,
#adminLayoutDrawer_nav .drawer-menu-nested .nav-link:hover::before {
    background: #818cf8;
}
#adminLayoutDrawer_nav .drawer-footer {
    background: #0f172a !important;
    border-top: 1px solid rgba(99,102,241,.15) !important;
}

/* Stat pill in sidebar */
.admin-stat-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 18px;
    padding: 0 6px;
    border-radius: 20px;
    font-size: .65rem;
    font-weight: 700;
    margin-left: auto;
}
</style>

<div id="adminLayoutDrawer_nav">
    <nav class="drawer accordion drawer-light" id="adminDrawerAccordion">
        <div class="drawer-menu">
            <div class="nav flex-column">

                <!-- ── OVERVIEW ───────────────────────────────── -->
                <div class="drawer-menu-heading">Overview</div>

                <a class="nav-link <?= adminIsActive('/admin/dashboard') ?>" href="../admin/dashboard">
                    <div class="nav-link-icon"><i class="material-icons">space_dashboard</i></div>
                    Dashboard
                </a>

                <a class="nav-link <?= adminIsActive('/admin/analytics') ?>" href="../admin/analytics">
                    <div class="nav-link-icon"><i class="material-icons">bar_chart</i></div>
                    Analytics
                </a>

                <div class="drawer-menu-divider"></div>

                <!-- ── USERS ──────────────────────────────────── -->
                <div class="drawer-menu-heading">Users</div>

                <!-- All Users -->
                <a class="nav-link <?= adminIsActive('/admin/users') ?>" href="../admin/users">
                    <div class="nav-link-icon"><i class="material-icons">people</i></div>
                    All Users
                </a>

                <!-- Subscriptions -->
                <a class="nav-link <?= adminIsActive('/admin/subscriptions') ?>" href="../admin/subscriptions">
                    <div class="nav-link-icon"><i class="material-icons">card_membership</i></div>
                    Subscriptions
                </a>

                <!-- Income / Revenue -->
                <a class="nav-link <?= adminIsCollapsed(['/admin/revenue', '/admin/income']) ?> <?= adminIsActive('/admin/revenue') ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseRevenue">
                    <div class="nav-link-icon"><i class="material-icons">trending_up</i></div>
                    Revenue
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>
                <div class="collapse <?= adminIsShow(['/admin/revenue', '/admin/income']) ?>" id="collapseRevenue">
                    <nav class="drawer-menu-nested nav flex-column">
                        <a class="nav-link <?= adminIsActive('/admin/revenue/overview') ?>" href="../admin/revenue/overview">Per-User Income</a>
                        <a class="nav-link <?= adminIsActive('/admin/revenue/platform-fees') ?>" href="../admin/revenue/platform-fees">Platform Fees</a>
                        <a class="nav-link <?= adminIsActive('/admin/revenue/payouts') ?>" href="../admin/revenue/payouts">Payouts</a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- ── GATEWAYS ───────────────────────────────── -->
                <div class="drawer-menu-heading">Gateways</div>

                <!-- Gateway Overview -->
                <a class="nav-link <?= adminIsCollapsed(['/admin/gateways']) ?> <?= adminIsActive('/admin/gateways') ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseGateways">
                    <div class="nav-link-icon"><i class="material-icons">mobile_friendly</i></div>
                    M-Pesa Gateways
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>
                <div class="collapse <?= adminIsShow(['/admin/gateways']) ?>" id="collapseGateways">
                    <nav class="drawer-menu-nested nav flex-column">
                        <a class="nav-link <?= adminIsActive('/admin/gateways/index') ?>" href="../admin/gateways/index">All Gateways</a>
                        <a class="nav-link <?= adminIsActive('/admin/gateways/pending') ?>" href="../admin/gateways/pending">
                            Pending Approval
                            <span class="admin-stat-pill" style="background:rgba(251,191,36,.15);color:#fbbf24;">3</span>
                        </a>
                        <a class="nav-link <?= adminIsActive('/admin/gateways/pesaflux') ?>" href="../admin/gateways/pesaflux">PesaFlux Keys</a>
                        <a class="nav-link <?= adminIsActive('/admin/gateways/api-keys') ?>" href="../admin/gateways/api-keys">User API Keys</a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- ── BILLING ─────────────────────────────────── -->
                <div class="drawer-menu-heading">Billing</div>

                <a class="nav-link <?= adminIsCollapsed(['/admin/invoices', '/admin/billing']) ?> <?= adminIsActive('/admin/invoices') ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseBilling">
                    <div class="nav-link-icon"><i class="material-icons">receipt_long</i></div>
                    Invoices
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>
                <div class="collapse <?= adminIsShow(['/admin/invoices', '/admin/billing']) ?>" id="collapseBilling">
                    <nav class="drawer-menu-nested nav flex-column">
                        <a class="nav-link <?= adminIsActive('/admin/invoices/platform') ?>" href="../admin/invoices/platform">Platform Invoices</a>
                        <a class="nav-link <?= adminIsActive('/admin/invoices/gateway') ?>" href="../admin/invoices/gateway">Gateway Invoices</a>
                        <a class="nav-link <?= adminIsActive('/admin/invoices/overdue') ?>" href="../admin/invoices/overdue">
                            Overdue
                            <span class="admin-stat-pill" style="background:rgba(239,68,68,.12);color:#f87171;">2</span>
                        </a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- ── SYSTEM ──────────────────────────────────── -->
                <div class="drawer-menu-heading">System</div>

                <a class="nav-link <?= adminIsActive('/admin/admins') ?>" href="../admin/admins">
                    <div class="nav-link-icon"><i class="material-icons">admin_panel_settings</i></div>
                    Admin Users
                </a>

                <a class="nav-link <?= adminIsActive('/admin/audit') ?>" href="../admin/audit">
                    <div class="nav-link-icon"><i class="material-icons">history</i></div>
                    Audit Log
                </a>

                <a class="nav-link <?= adminIsActive('/admin/settings') ?>" href="../admin/settings">
                    <div class="nav-link-icon"><i class="material-icons">settings</i></div>
                    Settings
                </a>

            </div>
        </div>

        <!-- Sidebar Footer -->
        <div class="drawer-footer border-top" style="background:#0f172a;">
            <div class="d-flex align-items-center gap-2">
                <div style="
                    width: 36px; height: 36px; border-radius: 9px;
                    background: linear-gradient(135deg, #6366f1, #8b5cf6);
                    display: flex; align-items: center; justify-content: center;
                    font-weight: 700; color: #fff; font-size: .85rem; flex-shrink: 0;
                ">
                    <?= strtoupper(substr($_SESSION['admin']['full_names'] ?? 'A', 0, 1)) ?>
                </div>
                <div style="overflow: hidden;">
                    <div class="small fw-600" style="color:#e2e8f0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        <?= htmlspecialchars($_SESSION['admin']['full_names'] ?? 'Admin') ?>
                    </div>
                    <div style="font-size:.68rem; color:#475569;">Super Admin</div>
                </div>
                <a href="../auth/logout" class="btn btn-sm ms-auto p-1" style="color:#475569;" title="Sign Out">
                    <i class="material-icons" style="font-size:18px;">logout</i>
                </a>
            </div>
        </div>

    </nav>
</div>