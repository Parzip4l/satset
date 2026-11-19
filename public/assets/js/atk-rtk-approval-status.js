(() => {
    const status = document.querySelector('[data-manager-approval-status]');
    if (!status) return;

    let editing = false;
    document.addEventListener('input', () => { editing = true; });

    async function refreshStatus() {
        if (document.visibilityState === 'hidden') {
            window.setTimeout(refreshStatus, 5000);
            return;
        }

        try {
            const response = await fetch(status.dataset.managerApprovalStatus, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: AbortSignal.timeout(10000),
            });
            if ([401, 403, 404].includes(response.status)) return;
            if (response.ok) {
                const latest = await response.json();
                if (latest.setup !== status.dataset.managerSetup
                    || latest.workflow_status !== status.dataset.managerWorkflow) {
                    if (!editing) {
                        window.location.reload();
                    } else {
                        const link = document.createElement('a');
                        link.href = window.location.href;
                        link.className = 'd-block mt-2 fw-semibold';
                        link.textContent = 'Status persetujuan berubah. Muat ulang setelah menyelesaikan isian Anda.';
                        status.appendChild(link);
                    }
                    return;
                }
            }
        } catch {
            // A temporary connection failure is retried on the next check.
        }
        window.setTimeout(refreshStatus, 5000);
    }

    window.setTimeout(refreshStatus, 5000);
})();
