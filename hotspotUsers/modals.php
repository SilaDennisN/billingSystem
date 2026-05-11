<!-- ═══════════════════════════════════════════════════════════════
     ADD USER MODAL
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="store.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-user-plus me-2"></i>Add PPPoE User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <input type="hidden" name="router_id" value="<?= $router_id ?>">
                        <input type="hidden" name="user_type" value="pppoe">

                        <div class="col-md-12">
                            <label class="form-label"><i class="fa fa-server me-1"></i>Router</label>
                            <select name="router_id" class="form-select" required>
                                <option value="">Select Router</option>
                                <?php foreach ($routers as $r): ?>
                                    <option value="<?= $r['router_id'] ?>" <?= $r['router_id'] == $router_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label"><i class="fa fa-box me-1"></i>Plan / Profile</label>
                            <select name="plan_id" class="form-select" required>
                                <option value="">Select Plan</option>
                                <?php foreach ($plans as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['profile_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><i class="fa fa-user me-1"></i>Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><i class="fa fa-key me-1"></i>Password</label>
                            <input type="password" name="password" id="addUserPassword" class="form-control" placeholder="Enter password" required>
                        </div>

                        <!-- Installation Fee -->
                        <div class="col-12">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0 fw-semibold">
                                            <i class="fa fa-money-bill-wave me-1 text-success"></i>Installation Fee Paid?
                                        </label>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="installFeeToggle" name="installation_fee_paid" value="1" role="switch">
                                        </div>
                                    </div>
                                    <div id="installFeeAmountWrap" class="mt-3" style="display:none;">
                                        <label class="form-label"><i class="fa fa-coins me-1"></i>Amount Paid</label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="number" name="installation_fee" id="installFeeAmount" class="form-control" placeholder="e.g. 1500" min="0" step="0.01">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Send Credentials -->
                        <div class="col-12">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0 fw-semibold">
                                            <i class="fa fa-envelope me-1 text-primary"></i>Send Credentials via Email?
                                        </label>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="sendEmailToggle" name="send_email" value="1" role="switch">
                                        </div>
                                    </div>
                                    <div id="sendEmailWrap" class="mt-3" style="display:none;">
                                        <label class="form-label"><i class="fa fa-at me-1"></i>Customer Email Address</label>
                                        <input type="email" name="customer_email" id="customerEmail" class="form-control" placeholder="customer@example.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>
                                <strong>Note:</strong> The user will be created on the selected router with the chosen plan configuration.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-1"></i>Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-check me-1"></i>Create PPPoE User</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     VIEW USER MODAL  — polished profile card style
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">

            <!-- Coloured banner -->
            <div id="view_banner" class="p-4 text-white position-relative">
                <!-- type badge top-left -->
                <span id="view_type_badge" class="badge bg-white bg-opacity-25 fw-semibold fs-7 position-absolute top-0 start-0 m-3"></span>
                <!-- online dot top-right -->
                <span id="view_online_pill" class="badge position-absolute top-0 end-0 m-3"></span>
                <!-- close -->
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 mt-4 me-2" data-bs-dismiss="modal" style="margin-top:2.8rem!important;"></button>

                <div class="d-flex align-items-center gap-3 mt-3">
                    <div id="view_avatar" class="rounded-circle d-flex align-items-center justify-content-center bg-white bg-opacity-25" style="width:56px;height:56px;font-size:1.4rem;">
                        <i class="fa fa-user text-white"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold" id="view_username"></h4>
                        <small id="view_time_remaining" class="opacity-75"></small>
                    </div>
                </div>
            </div>

            <!-- Detail rows -->
            <div class="modal-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <span class="rounded-circle bg-primary-soft d-flex align-items-center justify-content-center" style="width:38px;height:38px;min-width:38px;">
                            <i class="fa fa-server text-primary"></i>
                        </span>
                        <div>
                            <div class="small text-muted">Router</div>
                            <div class="fw-semibold" id="view_router">—</div>
                        </div>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <span class="rounded-circle bg-info-soft d-flex align-items-center justify-content-center" style="width:38px;height:38px;min-width:38px;">
                            <i class="fa fa-box text-info"></i>
                        </span>
                        <div>
                            <div class="small text-muted">Plan / Profile</div>
                            <div class="fw-semibold" id="view_plan">—</div>
                        </div>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <span class="rounded-circle bg-success-soft d-flex align-items-center justify-content-center" style="width:38px;height:38px;min-width:38px;">
                            <i class="fa fa-check-circle text-success"></i>
                        </span>
                        <div>
                            <div class="small text-muted">Account Status</div>
                            <div id="view_status">—</div>
                        </div>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <span class="rounded-circle bg-warning-soft d-flex align-items-center justify-content-center" style="width:38px;height:38px;min-width:38px;">
                            <i class="fa fa-calendar-alt text-warning"></i>
                        </span>
                        <div>
                            <div class="small text-muted">Expires At</div>
                            <div class="fw-semibold" id="view_expiry">—</div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     EDIT USER MODAL  — PPPoE only
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="update.php">

                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="fa fa-edit me-2 text-secondary"></i>Edit PPPoE User</h5>
                        <p class="text-muted small mb-0">Editing: <span class="fw-semibold text-dark" id="edit_username_display"></span></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">
                    <input type="hidden" name="username" id="edit_username">

                    <!-- Plan -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="fa fa-box me-1 text-info"></i>Plan / Profile
                        </label>
                        <select name="plan_id" id="edit_plan" class="form-select">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['profile_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Changing plan updates the PPPoE profile on the router immediately.</div>
                    </div>

                    <!-- Expiry -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold">
                            <i class="fa fa-calendar-alt me-1 text-warning"></i>Expiry Date &amp; Time
                        </label>
                        <input type="datetime-local" name="expires_at" id="edit_expiry" class="form-control" required>
                        <div class="form-text">Leave the exact time or adjust to extend / shorten access.</div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-secondary px-4">
                        <i class="fa fa-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     EXTEND TIME MODAL  — hotspot (30m/1h/3h/1d) + pppoe (+days)
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="extendUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="extend.php">

                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="fa fa-clock me-2 text-success"></i>Extend Access</h5>
                        <p class="text-muted small mb-0">User: <span class="fw-semibold text-dark" id="extend_username_display"></span></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body pt-3">
                    <input type="hidden" name="username"  id="extend_username">
                    <input type="hidden" name="user_type" id="extend_user_type">
                    <!-- One of these will be set by JS depending on chosen option -->
                    <input type="hidden" name="extend_minutes" id="extend_minutes" value="0">
                    <input type="hidden" name="extend_days"    id="extend_days"    value="0">

                    <!-- Current expiry display -->
                    <div class="alert alert-light border d-flex align-items-center gap-2 py-2 mb-4">
                        <i class="fa fa-calendar text-muted"></i>
                        <div>
                            <div class="small text-muted">Current Expiry</div>
                            <div class="fw-semibold" id="extend_current_expiry">—</div>
                        </div>
                    </div>

                    <!-- HOTSPOT options (shown for hotspot users) -->
                    <div id="extend_hotspot_options">
                        <p class="fw-semibold text-muted small text-uppercase mb-2">Add time</p>
                        <div class="d-grid gap-2">
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-success w-100 extend-time-btn" data-minutes="30">
                                        <i class="fa fa-plus me-1"></i>30 Minutes
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-success w-100 extend-time-btn" data-minutes="60">
                                        <i class="fa fa-plus me-1"></i>1 Hour
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-success w-100 extend-time-btn" data-minutes="180">
                                        <i class="fa fa-plus me-1"></i>3 Hours
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-outline-success w-100 extend-time-btn" data-minutes="1440">
                                        <i class="fa fa-plus me-1"></i>1 Day
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PPPOE options (shown for pppoe users) -->
                    <div id="extend_pppoe_options" style="display:none;">
                        <p class="fw-semibold text-muted small text-uppercase mb-2">Extend by</p>
                        <div class="row g-2">
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-info w-100 extend-day-btn" data-days="1">
                                    <i class="fa fa-plus me-1"></i>1 Day
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-info w-100 extend-day-btn" data-days="3">
                                    <i class="fa fa-plus me-1"></i>3 Days
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-info w-100 extend-day-btn" data-days="7">
                                    <i class="fa fa-plus me-1"></i>7 Days
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selected extension summary -->
                    <div id="extend_summary" class="alert alert-success border-0 mt-3 py-2 d-none">
                        <i class="fa fa-check-circle me-1"></i>
                        <span id="extend_summary_text"></span>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="extend_submit_btn" class="btn btn-success px-4" disabled>
                        <i class="fa fa-clock me-1"></i>Confirm Extension
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     DELETE USER MODAL  — with confirmation input
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="delete.php">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="fa fa-trash me-2"></i>Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="username" id="delete_username">

                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-danger-soft d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                            <i class="fa fa-exclamation-triangle text-danger fa-xl"></i>
                        </div>
                        <h6 class="fw-bold">Are you sure?</h6>
                        <p class="text-muted small mb-0">
                            This will permanently remove
                            <strong class="text-dark" id="delete_username_text"></strong>
                            from both the database and the router. This action cannot be undone.
                        </p>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Type the username to confirm</label>
                        <input type="text" id="delete_confirm_input" class="form-control form-control-sm" placeholder="e.g. john_doe" autocomplete="off">
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="delete_confirm_btn" class="btn btn-danger px-4" disabled>
                        <i class="fa fa-trash me-1"></i>Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════
     MODAL JAVASCRIPT
════════════════════════════════════════════════════════════════ -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    /* ── Add User toggles ─────────────────────────────────────────── */
    const feeToggle = document.getElementById("installFeeToggle");
    const feeWrap   = document.getElementById("installFeeAmountWrap");
    const feeInput  = document.getElementById("installFeeAmount");

    if (feeToggle) {
        feeToggle.addEventListener("change", function () {
            feeWrap.style.display = this.checked ? "block" : "none";
            feeInput.required     = this.checked;
            if (!this.checked) feeInput.value = "";
        });
    }

    const emailToggle = document.getElementById("sendEmailToggle");
    const emailWrap   = document.getElementById("sendEmailWrap");
    const emailInput  = document.getElementById("customerEmail");

    if (emailToggle) {
        emailToggle.addEventListener("change", function () {
            emailWrap.style.display = this.checked ? "block" : "none";
            emailInput.required     = this.checked;
            if (!this.checked) emailInput.value = "";
        });
    }


    /* ── View modal ──────────────────────────────────────────────── */
    document.addEventListener("click", function (e) {

        /* VIEW */
        const viewBtn = e.target.closest(".viewBtn");
        if (viewBtn) {
            const d        = viewBtn.dataset;
            const isOnline = d.online === "1";
            const isPppoe  = d.type   === "pppoe";
            const expDate  = new Date(d.expiry);
            const now      = new Date();

            // Banner colour
            const banner = document.getElementById("view_banner");
            banner.className = "p-4 text-white position-relative " + (isPppoe ? "bg-info" : "bg-secondary");

            // Avatar icon
            document.getElementById("view_avatar").innerHTML =
                `<i class="fa fa-${isPppoe ? 'network-wired' : 'rss'} text-white" style="font-size:1.3rem;"></i>`;

            // Type badge
            document.getElementById("view_type_badge").textContent = isPppoe ? "PPPoE" : "Hotspot";

            // Online pill
            const onlinePill = document.getElementById("view_online_pill");
            onlinePill.className = "badge position-absolute top-0 end-0 m-3 " + (isOnline ? "bg-success" : "bg-secondary");
            onlinePill.innerHTML = `<i class="fa fa-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i>${isOnline ? 'Online' : 'Offline'}`;

            // Username
            document.getElementById("view_username").textContent = d.username;

            // Time remaining
            const diffMs = expDate - now;
            let timeStr;
            if (diffMs <= 0) {
                timeStr = "⚠ Expired";
            } else {
                const days  = Math.floor(diffMs / 86400000);
                const hours = Math.floor((diffMs % 86400000) / 3600000);
                const mins  = Math.floor((diffMs % 3600000)  / 60000);
                if (days > 0)       timeStr = `Expires in ${days}d ${hours}h`;
                else if (hours > 0) timeStr = `Expires in ${hours}h ${mins}m`;
                else                timeStr = `Expires in ${mins} min`;
            }
            document.getElementById("view_time_remaining").textContent = timeStr;

            // Details
            document.getElementById("view_router").textContent = d.router || "—";
            document.getElementById("view_plan").textContent   = d.plan   || "No plan";

            // Status badge
            const statusEl = document.getElementById("view_status");
            const st = d.status;
            const expired = expDate < now;
            if (st === 'active' && !expired) {
                statusEl.innerHTML = '<span class="badge bg-success-soft text-success"><i class="fa fa-check-circle me-1"></i>Active</span>';
            } else if (expired) {
                statusEl.innerHTML = '<span class="badge bg-warning-soft text-warning"><i class="fa fa-clock me-1"></i>Expired</span>';
            } else {
                statusEl.innerHTML = '<span class="badge bg-danger-soft text-danger"><i class="fa fa-ban me-1"></i>Inactive</span>';
            }

            // Expiry
            document.getElementById("view_expiry").textContent =
                expDate.toLocaleString('en-KE', {dateStyle:'medium', timeStyle:'short'});
        }


        /* EDIT */
        const editBtn = e.target.closest(".editBtn");
        if (editBtn) {
            const d = editBtn.dataset;
            document.getElementById("edit_username").value          = d.username;
            document.getElementById("edit_username_display").textContent = d.username;
            document.getElementById("edit_plan").value              = d.plan;
            document.getElementById("edit_expiry").value            = d.expiry.replace(" ", "T").substring(0, 16);
        }


        /* EXTEND */
        const extendBtn = e.target.closest(".extendBtn");
        if (extendBtn) {
            const d       = extendBtn.dataset;
            const isPppoe = d.type === "pppoe";

            document.getElementById("extend_username").value         = d.username;
            document.getElementById("extend_username_display").textContent = d.username;
            document.getElementById("extend_user_type").value        = d.type;

            // Reset state
            document.getElementById("extend_minutes").value = "0";
            document.getElementById("extend_days").value    = "0";
            document.getElementById("extend_submit_btn").disabled = true;
            document.getElementById("extend_summary").classList.add("d-none");
            document.querySelectorAll(".extend-time-btn, .extend-day-btn")
                .forEach(b => b.classList.remove("btn-success","btn-info","active"));

            // Show correct option set
            document.getElementById("extend_hotspot_options").style.display = isPppoe ? "none" : "";
            document.getElementById("extend_pppoe_options").style.display   = isPppoe ? ""     : "none";

            // Format current expiry
            const exp = new Date(d.expiry);
            document.getElementById("extend_current_expiry").textContent =
                exp.toLocaleString('en-KE', {dateStyle:'medium', timeStyle:'short'});
        }


        /* DELETE */
        const deleteBtn = e.target.closest(".deleteBtn");
        if (deleteBtn) {
            const username = deleteBtn.dataset.username;
            document.getElementById("delete_username").value         = username;
            document.getElementById("delete_username_text").textContent = username;
            document.getElementById("delete_confirm_input").value   = "";
            document.getElementById("delete_confirm_btn").disabled  = true;
        }
    });


    /* ── Extend: hotspot time buttons ─────────────────────────────── */
    document.querySelectorAll(".extend-time-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            document.querySelectorAll(".extend-time-btn")
                .forEach(b => b.classList.remove("btn-success", "active"));
            this.classList.add("btn-success", "active");

            const mins = parseInt(this.dataset.minutes);
            document.getElementById("extend_minutes").value = mins;
            document.getElementById("extend_days").value    = "0";
            document.getElementById("extend_submit_btn").disabled = false;

            let label = mins < 60 ? `${mins} minutes` : mins === 1440 ? "1 day" : `${mins/60} hours`;
            document.getElementById("extend_summary_text").textContent = `Adding ${label} to this user's access.`;
            document.getElementById("extend_summary").classList.remove("d-none");
        });
    });

    /* ── Extend: pppoe day buttons ────────────────────────────────── */
    document.querySelectorAll(".extend-day-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            document.querySelectorAll(".extend-day-btn")
                .forEach(b => b.classList.remove("btn-info", "active"));
            this.classList.add("btn-info", "active");

            const days = parseInt(this.dataset.days);
            document.getElementById("extend_days").value    = days;
            document.getElementById("extend_minutes").value = "0";
            document.getElementById("extend_submit_btn").disabled = false;

            document.getElementById("extend_summary_text").textContent =
                `Adding ${days} day${days > 1 ? 's' : ''} to this user's subscription.`;
            document.getElementById("extend_summary").classList.remove("d-none");
        });
    });


    /* ── Delete: confirm input ────────────────────────────────────── */
    const deleteInput = document.getElementById("delete_confirm_input");
    const deleteBtn2  = document.getElementById("delete_confirm_btn");

    if (deleteInput) {
        deleteInput.addEventListener("input", function () {
            const expected = document.getElementById("delete_username").value;
            deleteBtn2.disabled = (this.value.trim() !== expected);
        });
    }


    /* ── Reset extend modal on close ─────────────────────────────── */
    const extendModal = document.getElementById("extendUserModal");
    if (extendModal) {
        extendModal.addEventListener("hidden.bs.modal", function () {
            document.querySelectorAll(".extend-time-btn, .extend-day-btn")
                .forEach(b => b.classList.remove("btn-success","btn-info","active"));
            document.getElementById("extend_submit_btn").disabled = true;
            document.getElementById("extend_summary").classList.add("d-none");
        });
    }

    /* ── Reset delete confirm on close ───────────────────────────── */
    const deleteModal = document.getElementById("deleteUserModal");
    if (deleteModal) {
        deleteModal.addEventListener("hidden.bs.modal", function () {
            if (deleteInput) deleteInput.value = "";
            if (deleteBtn2)  deleteBtn2.disabled = true;
        });
    }

});
</script>