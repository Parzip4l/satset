(() => {
    const form = document.getElementById('atkRtkForm');
    if (!form || !window.fetch) return;

    const button = form.querySelector('button[type="submit"]');
    const fields = form.querySelector('fieldset');
    const error = form.querySelector('[data-atk-submit-error]');
    const success = document.getElementById('atkSubmitSuccess');
    const detailLink = success.querySelector('[data-atk-ticket-link]');
    const originalButton = button.innerHTML;
    let submitting = false;

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submitting || !form.reportValidity()) return;

        const body = new FormData(form);
        submitting = true;
        fields.disabled = true;
        form.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Menyimpan permintaan…';
        error.hidden = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.ticket_no) {
                throw new Error(response.status < 500
                    ? Object.values(result.errors || {}).flat()[0] || result.message || 'Permintaan belum dapat disimpan. Silakan coba lagi.'
                    : 'Permintaan belum dapat dikonfirmasi. Silakan coba lagi.');
            }

            success.querySelector('[data-atk-submit-message]').textContent = result.message;
            success.querySelector('[data-atk-ticket-number]').textContent = result.ticket_no;
            if (result.detail_url) {
                detailLink.href = result.detail_url;
                detailLink.hidden = false;
            }
            form.hidden = true;
            success.hidden = false;
            success.focus();
        } catch (failure) {
            error.textContent = failure instanceof TypeError
                ? 'Koneksi terputus sebelum Satset mengonfirmasi permintaan. Silakan coba lagi.'
                : failure.message;
            error.hidden = false;
            error.focus();
        } finally {
            submitting = false;
            fields.disabled = false;
            button.innerHTML = originalButton;
            form.removeAttribute('aria-busy');
        }
    });
})();
