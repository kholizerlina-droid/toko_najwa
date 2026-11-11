document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modalPesananMenunggu');
    if (!modalEl || modalEl.dataset.autoShow !== 'true' || typeof bootstrap === 'undefined') return;

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
