(() => {
    'use strict';
    if (window.cbstatsManualExportReady) return;
    window.cbstatsManualExportReady = true;

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.cbstats-manual-copy');
        if (!button) return;
        const container = button.closest('.cbstats-manual-export');
        const syntax = container?.querySelector('.cbstats-manual-export-syntax');
        const status = container?.querySelector('.cbstats-manual-copy-status');
        if (!syntax || !status) return;

        window.clearTimeout(Number(status.dataset.timeout || 0));
        try {
            await navigator.clipboard.writeText(syntax.value);
            status.textContent = status.dataset.success || '';
        } catch (error) {
            syntax.focus();
            syntax.select();
            status.textContent = status.dataset.failure || '';
        }
        status.dataset.timeout = String(window.setTimeout(() => { status.textContent = ''; }, 2500));
    });
})();
