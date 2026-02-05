<div class="modal fade" id="payInvoiceModal">
<div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content">

<form method="POST" action="pay.php">

<div class="modal-header">
    <h5 class="modal-title">Pay Invoice</h5>
</div>

<div class="modal-body">
    <input type="hidden" name="invoice_id" id="pay-invoice-id">

    <label>Amount</label>
    <input name="amount" class="form-control" required>

    <label class="mt-2">Payment Method</label>
    <select name="payment_method" class="form-select">
        <option value="cash">Cash</option>
        <option value="online">Online</option>
    </select>
</div>

<div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">
        Cancel
    </button>
    <button class="btn btn-success">
        Pay
    </button>
</div>

</form>

</div>
</div>
</div>
