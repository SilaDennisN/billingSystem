<?php
// admin/partials/topnav.php
// Requires: $_SESSION['admin'] to be set with 'full_names' and 'email'
?>
<nav class="top-app-bar navbar navbar-expand navbar-dark" style="background: #0f172a; border-bottom: 1px solid rgba(99,102,241,.25);">
    <div class="container-fluid px-4">

        <!-- Drawer toggle -->
        <button class="btn btn-lg btn-icon order-1 order-lg-0" id="drawerToggle" href="javascript:void(0);">
            <i class="material-icons" style="color:#a5b4fc;">menu</i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand me-auto d-flex align-items-center gap-2" href="../admin/dashboard">
            <span style="
                background: linear-gradient(135deg,#6366f1,#818cf8);
                border-radius: 8px;
                width: 28px; height: 28px;
                display: flex; align-items: center; justify-content: center;
                font-size: 14px; font-weight: 900; color: #fff; letter-spacing: -1px;
                flex-shrink: 0;
            ">A</span>
            <span class="font-monospace fw-bold text-white" style="letter-spacing:.04em; font-size:.9rem;">
                INOVATECH <span style="color:#818cf8;">ADMIN</span>
            </span>
        </a>

        <!-- Right side -->
        <div class="d-flex align-items-center gap-2">

            <!-- Environment badge -->
            <span class="badge d-none d-md-inline-flex align-items-center gap-1"
                style="background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); font-size:.7rem; padding:5px 10px; border-radius:20px;">
                <span style="width:6px;height:6px;background:#4ade80;border-radius:50%;display:inline-block;"></span>
                Control Panel
            </span>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-lg btn-icon position-relative" id="adminNotifToggle" data-bs-toggle="dropdown" aria-expanded="false"
                    style="color:#94a3b8;">
                    <i class="material-icons">notifications_none</i>
                    <span class="position-absolute top-0 end-0 translate-middle badge rounded-pill"
                        style="background:#ef4444; font-size:.6rem; padding:3px 5px;">
                        3
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 py-0 overflow-hidden"
                    style="width:300px; border-radius:12px; background:#1e293b;">
                    <li>
                        <div class="px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.07);">
                            <span class="fw-bold text-white small">Notifications</span>
                        </div>
                    </li>
                    <li>
                        <a class="dropdown-item py-3 px-4" href="../admin/users" style="color:#cbd5e1; border-bottom:1px solid rgba(255,255,255,.05);">
                            <div class="d-flex gap-3 align-items-start">
                                <span style="width:32px;height:32px;background:rgba(99,102,241,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="material-icons" style="font-size:16px;color:#818cf8;">person_add</i>
                                </span>
                                <div>
                                    <div class="small fw-500 text-white">New user registered</div>
                                    <div style="font-size:.7rem;color:#64748b;">2 minutes ago</div>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-3 px-4" href="../admin/gateways" style="color:#cbd5e1; border-bottom:1px solid rgba(255,255,255,.05);">
                            <div class="d-flex gap-3 align-items-start">
                                <span style="width:32px;height:32px;background:rgba(234,179,8,.15);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="material-icons" style="font-size:16px;color:#fbbf24;">mobile_friendly</i>
                                </span>
                                <div>
                                    <div class="small fw-500 text-white">Gateway awaiting approval</div>
                                    <div style="font-size:.7rem;color:#64748b;">14 minutes ago</div>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-3 px-4" href="../admin/invoices" style="color:#cbd5e1;">
                            <div class="d-flex gap-3 align-items-start">
                                <span style="width:32px;height:32px;background:rgba(74,222,128,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="material-icons" style="font-size:16px;color:#4ade80;">receipt_long</i>
                                </span>
                                <div>
                                    <div class="small fw-500 text-white">Invoice #0042 paid</div>
                                    <div style="font-size:.7rem;color:#64748b;">1 hour ago</div>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-3 px-4 text-center" href="../admin/notifications"
                            style="color:#818cf8; font-size:.8rem; border-top:1px solid rgba(255,255,255,.07);">
                            View all notifications
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Admin profile dropdown -->
            <div class="dropdown">
                <button class="btn d-flex align-items-center gap-2 px-2 py-1" id="adminProfileToggle"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    style="background:rgba(99,102,241,.12); border:1px solid rgba(99,102,241,.25); border-radius:10px; color:#e2e8f0;">
                    <span style="
                        width:30px; height:30px; border-radius:7px;
                        background: linear-gradient(135deg,#6366f1,#8b5cf6);
                        display:flex; align-items:center; justify-content:center;
                        font-size:.8rem; font-weight:700; color:#fff; flex-shrink:0;
                    ">
                        <?= strtoupper(substr($_SESSION['admin']['full_names'] ?? 'A', 0, 1)) ?>
                    </span>
                    <span class="d-none d-md-block small fw-500"><?= htmlspecialchars($_SESSION['admin']['full_names'] ?? 'Admin') ?></span>
                    <i class="material-icons" style="font-size:16px; color:#64748b;">expand_more</i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 py-0 overflow-hidden"
                    style="width:220px; border-radius:12px; background:#1e293b; min-width:180px;">
                    <li>
                        <div class="px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.07);">
                            <div class="small fw-bold text-white"><?= htmlspecialchars($_SESSION['admin']['full_names'] ?? 'Admin') ?></div>
                            <div style="font-size:.7rem; color:#64748b;"><?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?></div>
                        </div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 px-4" href="../admin/profile"
                            style="color:#cbd5e1; font-size:.85rem;">
                            <i class="material-icons leading-icon" style="font-size:18px; color:#818cf8;">manage_accounts</i>
                            My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 px-4" href="../admin/settings"
                            style="color:#cbd5e1; font-size:.85rem;">
                            <i class="material-icons leading-icon" style="font-size:18px; color:#818cf8;">tune</i>
                            Admin Settings
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 px-4" href="../help/index"
                            style="color:#cbd5e1; font-size:.85rem;">
                            <i class="material-icons leading-icon" style="font-size:18px; color:#818cf8;">help_outline</i>
                            Help
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-0" style="border-color:rgba(255,255,255,.07);"></li>
                    <li>
                        <a class="dropdown-item py-2 px-4 mb-1" href="../auth/logout"
                            style="color:#f87171; font-size:.85rem;">
                            <i class="material-icons leading-icon" style="font-size:18px; color:#f87171;">logout</i>
                            Sign Out
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</nav>