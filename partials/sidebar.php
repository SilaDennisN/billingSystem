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
                        <a class="nav-link <?= isActive('/hotspot/live') ?>" href="../hotspot/live.php">Sessions</a>
                        <a class="nav-link <?= isActive('/routers') ?>" href="../routers/index">Routers</a>
                        <a class="nav-link <?= isActive('/hotspotUsers') ?>" href="../hotspotUsers/index">Users</a>
                        <a class="nav-link <?= isActive('/hotspotprofiles') ?>" href="../hotspotprofiles/profiles">Profiles</a>
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

                <div class="collapse <?= isShow(['/plans']) ?>" id="collapseBilling">
                    <nav class="drawer-menu-nested nav">
                        <a class="nav-link <?= isActive('/plans/invoices') ?>" href="../plans/invoices">Invoices</a>
                        <a class="nav-link <?= isActive('/plans/payments') ?>" href="../plans/payments">Payments</a>
                    </nav>
                </div>

                <!-- Reports -->
                <a class="nav-link <?= isCollapsed(['/reports']) ?>"
                   href="javascript:void(0);"
                   data-bs-toggle="collapse"
                   data-bs-target="#collapseReports">
                    <div class="nav-link-icon"><i class="material-icons">assessment</i></div>
                    Reports
                    <div class="drawer-collapse-arrow"><i class="material-icons">expand_more</i></div>
                </a>

                <div class="collapse <?= isShow(['/reports']) ?>" id="collapseReports">
                    <nav class="drawer-menu-nested nav">
                        <a class="nav-link <?= isActive('/reports/sales') ?>" href="/reports/sales">Sales Reports</a>
                        <a class="nav-link <?= isActive('/reports/usage') ?>" href="/reports/usage">Usage Reports</a>
                        <a class="nav-link <?= isActive('/reports/logs') ?>" href="/reports/logs">System Logs</a>
                    </nav>
                </div>

                <div class="drawer-menu-divider"></div>

                <!-- System -->
                <div class="drawer-menu-heading">System</div>

                <a class="nav-link <?= isActive('/system/expiry') ?>" href="../system/expiry">
                    <div class="nav-link-icon"><i class="material-icons">schedule</i></div>
                    Expiry Engine
                </a>

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

            </div>
        </div>

        <!-- Footer -->
        <div class="drawer-footer border-top">
            <div class="d-flex align-items-center">
                <i class="material-icons text-muted">account_circle</i>
                <div class="ms-3">
                    <div class="caption">Logged in as:</div>
                    <div class="small fw-500"><?= $_SESSION['user']['username'] ?? 'User' ?></div>
                </div>
            </div>
        </div>
    </nav>
</div>
