<!-- VIEW ROUTER MODAL -->
<div class="modal fade" id="viewRouterModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="fa fa-server me-2"></i>
                    <span id="routerModalName">Router Details</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body bg-light">

                <div class="row g-4">

                    <!-- SYSTEM INFO -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-primary text-white">
                                <i class="fa fa-microchip me-2"></i>System Information
                            </div>
                            <div class="card-body">

                                <div class="mb-3">
                                    <strong>Model:</strong>
                                    <span id="routerModel" class="text-muted"></span>
                                </div>

                                <div class="mb-3">
                                    <strong>Firmware:</strong>
                                    <span id="routerFirmware" class="text-muted"></span>
                                </div>

                                <div class="mb-3">
                                    <strong>Uptime:</strong>
                                    <span id="routerUptime" class="text-muted"></span>
                                </div>

                                <div>
                                    <strong>CPU Load:</strong>
                                    <span id="routerCPU" class="badge bg-info"></span>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- MEMORY -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-success text-white">
                                <i class="fa fa-memory me-2"></i>Memory Usage
                            </div>
                            <div class="card-body">

                                <div class="progress mb-3" style="height: 25px;">
                                    <div id="ramProgress"
                                         class="progress-bar bg-success"
                                         style="width:0%">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <small>Total RAM:</small>
                                    <small id="ramTotal"></small>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <small>Free RAM:</small>
                                    <small id="ramFree"></small>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- LIVE TRAFFIC -->
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-info text-white">
                                <i class="fa fa-chart-line me-2"></i>Live Traffic
                            </div>
                            <div class="card-body">

                                <canvas id="routerTrafficChart" height="80"></canvas>

                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</div>



<!-- EDIT ROUTER MODAL -->
<div class="modal fade" id="editRouterModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="update.php">

                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-edit me-2"></i>Edit Router
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="router_id" id="editRouterId">

                    <div class="mb-3">
                        <label>Name</label>
                        <input type="text" name="name" id="editRouterName" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>Host</label>
                        <input type="text" name="host" id="editRouterHost" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>API Port</label>
                        <input type="number" name="api_port" id="editRouterPort" class="form-control">
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">
                        Save Changes
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>



<!-- DELETE ROUTER MODAL -->
<div class="modal fade" id="deleteRouterModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="delete.php">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        Delete Router
                    </h5>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="router_id" id="deleteRouterId">

                    Are you sure you want to delete:
                    <strong id="deleteRouterName"></strong> ?

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger">
                        Delete
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
