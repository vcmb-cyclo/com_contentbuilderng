<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * Ajax toggle + sortable-columns script for the BF system fields modal (#cbBfSystemFieldModal).
 * Depends on globals declared earlier in edit_init_scripts.php: cbFormId, cbSaveFailedMessage.
 * Wrapped in DOMContentLoaded because this <script> is output before the modal HTML in the page.
 */

\defined('_JEXEC') or die;
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var modal = document.getElementById('cbBfSystemFieldModal');
    if (!modal) { return; }

    var changed    = false;
    var sortCol    = 'label';
    var sortDir    = 1; // 1 = asc, -1 = desc

    // ── Sort ─────────────────────────────────────────────────────────────────

    function updateSortIcons() {
        modal.querySelectorAll('th[data-sort-col]').forEach(function (th) {
            var icon = th.querySelector('.cb-bf-sort-icon');
            if (!icon) { return; }
            if (th.dataset.sortCol === sortCol) {
                icon.textContent = sortDir === 1 ? ' ↑' : ' ↓';
            } else {
                icon.textContent = '';
            }
        });
    }

    function reNumberRows(rows) {
        rows.forEach(function (row, i) {
            var cell = row.querySelector('.cb-bf-row-num');
            if (cell) { cell.textContent = String(i + 1); }
        });
    }

    function sortTable(col) {
        if (sortCol === col) {
            sortDir = -sortDir;
        } else {
            sortCol = col;
            sortDir = 1;
        }

        var tbody = modal.querySelector('#cbBfSystemFieldTable tbody');
        if (!tbody) { return; }

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

        rows.sort(function (a, b) {
            var aCell = a.querySelector('[data-sort-col="' + col + '"]');
            var bCell = b.querySelector('[data-sort-col="' + col + '"]');
            var aVal  = aCell ? (aCell.dataset.sort || '') : '';
            var bVal  = bCell ? (bCell.dataset.sort || '') : '';
            return sortDir * aVal.localeCompare(bVal, undefined, { sensitivity: 'base', numeric: true });
        });

        rows.forEach(function (row) { tbody.appendChild(row); });
        reNumberRows(rows);
        updateSortIcons();
    }

    // Attach click handlers to sortable headers
    modal.querySelectorAll('th[data-sort-col]').forEach(function (th) {
        th.addEventListener('click', function () { sortTable(th.dataset.sortCol); });
    });

    // Initial sort indicator (label asc is the PHP default)
    updateSortIcons();

    // ── Reload after close if changed ────────────────────────────────────────

    modal.addEventListener('hidden.bs.modal', function () {
        if (changed) { window.location.reload(); }
    });

    // ── CSRF token helper ─────────────────────────────────────────────────────

    function getCsrfTokenName() {
        var adminForm = document.getElementById('adminForm');
        if (!adminForm) { return null; }
        var inputs = adminForm.querySelectorAll('input[type="hidden"]');
        for (var i = 0; i < inputs.length; i++) {
            var inp = inputs[i];
            if (/^[a-f0-9]{32}$/.test(inp.name) && inp.value === '1') {
                return inp.name;
            }
        }
        return null;
    }

    // ── Ajax toggle ───────────────────────────────────────────────────────────

    modal.addEventListener('change', function (e) {
        var checkbox = e.target;
        if (!checkbox || !checkbox.classList.contains('cb-bf-system-field-toggle')) { return; }

        var refId    = parseInt(checkbox.dataset.referenceId, 10);
        var elemId   = parseInt(checkbox.dataset.elementId || '0', 10);
        var adding   = checkbox.checked;
        var errEl    = document.getElementById('cbBfSystemFieldError');
        var statusEl = document.getElementById('cbBfSystemFieldStatus');
        var row      = checkbox.closest('tr');

        checkbox.disabled = true;
        if (errEl) { errEl.classList.add('d-none'); }

        var fd        = new FormData();
        var tokenName = getCsrfTokenName();
        if (tokenName) { fd.set(tokenName, '1'); }
        fd.set('option', 'com_contentbuilderng');
        fd.set('id', String(cbFormId));
        fd.set('cb_ajax', '1');

        if (adding) {
            fd.set('task', 'form.ajax_add_bf_system_field');
            fd.set('reference_id', String(refId));
        } else {
            fd.set('task', 'form.ajax_remove_bf_system_field');
            fd.set('element_id', String(elemId));
        }

        fetch('index.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            if (!payload.success) { throw new Error(payload.message || ''); }
            changed = true;
            if (adding && payload.data && payload.data.element_id) {
                checkbox.dataset.elementId = String(payload.data.element_id);
            } else if (!adding) {
                checkbox.dataset.elementId = '0';
            }
            // Keep data-sort in sync so re-sort by "included" reflects the new state
            if (row) {
                var cell = row.querySelector('[data-sort-col="included"]');
                if (cell) { cell.dataset.sort = adding ? '1' : '0'; }
            }
            if (statusEl) { statusEl.textContent = payload.message || ''; }
        })
        .catch(function (err) {
            checkbox.checked = !adding;
            if (errEl) {
                errEl.textContent = err.message || cbSaveFailedMessage;
                errEl.classList.remove('d-none');
            }
        })
        .finally(function () { checkbox.disabled = false; });
    });
});
</script>
