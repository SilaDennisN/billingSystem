<!-- ADD PROFILE MODAL -->
<div class="modal fade" id="addProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white">
                        <i class="fa fa-plus-circle me-2"></i>Add New Package
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-server me-1"></i>Router
                            </label>
                            <select name="router_id" class="form-select" required>
                                <option value="">Select Router</option>
                                <?php foreach ($routers as $r): ?>
                                    <option value="<?= $r['router_id'] ?>" <?= $r['router_id'] == $selected_router ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-layer-group me-1"></i>Profile Type
                            </label>
                            <select name="profile_type" class="form-select" required>
                                <option value="hotspot">
                                    <i class="fa fa-wifi"></i> Hotspot
                                </option>
                                <option value="pppoe">
                                    <i class="fa fa-network-wired"></i> PPPoE
                                </option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">
                                <i class="fa fa-tag me-1"></i>Profile Name
                            </label>
                            <input name="profile_name" class="form-control" placeholder="e.g., Premium_5Mbps" required>
                            <small class="text-muted">Use descriptive names without spaces</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-tachometer-alt me-1"></i>Rate Limit
                            </label>
                            <input name="rate_limit" class="form-control" placeholder="e.g., 5M/5M">
                            <small class="text-muted">Format: Download/Upload (e.g., 10M/10M)</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-users me-1"></i>Shared Users
                            </label>
                            <input name="shared_users" type="number" class="form-control" placeholder="1" value="1">
                            <small class="text-muted">Number of concurrent logins allowed</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-money-bill-wave me-1"></i>Price (KES)
                            </label>
                            <input name="price" type="number" step="0.01" class="form-control" placeholder="500.00">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-clock me-1"></i>Validity (Days)
                            </label>
                            <input name="validity_days" type="number" class="form-control" placeholder="30">
                            <small class="text-muted">Package duration in days</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-clock me-1"></i>Validity (Hours)
                            </label>
                            <input name="validity_hours" type="number" class="form-control" placeholder="0">
                            <small class="text-muted">Additional hours (optional)</small>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>
                                <strong>Note:</strong> The profile will be created on the router and saved to the database for billing.
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-check me-1"></i>Create Package
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>



<!-- VIEW PROFILE MODAL -->
<div class="modal fade" id="viewProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white">
                    <i class="fa fa-eye me-2"></i>Profile Details
                </h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <div class="card card-raised">
                            <div class="card-body">
                                <div class="small text-muted mb-1">
                                    <i class="fa fa-tag me-1"></i>Profile Name
                                </div>
                                <h5 id="view-name" class="mb-0"></h5>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-raised">
                            <div class="card-body">
                                <div class="small text-muted mb-1">
                                    <i class="fa fa-layer-group me-1"></i>Type
                                </div>
                                <h5 id="view-type" class="mb-0"></h5>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-raised">
                            <div class="card-body">
                                <div class="small text-muted mb-1">
                                    <i class="fa fa-tachometer-alt me-1"></i>Rate Limit
                                </div>
                                <h5 id="view-rate" class="mb-0 text-primary"></h5>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-raised">
                            <div class="card-body">
                                <div class="small text-muted mb-1">
                                    <i class="fa fa-users me-1"></i>Shared Users
                                </div>
                                <h5 id="view-shared" class="mb-0"></h5>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-raised">
                            <div class="card-body">
                                <div class="small text-muted mb-1">
                                    <i class="fa fa-money-bill-wave me-1"></i>Price
                                </div>
                                <h5 id="view-price" class="mb-0 text-success"></h5>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-raised">
                            <div class="card-body">
                                <div class="small text-muted mb-1">
                                    <i class="fa fa-clock me-1"></i>Validity
                                </div>
                                <h5 id="view-validity" class="mb-0 text-warning"></h5>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i>Close
                </button>
            </div>

        </div>
    </div>
</div>


<!-- EDIT PROFILE MODAL -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="router_id" id="edit-router">
                <input type="hidden" name="profile_name" id="edit-name">
                <input type="hidden" name="profile_type" id="edit-type">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title text-white">
                        <i class="fa fa-edit me-2"></i>Edit Profile
                    </h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-tachometer-alt me-1"></i>Rate Limit
                            </label>
                            <input name="rate_limit" id="edit-rate" class="form-control" placeholder="e.g., 10M/10M">
                            <small class="text-muted">Format: Download/Upload</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-users me-1"></i>Shared Users
                            </label>
                            <input name="shared_users" id="edit-shared" type="number" class="form-control">
                            <small class="text-muted">Concurrent login limit</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-money-bill-wave me-1"></i>Price (KES)
                            </label>
                            <input name="price" id="edit-price" type="number" step="0.01" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-clock me-1"></i>Validity Hrs
                            </label>
                            <input name="validity_hours" id="edit-validity-hours" class="form-control" placeholder="e.g., 30d 0h">
                            <small class="text-muted">Format: hours</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fa fa-clock me-1"></i>Validity Days
                            </label>
                            <input name="validity_days" id="edit-validity-days" class="form-control" placeholder="e.g., 30d">
                            <small class="text-muted">Format: days</small>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Changes will be applied to the router configuration and database.
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save me-1"></i>Save Changes
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>


<!-- DELETE PROFILE MODAL -->
<div class="modal fade" id="deleteProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="store.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="router_id" id="delete-router">
                <input type="hidden" name="profile_name" id="delete-name">
                <input type="hidden" name="profile_type" id="delete-type">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title text-white">
                        <i class="fa fa-exclamation-triangle me-2"></i>Delete Profile
                    </h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fa fa-trash-alt fa-3x text-danger mb-3"></i>
                        <h5>Are you sure?</h5>
                        <p class="text-muted">
                            You are about to delete the profile:<br>
                            <strong class="text-danger" id="delete-profile-name"></strong>
                        </p>
                    </div>

                    <div class="alert alert-danger mb-0">
                        <i class="fa fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> This will permanently remove the profile from the router. 
                        Users with this profile may lose connectivity.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fa fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash me-1"></i>Delete Profile
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>