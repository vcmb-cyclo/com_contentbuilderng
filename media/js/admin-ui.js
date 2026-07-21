window.ContentBuilderNgAdmin = window.ContentBuilderNgAdmin || (function () {
    function getTabTargetId(el) {
        if (!el || typeof el.getAttribute !== 'function') {
            return null;
        }

        return (
            el.getAttribute('aria-controls') ||
            el.getAttribute('data-tab') ||
            (el.getAttribute('data-bs-target') && el.getAttribute('data-bs-target').startsWith('#') ? el.getAttribute('data-bs-target').slice(1) : null) ||
            (el.getAttribute('href') && el.getAttribute('href').startsWith('#') ? el.getAttribute('href').slice(1) : null) ||
            (el.getAttribute('data-target') && el.getAttribute('data-target').startsWith('#') ? el.getAttribute('data-target').slice(1) : null)
        );
    }

    function initBootstrapTooltips(root) {
        var scope = root || document;

        if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
            return;
        }

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (!window.bootstrap.Tooltip.getInstance(el)) {
                new window.bootstrap.Tooltip(el);
            }
        });
    }

    function applyTabTooltips(tabsetId, tips, attempt) {
        var tabset = document.getElementById(tabsetId);
        var tries = typeof attempt === 'number' ? attempt : 0;

        if (!tabset || !tips) {
            return;
        }

        var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
        if (!jTab) {
            return;
        }

        var selector = 'button[aria-controls],button[data-tab],button[data-target],button[data-bs-target],a[aria-controls],a[data-tab],a[data-target],a[data-bs-target],a[href^="#"]';
        var roots = [jTab];
        if (jTab.shadowRoot) {
            roots.push(jTab.shadowRoot);
        }

        var applied = 0;

        roots.forEach(function (root) {
            root.querySelectorAll(selector).forEach(function (trigger) {
                var id = getTabTargetId(trigger);
                var tip = id ? tips[id] : null;

                if (!tip) {
                    return;
                }

                trigger.setAttribute('title', String(tip));
                trigger.setAttribute('data-bs-toggle', 'tooltip');
                trigger.setAttribute('data-bs-placement', 'top');
                trigger.setAttribute('data-bs-title', String(tip));
                applied++;
            });

            initBootstrapTooltips(root);
        });

        if (applied === 0 && tries < 12) {
            window.setTimeout(function () {
                applyTabTooltips(tabsetId, tips, tries + 1);
            }, 120);
        }
    }

    function setHiddenInputValue(name, value) {
        var el = document.querySelector('input[name="' + name + '"], input[name="jform[' + name + ']"]');
        if (el) {
            el.value = value;
        }
    }

    function persistJoomlaTabset(tabsetId, storageKey, onSave, options) {
        var tabset = document.getElementById(tabsetId);
        var config = options || {};
        var restoreFromStorage = config.restoreFromStorage !== false;
        if (!tabset) {
            return;
        }

        var jTab = tabset.matches('joomla-tab') ? tabset : tabset.querySelector('joomla-tab');
        if (!jTab) {
            return;
        }

        var saved = restoreFromStorage ? localStorage.getItem(storageKey) : null;
        if (saved) {
            if (typeof jTab.show === 'function') {
                try {
                    jTab.show(saved);
                } catch (e) {
                }
            }

            var btn =
                jTab.querySelector('button[aria-controls="' + saved + '"]') ||
                jTab.querySelector('button[data-tab="' + saved + '"]') ||
                jTab.querySelector('button[data-bs-target="#' + saved + '"]') ||
                jTab.querySelector('button[data-target="#' + saved + '"]') ||
                jTab.querySelector('a[aria-controls="' + saved + '"]') ||
                jTab.querySelector('a[href="#' + saved + '"]') ||
                (jTab.shadowRoot && (
                    jTab.shadowRoot.querySelector('button[aria-controls="' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('button[data-tab="' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('button[data-bs-target="#' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('button[data-target="#' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('a[aria-controls="' + saved + '"]') ||
                    jTab.shadowRoot.querySelector('a[href="#' + saved + '"]')
                ));

            if (btn) {
                btn.click();
                if (typeof btn.blur === 'function') {
                    btn.blur();
                }
            }
        }

        var saveActiveTab = function (ev) {
            var trigger = (ev.target && typeof ev.target.closest === 'function') ? (ev.target.closest('button,a') || ev.target) : ev.target;
            var id = getTabTargetId(trigger);

            if (!id) {
                return;
            }

            localStorage.setItem(storageKey, id);
            if (typeof onSave === 'function') {
                onSave(id);
            }
        };

        jTab.addEventListener('click', saveActiveTab, { passive: true });

        if (jTab.shadowRoot) {
            jTab.shadowRoot.addEventListener('click', saveActiveTab, { passive: true });
        }
    }

    function extractSubmitButtonTask(onclickAttr) {
        var m = onclickAttr.match(/Joomla\.submitbutton\(\s*'([^']+)'\s*\)/);

        return m ? m[1] : null;
    }

    function getCheckedRows(form) {
        return Array.prototype.slice.call(form.querySelectorAll('input[name="cid[]"]:checked'));
    }

    function labelForCheckbox(checkbox) {
        var row = checkbox.closest('tr[data-cb-item-label]');

        return row ? row.getAttribute('data-cb-item-label') : '';
    }

    function buildDeleteConfirmMessage(checkedRows) {
        if (checkedRows.length === 1) {
            var label = labelForCheckbox(checkedRows[0]);

            if (label && window.Joomla && typeof Joomla.Text._ === 'function') {
                return Joomla.Text._('COM_CONTENTBUILDERNG_CONFIRM_DELETE_ONE').replace('%s', label);
            }
        }

        if (window.Joomla && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._('COM_CONTENTBUILDERNG_CONFIRM_DELETE_MANY').replace('%d', String(checkedRows.length));
        }

        return null;
    }

    // Rend les boîtes de confirmation "Supprimer" (toolbar admin, boutons
    // générés via Toolbar\Button\ConfirmButton::message()) plus précises :
    // nom de l'élément si un seul est sélectionné, nombre sinon. Intercepte
    // tout bouton confirm()+Joomla.submitbutton() du composant, sans wiring
    // par écran.
    document.addEventListener('click', function (event) {
        var el = typeof event.target.closest === 'function'
            ? event.target.closest('[onclick*="Joomla.submitbutton("]')
            : null;

        if (!el) {
            return;
        }

        var onclickAttr = el.getAttribute('onclick') || '';

        if (onclickAttr.indexOf('confirm(') === -1) {
            return;
        }

        var task = extractSubmitButtonTask(onclickAttr);

        if (!task) {
            return;
        }

        var form = document.adminForm || document.getElementById('adminForm');

        if (!form) {
            return;
        }

        var checkedRows = getCheckedRows(form);

        if (!checkedRows.length) {
            return; // Laisser le handler natif afficher "Sélectionnez un élément".
        }

        var message = buildDeleteConfirmMessage(checkedRows);

        if (!message) {
            return; // Chaînes non chargées sur cette page : conserver le comportement natif.
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (window.confirm(message)) {
            Joomla.submitbutton(task);
        }
    }, true);

    return {
        applyTabTooltips: applyTabTooltips,
        getTabTargetId: getTabTargetId,
        initBootstrapTooltips: initBootstrapTooltips,
        persistJoomlaTabset: persistJoomlaTabset,
        setHiddenInputValue: setHiddenInputValue
    };
}());
