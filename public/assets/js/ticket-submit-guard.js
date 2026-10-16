document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-ticket-submit-guard]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            if (!form.checkValidity()) {
                return;
            }

            form.dataset.submitting = 'true';

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                button.disabled = true;

                if (button.tagName === 'BUTTON') {
                    button.dataset.originalHtml = button.innerHTML;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Mengirim...';
                } else {
                    button.dataset.originalValue = button.value;
                    button.value = 'Mengirim...';
                }
            });
        });
    });
});
