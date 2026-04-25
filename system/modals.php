<?php
/* Fetch all active routers for the assign-routers checkboxes */
$modalRouters = $allRouters ?? $pdo->query("SELECT router_id, name FROM routers WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ===================== ADD USER ===================== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="create">

                <div class="modal-header px-4 py-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <div>
                        <h5 class="modal-title mb-0" style="font-weight:700;color:#1e293b;">Add System User</h5>
                        <small style="color:#94a3b8;">Create a new user account</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Username <span class="text-danger">*</span></label>
                        <input name="username" class="form-control" required placeholder="e.g. john_doe">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Email</label>
                        <input name="email" type="email" class="form-control" placeholder="user@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Password <span class="text-danger">*</span></label>
                        <input name="password" type="password" class="form-control" required placeholder="Minimum 6 characters">
                    </div>

                    <?php if (!empty($modalRouters)): ?>
                        <div class="mb-1">
                            <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Assign Routers</label>
                            <div style="border:1px solid #e2e8f0;border-radius:8px;max-height:160px;overflow-y:auto;padding:8px 12px;">
                                <?php foreach ($modalRouters as $r): ?>
                                    <div class="form-check py-1">
                                        <input class="form-check-input" type="checkbox" name="router_ids[]" value="<?= $r['router_id'] ?>" id="add_r_<?= $r['router_id'] ?>">
                                        <label class="form-check-label" for="add_r_<?= $r['router_id'] ?>" style="font-size:0.85rem;color:#374151;">
                                            <i class="fa fa-server me-1" style="color:#94a3b8;font-size:0.75rem;"></i><?= htmlspecialchars($r['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small style="color:#94a3b8;font-size:0.75rem;">Only routers assigned to you are shown.</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer px-4 py-3" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-4" style="background:#3b82f6;color:#fff;border:none;border-radius:7px;font-weight:600;">
                        <i class="fa fa-plus me-1"></i>Create User
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ===================== EDIT USER ===================== -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit-id">

                <div class="modal-header px-4 py-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <div>
                        <h5 class="modal-title mb-0" style="font-weight:700;color:#1e293b;">Edit User</h5>
                        <small style="color:#94a3b8;">Update account details</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Username</label>
                        <input name="username" id="edit-username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Email</label>
                        <input name="email" id="edit-email" type="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Role</label>
                        <select name="role" id="edit-role" class="form-select">
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Status</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="active">Active</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer px-4 py-3" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-4" style="background:#3b82f6;color:#fff;border:none;border-radius:7px;font-weight:600;">
                        <i class="fa fa-save me-1"></i>Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ===================== ASSIGN ROUTERS ===================== -->
<div class="modal fade" id="assignRoutersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="assign_routers">
                <input type="hidden" name="user_id" id="assign-user-id">

                <div class="modal-header px-4 py-3" style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                    <div>
                        <h5 class="modal-title mb-0" style="font-weight:700;color:#1e293b;">Assign Routers</h5>
                        <small style="color:#94a3b8;">For <strong id="assignRouterTitle"></strong></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body px-4 py-4">
                    <?php if (!empty($modalRouters)): ?>
                        <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                            <?php foreach ($modalRouters as $i => $r): ?>
                                <div class="form-check px-4 py-2" style="<?= $i > 0 ? 'border-top:1px solid #f1f5f9;' : '' ?>background:<?= $i % 2 === 0 ? '#fff' : '#f8fafc' ?>;">
                                    <input class="form-check-input router-checkbox" type="checkbox"
                                        name="router_ids[]" value="<?= $r['router_id'] ?>" id="asgn_r_<?= $r['router_id'] ?>">
                                    <label class="form-check-label w-100" for="asgn_r_<?= $r['router_id'] ?>" style="font-size:0.85rem;color:#374151;cursor:pointer;">
                                        <i class="fa fa-server me-2" style="color:#94a3b8;font-size:0.75rem;"></i><?= htmlspecialchars($r['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No active routers found.</p>
                    <?php endif; ?>
                </div>

                <div class="modal-footer px-4 py-3" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-4" style="background:#0ea5e9;color:#fff;border:none;border-radius:7px;font-weight:600;">
                        <i class="fa fa-server me-1"></i>Save Assignments
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ===================== RESET PASSWORD ===================== -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset-id">

                <div class="modal-body px-4 py-4 text-center">
                    <div style="width:52px;height:52px;background:#fffbeb;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fa fa-lock-open" style="color:#f59e0b;font-size:1.3rem;"></i>
                    </div>
                    <h5 style="font-weight:700;color:#1e293b;margin-bottom:6px;">Reset Password</h5>
                    <p style="color:#64748b;font-size:0.85rem;margin-bottom:16px;">
                        Reset password for <strong id="resetUsername"></strong>?<br>
                        They will be given the default password.
                    </p>
                    <div class="mb-3 text-start">
                        <label class="form-label" style="font-size:0.8rem;font-weight:600;color:#64748b;">New Password</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Enter new password">
                    </div>
                </div>

                <div class="modal-footer px-4 py-3 justify-content-center gap-2" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-4" style="background:#f59e0b;color:#fff;border:none;border-radius:7px;font-weight:600;">
                        Reset Password
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ===================== TOGGLE STATUS ===================== -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" id="toggle-id">
                <input type="hidden" name="current_status" id="toggle-status">

                <div class="modal-body px-4 py-4 text-center">
                    <div id="toggleIcon" style="width:52px;height:52px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fa fa-ban" style="color:#ef4444;font-size:1.3rem;"></i>
                    </div>
                    <h5 style="font-weight:700;color:#1e293b;margin-bottom:6px;" id="toggleTitle">Disable User</h5>
                    <p style="color:#64748b;font-size:0.85rem;margin-bottom:0;">
                        Are you sure you want to <strong id="toggleAction"></strong> <strong id="toggleUsername"></strong>?
                    </p>
                </div>

                <div class="modal-footer px-4 py-3 justify-content-center gap-2" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="toggleActionBtn" class="btn btn-sm btn-danger px-4" style="border-radius:7px;font-weight:600;">
                        Disable User
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- ===================== DELETE USER ===================== -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete-id">

                <div class="modal-body px-4 py-4 text-center">
                    <div style="width:52px;height:52px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fa fa-trash" style="color:#ef4444;font-size:1.3rem;"></i>
                    </div>
                    <h5 style="font-weight:700;color:#1e293b;margin-bottom:6px;">Delete User</h5>
                    <p style="color:#64748b;font-size:0.85rem;margin-bottom:0;">
                        Permanently delete <strong id="deleteUsername"></strong>?<br>
                        <span style="color:#ef4444;font-size:0.78rem;">This action cannot be undone.</span>
                    </p>
                </div>

                <div class="modal-footer px-4 py-3 justify-content-center gap-2" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                    <button type="button" class="btn btn-sm btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger px-4" style="border-radius:7px;font-weight:600;">
                        <i class="fa fa-trash me-1"></i>Delete User
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>