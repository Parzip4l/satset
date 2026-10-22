(function () {
    'use strict';

    const formatMegabytes = (bytes) => {
        const megabytes = bytes / (1024 * 1024);
        return Number.isInteger(megabytes) ? megabytes.toString() : megabytes.toFixed(1);
    };

    const showFileSizeAlert = (input, file, maximumBytes) => {
        const maximumLabel = input.dataset.maxFileSizeLabel || `${formatMegabytes(maximumBytes)} MB`;
        const message = `File "${file.name}" berukuran ${formatMegabytes(file.size)} MB. Ukuran maksimal yang diperbolehkan adalah ${maximumLabel}. Silakan pilih file yang lebih kecil.`;

        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: 'warning',
                title: 'File terlalu besar',
                text: message,
                confirmButtonText: 'Pilih File Lain',
                confirmButtonColor: '#dc2626',
            });
            return;
        }

        window.alert(`File terlalu besar\n\n${message}`);
    };

    const validateFileInput = (input, showAlert = true) => {
        const maximumMegabytes = Number.parseFloat(input.dataset.maxFileSizeMb || '');
        if (!Number.isFinite(maximumMegabytes) || maximumMegabytes <= 0 || !input.files?.length) {
            return true;
        }

        const maximumBytes = maximumMegabytes * 1024 * 1024;
        const oversizedFile = Array.from(input.files).find((file) => file.size > maximumBytes);
        if (!oversizedFile) {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            return true;
        }

        input.value = '';
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (showAlert) showFileSizeAlert(input, oversizedFile, maximumBytes);
        input.focus({ preventScroll: true });
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('input[type="file"][data-max-file-size-mb]').forEach((input) => {
            input.addEventListener('change', () => validateFileInput(input));
        });

        document.querySelectorAll('form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const fileInputs = form.querySelectorAll('input[type="file"][data-max-file-size-mb]');
                for (const input of fileInputs) {
                    if (!validateFileInput(input)) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        return;
                    }
                }
            });
        });
    });
})();
