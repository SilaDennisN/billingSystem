<div id="layoutDrawer_nav">
    <nav class="drawer accordion drawer-light bg-white" id="drawerAccordion">
        <div class="drawer-menu">
            <div class="nav">

                <!-- Core -->
                <div class="drawer-menu-heading">Core</div>

                <!-- Dashboard -->
                <a class="nav-link <?= isActive('/dashboard') ?>" href="../dashboard/index">
                    <div class="nav-link-icon"><i class="material-icons">dashboard</i></div>
                    Dashboard
                </a>

                <!-- Connections -->
                <a class="nav-link <?= isCollapsed(['/routers', '/hotspot']) ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseRouters">
                    <div class="nav-link-icon"><i class="material-icons">router</i></div>
                    Connections
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>

                <div class="collapse <?= isShow(['/routers', '/hotspot']) ?>" id="collapseRouters">
                    <nav class="drawer-menu-nested nav">
                        <a class="nav-link <?= isActive('/hotspot/live') ?>" href="../hotspot/live.php">Live Monitor</a>
                        <a class="nav-link <?= isActive('/routers') ?>" href="../routers/index">Routers</a>
                        <a class="nav-link <?= isActive('/hotspotUsers') ?>" href="../hotspotUsers/index">Users</a>
                    </nav>
                </div>

                <!-- Billing -->
                <a class="nav-link <?= isCollapsed(['/plans']) ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseBilling">
                    <div class="nav-link-icon"><i class="material-icons">payments</i></div>
                    Billing
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>

                <div class="collapse <?= isShow(['/plans', '/hotspotProfiles']) ?>" id="collapseBilling">
                    <nav class="drawer-menu-nested nav">
                        <a class="nav-link <?= isActive('/hotspotprofiles') ?>" href="../hotspotProfiles/profiles">Packages</a>
                        <a class="nav-link <?= isActive('/plans/invoices') ?>" href="../plans/invoices">Invoices</a>
                        <a class="nav-link <?= isActive('/plans/payments') ?>" href="../plans/payments">Payments</a>
                    </nav>
                </div>

                <!-- expenses -->
                <a class="nav-link <?= isActive('/expenses') ?>" href="../expenses/index">
                    <div class="nav-link-icon"><i class="material-icons">people</i></div>
                    Expenses
                </a>

                <!-- Reports -->
                <!-- Reports -->
                <a class="nav-link <?= isActive('/reports') ?>" href="../reports/index">
                    <div class="nav-link-icon"><i class="material-icons">assessment</i></div>
                    Detailed Reports
                </a>

                <div class="drawer-menu-divider"></div>

                <!-- System -->
                <div class="drawer-menu-heading">System</div>
                <?php if ($_SESSION['user']['role'] === "admin"): ?>
                    <a class="nav-link <?= isActive('/system/expiry') ?>" href="../system/expiry">
                        <div class="nav-link-icon">
                            <i class="material-icons">schedule</i>
                        </div>
                        Expiry Engine
                    </a>

                    <a class="nav-link <?= isActive('/monitor/monitor') ?>" href="../monitor/monitor">
                        <div class="nav-link-icon">
                            <i class="material-icons">desktop_mac</i>
                        </div>
                        Monitor
                    </a>

                <?php endif; ?>

                <a class="nav-link <?= isActive('/users') ?>" href="../system/users">
                    <div class="nav-link-icon"><i class="material-icons">people</i></div>
                    System Users
                </a>

                <!-- Settings -->
                <a class="nav-link <?= isCollapsed(['/devices']) ?>"
                    href="javascript:void(0);"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapseSettings">
                    <div class="nav-link-icon"><i class="material-icons">settings</i></div>
                    Settings
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>

                <div class="collapse <?= isShow(['/devices']) ?>" id="collapseSettings">
                    <nav class="drawer-menu-nested nav">
                        <a class="nav-link <?= isActive('/devices/settings') ?>" href="../devices/settings">System Settings</a>
                        <a class="nav-link <?= isActive('/devices/index') ?>" href="../devices/index">Router Settings</a>
                        <a class="nav-link <?= isActive('/devices/security') ?>" href="../devices/security">Security</a>
                    </nav>
                </div>

                <a class="nav-link <?= isActive('/subscription') ?>" href="../subscription/index">
                    <div class="nav-link-icon"><i class="material-icons">dashboard</i></div>
                    Subscription
                </a>

            </div>
        </div>

        <!-- Footer -->
        <div class="drawer-footer border-top">
            <div class="d-flex align-items-center">
                <i class="material-icons text-muted">account_circle</i>
                <div class="ms-3">
                    <div class="caption">Logged in as:</div>
                    <div class="small fw-500"><?= $_SESSION['user']['full_names'] ?? 'User' ?></div>
                    <div class="small fw-500"><?= $_SESSION['user']['phone_number'] ?? 'phone' ?></div>
                </div>
            </div>
        </div>
    </nav>
</div>