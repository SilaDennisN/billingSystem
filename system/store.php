<div class="modal fade" id="addUserModal">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">

<form method="POST" action="store.php">

<div class="modal-header">
    <h5 class="modal-title">Add System User</h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

    <label>Username</label>
    <input name="username" class="form-control" required>

    <label class="mt-3">Email</label>
    <input name="email" type="email" class="form-control">

    <label class="mt-3">Role</label>
    <select name="role" class="form-select">
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
    </select>

    <label class="mt-3">Password</label>
    <input name="password" type="password" class="form-control" required>

</div>

<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-primary">Create User</button>
</div>

</form>

</div>
</div>
</div>
