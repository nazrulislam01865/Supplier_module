document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('supplierPoForm');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
        if (!form.checkValidity()) return;
        form.dataset.submitting = 'true';
        var button = form.querySelector('button[type=submit]');
        if (button) { button.disabled = true; button.textContent = 'Saving in FlowTracker…'; }
    });
});
