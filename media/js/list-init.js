/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * Relies on window.cbListConfig, populated inline by
 * site/tmpl/list/default.php before this file loads.
 */

	// The list view has no unsaved-edit workflow; prevent external dirty handlers
	// from blocking pagination or other list navigation.
	window.onbeforeunload = null;
	window.addEventListener('beforeunload', function(event) {
		event.stopImmediatePropagation();
	}, true);

	Joomla.tableOrdering = function(order, dir, task) {
		var form = document.getElementById('adminForm');
		if (!form) return;

		// Joomla 6 native list state
		if (form.elements['list[start]']) {
			form.elements['list[start]'].value = 0;
		}
		if (form.elements['list[ordering]']) {
			form.elements['list[ordering]'].value = order;
		}
		if (form.elements['list[direction]']) {
			form.elements['list[direction]'].value = dir;
		}
		if (form.elements['list[fullordering]']) {
			form.elements['list[fullordering]'].value = order + ' ' + dir;
		}

		Joomla.submitform(task || '', form);
	};

	window.cbRate = function(url, lastId) {
		var tokenParam = cbListConfig.ratingCsrfTokenParam;
		var separator = url.indexOf('?') === -1 ? '?' : '&';
		var requestUrl = url + separator + tokenParam;

		fetch(requestUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: tokenParam
		})
			.then(function(response) {
				return response.text().then(function(text) {
					var payload = null;

					try {
						payload = JSON.parse(text);
					} catch (error) {
						payload = {success: false, message: text || 'Rating error'};
					}

					var result = payload && typeof payload.success !== 'undefined'
						? (payload.success ? (payload.data || {}) : {code: 1, msg: payload.message || ''})
						: payload;
					var messageBox = document.getElementById(lastId);

					if (messageBox) {
						messageBox.style.display = 'block';
						messageBox.textContent = (result && result.msg) ? result.msg : '';
						window.setTimeout(function() {
							messageBox.style.display = 'none';
						}, 1800);
					}

					if (result && result.code === 0) {
						var counter = document.getElementById(lastId + 'Counter');
						if (counter && !isNaN(Number(counter.textContent))) {
							counter.textContent = String(Number(counter.textContent) + 1);
						}
					}

					if (!response.ok) {
						throw new Error((result && result.msg) ? result.msg : 'Rating error');
					}
				});
			})
			.catch(function(error) {
				var messageBox = document.getElementById(lastId);
				if (messageBox) {
					messageBox.style.display = 'block';
					messageBox.textContent = error && error.message ? error.message : 'Rating error';
					window.setTimeout(function() {
						messageBox.style.display = 'none';
					}, 1800);
				}
			});

		return false;
	};

	function contentbuilderng_selectedCount(form) {
		if (!form) return 0;
		var boxchecked = form.querySelector('input[name="boxchecked"]');
		if (boxchecked) {
			var value = parseInt(boxchecked.value, 10);
			return isNaN(value) ? 0 : value;
		}
		return form.querySelectorAll('input[name="cid[]"]:checked').length;
	}

	function contentbuilderng_updateBulkActionsAvailability(form) {
		if (!form) return;
		var hasSelection = contentbuilderng_selectedCount(form) > 0;

		var bulkStateSelect = form.querySelector('select[name="list_state"]');
		if (bulkStateSelect) {
			bulkStateSelect.disabled = !hasSelection;

			if (!hasSelection && bulkStateSelect.value !== '-1') {
				bulkStateSelect.value = '-1';
			}
		}

		var bulkPublishSelect = form.querySelector('select[name="list_publish"]');
		if (bulkPublishSelect) {
			bulkPublishSelect.disabled = !hasSelection;

			if (!hasSelection && bulkPublishSelect.value !== '-1') {
				bulkPublishSelect.value = '-1';
			}
		}
	}

	function contentbuilderng_getResetFilterValues(form) {
		if (!form) return [];

		var values = [];
		var filterInput = form.querySelector('#contentbuilderng_filter');
		if (filterInput) {
			values.push(String(filterInput.value || '').trim());
		}

		['list_state_filter', 'list_publish_filter', 'list_language_filter'].forEach(function(name) {
			var select = form.querySelector('[name="' + name + '"]');
			if (select) {
				values.push(String(select.value || '').trim());
			}
		});

		return values;
	}

	function contentbuilderng_updateResetButtonState(form) {
		if (!form) return;

		var resetButton = form.querySelector('#cbResetButton');
		if (!resetButton) return;

		var hasActiveFilter = contentbuilderng_getResetFilterValues(form).some(function(value) {
			return value !== '' && value !== '0' && value !== '-1' && value !== '*';
		});

		resetButton.classList.remove('btn-outline-secondary', 'btn-warning');
		resetButton.classList.add(hasActiveFilter ? 'btn-warning' : 'btn-outline-secondary');
	}

	function contentbuilderng_updateBoxchecked(form) {
		if (!form) return;
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		var checked = 0;
		boxes.forEach(function(box) {
			if (box.checked) checked++;
		});
		var boxchecked = form.querySelector('input[name="boxchecked"]');
		if (boxchecked) {
			boxchecked.value = String(checked);
		}
		contentbuilderng_updateBulkActionsAvailability(form);
	}

	function contentbuilderng_selectAll(toggle) {
		var form = document.getElementById('adminForm');
		if (!form) return;
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		boxes.forEach(function(box) {
			box.checked = !!toggle.checked;
		});
		contentbuilderng_updateBoxchecked(form);
	}

	function contentbuilderng_delete() {
		if (confirm(cbListConfig.text.confirmDelete)) {
			var form = document.getElementById('adminForm');
			document.getElementById('task').value = 'list.delete';
			Joomla.submitform('list.delete', form);
		}
	}

	function contentbuilderng_state() {
		var form = document.getElementById('adminForm');
		if (!form) return;
		if (contentbuilderng_selectedCount(form) < 1) {
			var stateSelect = form.querySelector('select[name="list_state"]');
			if (stateSelect) {
				stateSelect.value = '-1';
			}
			contentbuilderng_updateBulkActionsAvailability(form);
			return;
		}
		document.getElementById('task').value = 'list.state';
		Joomla.submitform('list.state', form);
	}

	function contentbuilderngGetStateBadgeStyle(color) {
		var hex = String(color || '').replace(/^#/, '').toUpperCase();
		if (/^[0-9A-F]{3}$/.test(hex)) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}

		if (!/^[0-9A-F]{6}$/.test(hex)) {
			return '';
		}

		var red = parseInt(hex.substring(0, 2), 16);
		var green = parseInt(hex.substring(2, 4), 16);
		var blue = parseInt(hex.substring(4, 6), 16);
		var brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;
		var textColor = brightness > 160 ? '#111827' : '#F9FAFB';

		return 'background-color:#' + hex + ';color:' + textColor + ';';
	}

	function contentbuilderngGetStateSelectColors(color) {
		var hex = String(color || '').replace(/^#/, '').toUpperCase();
		if (/^[0-9A-F]{3}$/.test(hex)) {
			hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
		}

		if (!/^[0-9A-F]{6}$/.test(hex)) {
			return null;
		}

		var red = parseInt(hex.substring(0, 2), 16);
		var green = parseInt(hex.substring(2, 4), 16);
		var blue = parseInt(hex.substring(4, 6), 16);
		var brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

		return {
			background: '#' + hex,
			foreground: brightness > 160 ? '#111827' : '#F9FAFB'
		};
	}

	function contentbuilderngApplyStateSelectStyle(select, color) {
		var colors = contentbuilderngGetStateSelectColors(color);
		if (!select || !colors) {
			return;
		}

		var arrowColor = colors.foreground.replace(/#/g, '%23');
		var arrow = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3E%3Cpath fill=\'none\' stroke=\'' + arrowColor + '\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.8\' d=\'m3.5 6 4.5 4.5L12.5 6\'/%3E%3C/svg%3E")';

		select.style.setProperty('background-color', colors.background, 'important');
		select.style.setProperty('color', colors.foreground, 'important');
		select.style.setProperty('border-color', colors.background, 'important');
		select.style.setProperty('opacity', '1', 'important');
		select.style.setProperty('background-image', arrow, 'important');
		select.style.setProperty('background-repeat', 'no-repeat', 'important');
		select.style.setProperty('background-position', 'right .75rem center', 'important');
		select.style.setProperty('background-size', '16px 12px', 'important');
		select.style.setProperty('padding-right', '2.25rem', 'important');
	}

	function contentbuilderngClearStateSelectStyle(select) {
		if (!select) {
			return;
		}

		select.style.removeProperty('background-color');
		select.style.removeProperty('color');
		select.style.removeProperty('border-color');
		select.style.removeProperty('opacity');
		select.style.removeProperty('background-image');
		select.style.removeProperty('background-repeat');
		select.style.removeProperty('background-position');
		select.style.removeProperty('background-size');
		select.style.removeProperty('padding-right');
	}

	function contentbuilderngApplyInitialStateSelectStyles(root) {
		var scope = root || document;
		scope.querySelectorAll('[data-cb-state-select]').forEach(function(select) {
			var selectedOption = select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
			var stateColor = selectedOption ? String(selectedOption.getAttribute('data-state-color') || '') : '';

			if (String(select.value || '') !== '' && stateColor !== '') {
				contentbuilderngApplyStateSelectStyle(select, stateColor);
			} else {
				contentbuilderngClearStateSelectStyle(select);
			}
		});
	}

	function contentbuilderngEnsureStateOption(select, stateId, title, color, sourceOption) {
		if (!select || stateId === '0' || stateId === '') {
			return;
		}

		var option = Array.prototype.find.call(select.options, function(candidate) {
			return String(candidate.value) === String(stateId);
		});

		if (option) {
			return;
		}

		option = document.createElement('option');
		option.value = stateId;
		option.textContent = title || stateId;
		option.setAttribute('data-state-title', title || '');
		option.setAttribute('data-state-color', color || '');

		if (sourceOption) {
			Array.prototype.forEach.call(sourceOption.attributes, function(attribute) {
				if (attribute.name !== 'selected') {
					option.setAttribute(attribute.name, attribute.value);
				}
			});
			option.textContent = sourceOption.textContent;
		}

		select.appendChild(option);
	}

	function contentbuilderngUpdateStateUi(recordId, stateId, title, color, sourceOption) {
		var selector = '[data-record-id="' + contentbuilderngEscapeSelector(recordId) + '"]';
		var stateCells = document.querySelectorAll('[data-cb-state-cell]' + selector);
		var stateBadges = document.querySelectorAll('[data-cb-state-badge]' + selector);
		var stateSelects = document.querySelectorAll('[data-cb-state-select]' + selector);
		var normalizedColor = String(color || '').replace(/^#/, '');
		var badgeStyle = contentbuilderngGetStateBadgeStyle(normalizedColor);

		stateSelects.forEach(function(select) {
			contentbuilderngEnsureStateOption(select, stateId, title, normalizedColor, sourceOption);
			select.value = stateId === '0' ? '' : stateId;
			if (badgeStyle !== '') {
				contentbuilderngApplyStateSelectStyle(select, normalizedColor);
			} else {
				contentbuilderngClearStateSelectStyle(select);
			}
		});

		stateCells.forEach(function(stateCell) {
			stateCell.style.backgroundColor = normalizedColor !== '' ? ('#' + normalizedColor) : '#FFFFFF';
		});

		stateBadges.forEach(function(stateBadge) {
			if (title === '') {
				stateBadge.hidden = true;
				stateBadge.textContent = '';
				stateBadge.removeAttribute('style');
			} else {
				stateBadge.hidden = false;
				stateBadge.textContent = title;
				if (badgeStyle !== '') {
					stateBadge.setAttribute('style', badgeStyle);
				} else {
					stateBadge.removeAttribute('style');
				}
			}
		});
	}

	function contentbuilderng_state_single(selectOrStateId, stateIdOrRecordId, recordId) {
		var form = document.getElementById('adminForm');
		if (!form) return;
		var select = selectOrStateId && typeof selectOrStateId.tagName === 'string' ? selectOrStateId : null;
		var targetRecordId = select ? recordId : stateIdOrRecordId;
		var rawStateId = select ? stateIdOrRecordId : selectOrStateId;
		if (rawStateId === undefined || rawStateId === null) return;
		var normalizedStateId = String(rawStateId) === '' ? '0' : String(rawStateId);
		var selectedOption = select && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
		var stateTitle = selectedOption ? String(selectedOption.getAttribute('data-state-title') || '') : '';
		var stateColor = selectedOption ? String(selectedOption.getAttribute('data-state-color') || '') : '';
		var originalValue = select ? String(select.getAttribute('data-original-value') || '') : '';

		// Ensure only the clicked record is selected.
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		boxes.forEach(function (box) {
			box.checked = String(box.value) === String(targetRecordId);
		});
		contentbuilderng_updateBoxchecked(form);

		var formData = new FormData(form);
		formData.set('task', 'edit.state');
		formData.set('cb_ajax', '1');
		formData.set('list_state', normalizedStateId);
		formData.delete('cid[]');
		formData.append('cid[]', String(targetRecordId));
		formData.set('boxchecked', '1');

		if (select) {
			select.disabled = true;
		}

		fetch(form.getAttribute('action') || window.location.href, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(function (response) {
				return response.text().then(function (text) {
					var payload = null;

					try {
						payload = JSON.parse(text);
					} catch (e) {
						payload = null;
					}

					if (!response.ok || !payload || payload.success === false) {
						throw new Error((payload && payload.message) ? payload.message : 'Save failed');
					}

					return payload;
				});
			})
			.then(function () {
				if (select) {
					select.setAttribute('data-original-value', normalizedStateId === '0' ? '' : normalizedStateId);
				}

				contentbuilderngUpdateStateUi(String(targetRecordId), normalizedStateId, stateTitle, stateColor, selectedOption);
			})
			.catch(function (error) {
				if (select) {
					select.value = originalValue;
				}

				var message = error && error.message ? error.message : 'Save failed';
				if (window.Joomla && typeof Joomla.renderMessages === 'function') {
					Joomla.renderMessages({ error: [message] });
				} else {
					alert(message);
				}
			})
			.finally(function () {
				if (select) {
					select.disabled = false;
				}

				boxes.forEach(function (box) {
					box.checked = false;
				});
				contentbuilderng_updateBoxchecked(form);
			});
	}

	function contentbuilderng_publish() {
		var form = document.getElementById('adminForm');
		if (!form) return;
		if (contentbuilderng_selectedCount(form) < 1) {
			var publishSelect = form.querySelector('select[name="list_publish"]');
			if (publishSelect) {
				publishSelect.value = '-1';
			}
			contentbuilderng_updateBulkActionsAvailability(form);
			return;
		}
		document.getElementById('task').value = 'list.publish';
		Joomla.submitform('list.publish', form);
	}

	function contentbuilderng_language() {
		var form = document.getElementById('adminForm');
		document.getElementById('task').value = 'list.language';
		Joomla.submitform('list.language', form);
	}

	function contentbuilderngEscapeSelector(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(String(value));
		}

		return String(value).replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
	}

	function contentbuilderngUpdatePublishUi(recordId, published) {
		var selector = '[data-record-id="' + contentbuilderngEscapeSelector(recordId) + '"]';
		var nodes = document.querySelectorAll('[data-cb-publish-toggle]' + selector + ', [data-cb-publish-badge]' + selector);
		var iconClass = published ? 'fa-solid fa-check text-success' : 'fa-solid fa-circle-xmark text-danger';
		var title = published
			? cbListConfig.text.published
			: cbListConfig.text.unpublished;

		nodes.forEach(function (node) {
			if (node.hasAttribute('data-cb-publish-toggle')) {
				node.setAttribute('data-published', published ? '1' : '0');
				node.setAttribute('title', title);

				var href = String(node.getAttribute('href') || '');
				if (href !== '') {
					node.setAttribute(
						'href',
						href.replace(/([?&]list_publish=)(0|1)\b/, '$1' + (published ? '0' : '1'))
					);
				}
			}

			var icon = node.querySelector('[data-cb-publish-icon]');
			if (icon) {
				icon.className = iconClass;
				icon.setAttribute('title', title);
			}

			var sr = node.querySelector('.visually-hidden');
			if (sr) {
				sr.textContent = title;
			}
		});
	}

	function contentbuilderngHandlePublishToggleClick(toggle) {
		if (!toggle) {
			return;
		}

		var href = String(toggle.getAttribute('href') || '');
		var recordId = String(toggle.getAttribute('data-record-id') || '');
		if (href === '' || recordId === '' || toggle.getAttribute('data-cb-publish-busy') === '1') {
			return;
		}

		toggle.setAttribute('data-cb-publish-busy', '1');

		fetch(href + (href.indexOf('?') === -1 ? '?' : '&') + 'cb_ajax=1', {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(function (response) {
				return response.text().then(function (text) {
					var payload = null;

					try {
						payload = JSON.parse(text);
					} catch (e) {
						payload = null;
					}

					if (!response.ok || !payload || payload.success === false) {
						throw new Error((payload && payload.message) ? payload.message : 'Save failed');
					}

					return payload;
				});
			})
			.then(function () {
				contentbuilderngUpdatePublishUi(recordId, toggle.getAttribute('data-published') !== '1');
			})
			.catch(function (error) {
				var message = error && error.message ? error.message : 'Save failed';
				if (window.Joomla && typeof Joomla.renderMessages === 'function') {
					Joomla.renderMessages({ error: [message] });
				} else {
					alert(message);
				}
			})
			.finally(function () {
				toggle.removeAttribute('data-cb-publish-busy');
			});
	}

	document.addEventListener('DOMContentLoaded', function() {
		const form = document.getElementById('adminForm');
		if (!form) return;

		function syncListLimitFromSelect() {
			const select = form.querySelector('select[name="limit"], select[name="list[limit]"]');
			if (!select || !form.elements['list[limit]']) return;
			// Force Joomla 6 naming on the select itself.
			if (select.name !== 'list[limit]') {
				select.name = 'list[limit]';
				select.id = 'list_limit';
			}
			form.elements['list[limit]'].value = select.value;
		}

		// Limit box select (legacy name="limit" or Joomla name="list[limit]")
		const limitSelect = form.querySelector('select[name="limit"], select[name="list[limit]"]');
		if (limitSelect) {
			limitSelect.classList.add('form-select', 'form-select-sm', 'cb-filter-select-rpp');
			// Mirror legacy limit into Joomla 6 list[limit] and submit immediately.
			limitSelect.addEventListener('change', function() {
				syncListLimitFromSelect();
				if (form.elements['list[start]']) {
					form.elements['list[start]'].value = 0;
				}
				Joomla.submitform('', form);
			});
		}

		// Ensure the hidden Joomla 6 limit always reflects the visible select.
		form.addEventListener('submit', syncListLimitFromSelect);

		// Keep boxchecked in sync with manual row selection.
		const rowBoxes = form.querySelectorAll('input[name="cid[]"]');
		rowBoxes.forEach(function(box) {
			box.addEventListener('change', function() {
				contentbuilderng_updateBoxchecked(form);
			});
		});

		const resetButton = document.getElementById('cbResetButton');
		if (resetButton) {
			['contentbuilderng_filter', 'list_state_filter', 'list_publish_filter', 'list_language_filter'].forEach(function(name) {
				var field = form.querySelector('#' + name + ', [name="' + name + '"]');
				if (!field) return;
				field.addEventListener('change', function() {
					contentbuilderng_updateResetButtonState(form);
				});
				if (name === 'contentbuilderng_filter') {
					field.addEventListener('input', function() {
						contentbuilderng_updateResetButtonState(form);
					});
				}
			});
			contentbuilderng_updateResetButtonState(form);
		}

		document.addEventListener('click', function(event) {
			var toggle = event.target ? event.target.closest('[data-cb-publish-toggle]') : null;
			if (!toggle) {
				return;
			}

			event.preventDefault();
			contentbuilderngHandlePublishToggleClick(toggle);
		});

		contentbuilderngApplyInitialStateSelectStyles(form);
		contentbuilderng_updateBoxchecked(form);
		});
