<!-- Enhanced Add User Modal -->
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
                            <label class="form-label">
                                <i class="fa fa-server me-1"></i>Router
                            </label>
                            <select name="router_id" class="form-select" required>
                                <option value="">Select Router</option>
                                <?php foreach ($routers as $r): ?>
                                    <option value="<?= $r['router_id'] ?>" <?= $r['router_id'] == $router_id ? 'selected' : '' ?>>
                                        <?= $r['name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fa fa-box me-1"></i>Plan / Profile
                            </label>
                            <select name="plan_id" class="form-select" required>
                                <option value="">Select Plan</option>
                                <?php foreach ($plans as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= $p['profile_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-user me-1"></i>Username
                            </label>
                            <input type="text" name="username" class="form-control" placeholder="Enter username" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-key me-1"></i>Password
                            </label>
                            <input type="password" name="password" id="addUserPassword" class="form-control" placeholder="Enter password" required>
                        </div>

                        <!-- Installation Fee Toggle -->
                        <div class="col-12">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0 fw-semibold">
                                            <i class="fa fa-money-bill-wave me-1 text-success"></i>
                                            Installation Fee Paid?
                                        </label>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                id="installFeeToggle"
                                                name="installation_fee_paid"
                                                value="1"
                                                role="switch">
                                        </div>
                                    </div>

                                    <!-- Fee Amount — hidden until toggle is ON -->
                                    <div id="installFeeAmountWrap" class="mt-3" style="display:none;">
                                        <label class="form-label">
                                            <i class="fa fa-coins me-1"></i>Amount Paid
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">KES</span>
                                            <input type="number"
                                                name="installation_fee"
                                                id="installFeeAmount"
                                                class="form-control"
                                                placeholder="e.g. 1500"
                                                min="0"
                                                step="0.01">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Send Credentials via Email -->
                        <div class="col-12">
                            <div class="card bg-light border-0">
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0 fw-semibold">
                                            <i class="fa fa-envelope me-1 text-primary"></i>
                                            Send Credentials via Email?
                                        </label>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox"
                                                id="sendEmailToggle"
                                                name="send_email"
                                                value="1"
                                                role="switch">
                                        </div>
                                    </div>

                                    <!-- Email field — hidden until toggle is ON -->
                                    <div id="sendEmailWrap" class="mt-3" style="display:none;">
                                        <label class="form-label">
                                            <i class="fa fa-at me-1"></i>Customer Email Address
                                        </label>
                                        <input type="email"
                                            name="customer_email"
                                            id="customerEmail"
                                            class="form-control"
                                            placeholder="customer@example.com">
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
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-check me-1"></i>Create PPPoE User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle JS — scoped to this modal only -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    // Installation fee toggle
    const feeToggle = document.getElementById("installFeeToggle");
    const feeWrap   = document.getElementById("installFeeAmountWrap");
    const feeInput  = document.getElementById("installFeeAmount");

    feeToggle.addEventListener("change", function () {
        feeWrap.style.display = this.checked ? "block" : "none";
        feeInput.required     = this.checked;
        if (!this.checked) feeInput.value = "";
    });

    // Send email toggle
    const emailToggle = document.getElementById("sendEmailToggle");
    const emailWrap   = document.getElementById("sendEmailWrap");
    const emailInput  = document.getElementById("customerEmail");

    emailToggle.addEventListener("change", function () {
        emailWrap.style.display = this.checked ? "block" : "none";
        emailInput.required     = this.checked;
        if (!this.checked) emailInput.value = "";
    });

});
</script>


<!-- ===== ALL OTHER MODALS BELOW — UNCHANGED ===== -->

<!-- VIEW MODAL -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-eye me-2"></i>View User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tr><th>Username</th><td id="view_username"></td></tr>
                    <tr><th>Router</th><td id="view_router"></td></tr>
                    <tr><th>Plan</th><td id="view_plan"></td></tr>
                    <tr><th>Status</th><td id="view_status"></td></tr>
                    <tr><th>Expires</th><td id="view_expiry"></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="update.php">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title"><i class="fa fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="username" id="edit_username">
                    <div class="mb-3">
                        <label>Plan</label>
                        <select name="plan_id" id="edit_plan" class="form-select">
                            <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= $p['profile_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Expiry</label>
                        <input type="datetime-local" name="expires_at" id="edit_expiry" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteUserModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="delete.php">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete User</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="username" id="delete_username">
                    Are you sure you want to delete: <strong id="delete_username_text"></strong>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>