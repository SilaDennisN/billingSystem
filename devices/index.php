<?php
require_once "../core/db.php";
require_once "../core/auth.php";

if (!is_logged_in() || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../dashboard");
    exit;
}

// Fetch towers with router info
$towers = $pdo->query("
    SELECT t.*, r.name AS router_name
    FROM towers t
    JOIN routers r ON t.router_id = r.router_id
    ORDER BY t.created_at DESC
")->fetchAll();

// Fetch routers for modal dropdown
$routers = $pdo->query("SELECT * FROM routers WHERE status='active' ORDER BY name")->fetchAll();
?>

<?php require_once "../partials/head.php"; ?>

<body class="nav-fixed bg-light">
<?php require_once "../partials/topnav.php"; ?>
<div id="layoutDrawer">
    <?php require_once "../partials/sidebar.php"; ?>
    <div id="layoutDrawer_content">
        <main>
            <header class="bg-primary">
                <div class="container-xl px-1">
                    <div class="d-flex align-items-center justify-content-between py-4">
                        <div>
                            <h1 class="text-white mb-1 display-6">Towers Management</h1>
                            <p class="text-white-50 mb-0">Manage towers and their VLAN configuration</p>
                        </div>
                        <div>
                            <i class="material-icons text-white-50" style="font-size: 3rem;">apartment</i>
                        </div>
                    </div>
                </div>
            </header>

            <div class="container-xl px-1 mt-n4">
                <!-- Towers Table Card -->
                <div class="card shadow border-0">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                        <div>
                            <h5 class="card-title mb-0">All Towers</h5>
                            <small class="text-muted">View and manage all towers</small>
                        </div>
                        <button class="btn btn-primary btn-sm lift" data-bs-toggle="modal" data-bs-target="#addTowerModal">
                            <i class="material-icons me-1" style="font-size: 1rem; vertical-align: middle;">add</i>
                            Add Tower
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Tower Name</th>
                                        <th>Router</th>
                                        <th>Hotspot VLAN</th>
                                        <th>PPPoE VLAN</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($towers as $t): ?>
                                    <tr>
                                        <td><?= $t['tower_id'] ?></td>
                                        <td><?= htmlspecialchars($t['name']) ?></td>
                                        <td><?= htmlspecialchars($t['router_name']) ?></td>
                                        <td><?= $t['hotspot_vlan'] ?></td>
                                        <td><?= $t['pppoe_vlan'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $t['status'] == 'active' ? 'success' : 'danger' ?>-soft text-<?= $t['status'] == 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($t['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="edit.php?tower_id=<?= $t['tower_id'] ?>" class="btn btn-sm btn-text">
                                                <i class="material-icons">edit</i>
                                            </a>
                                            <a href="toggle.php?tower_id=<?= $t['tower_id'] ?>" class="btn btn-sm btn-text">
                                                <i class="material-icons"><?= $t['status'] == 'active' ? 'block' : 'check_circle' ?></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-muted py-3">
                        Showing <?= count($towers) ?> tower<?= count($towers) != 1 ? 's' : '' ?> • Last updated: <?= date('d M Y H:i') ?>
                    </div>
                </div>
            </div>
        </main>

        <!-- Add Tower Modal -->
        <div class="modal fade" id="addTowerModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form action="store.php" method="POST" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Tower</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Tower Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Router</label>
                            <select name="router_id" class="form-control" required>
                                <?php foreach($routers as $r): ?>
                                    <option value="<?= $r['router_id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Hotspot VLAN</label>
                            <input type="number" name="hotspot_vlan" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>PPPoE VLAN</label>
                            <input type="number" name="pppoe_vlan" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Tower</button>
                    </div>
                </form>
            </div>
        </div>

        <?php require_once "../partials/footer.php"; ?>
    </div>
</div>

<?php require_once "../partials/scripts.php"; ?>
</body>
</html>
