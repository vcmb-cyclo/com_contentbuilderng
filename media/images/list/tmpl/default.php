<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright (C) 2026 by XDA+GIL 
 * @license     GNU/GPL
 */

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderLegacyHelper;
use CB\Component\Contentbuilder_ng\Administrator\Helper\ContentbuilderHelper;
use CB\Component\Contentbuilder_ng\Administrator\Helper\RatingHelper;

/** @var SiteApplication $app */
$app = Factory::getApplication();
$frontend = $app->isClient('site');
$language_allowed = ContentbuilderLegacyHelper::authorizeFe('language');
$edit_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('edit') : ContentbuilderLegacyHelper::authorize('edit');
$delete_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('delete') : ContentbuilderLegacyHelper::authorize('delete');
$view_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('view') : ContentbuilderLegacyHelper::authorize('view');
$new_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('new') : ContentbuilderLegacyHelper::authorize('new');
$state_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('state') : ContentbuilderLegacyHelper::authorize('state');
$publish_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('publish') : ContentbuilderLegacyHelper::authorize('publish');
$rating_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('rating') : ContentbuilderLegacyHelper::authorize('rating');

$input = $app->input;
$previewQuery = '';
$previewEnabled = $input->getBool('cb_preview', false);
$previewUntil = $input->getInt('cb_preview_until', 0);
$previewSig = (string) $input->getString('cb_preview_sig', '');
$previewActorId = $input->getInt('cb_preview_actor_id', 0);
$previewActorName = (string) $input->getString('cb_preview_actor_name', '');
$isAdminPreview = $input->getBool('cb_preview_ok', false);
$adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilder_ng&task=form.edit&id=' . (int) $input->getInt('id', 0);
$previewFormName = trim((string) ($this->form_name ?? ''));
if ($previewFormName === '') {
    $previewFormName = trim((string) ($this->page_title ?? ''));
}
if ($previewFormName === '') {
    $previewFormName = Text::_('COM_CONTENTBUILDER_NG_NOT_AVAILABLE');
}
$previewFormName = htmlspecialchars($previewFormName, ENT_QUOTES, 'UTF-8');
if ($previewEnabled && $previewUntil > 0 && $previewSig !== '') {
    $previewQuery = '&cb_preview=1'
        . '&cb_preview_until=' . $previewUntil
        . '&cb_preview_actor_id=' . (int) $previewActorId
        . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
        . '&cb_preview_sig=' . rawurlencode($previewSig);
}
if ($isAdminPreview) {
    $view_allowed = true;
}

$document = $app->getDocument();
$wa = $document->getWebAssetManager();

// Charge le manifeste joomla.asset.json du composant
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilder_ng');

$wa->useScript('jquery');
$wa->useScript('com_contentbuilder_ng.contentbuilder_ng');

$___getpost = 'post';
$___tableOrdering = "Joomla.tableOrdering = function";

if (!empty($this->theme_css)) {
	$wa->addInlineStyle((string) $this->theme_css);
}

$wa->addInlineStyle(
	'.cb-scroll-x{overflow-x:auto;padding-bottom:.35rem;box-shadow:inset 0 -1px 0 rgba(0,0,0,.08)}'
	. '.cb-scroll-x::-webkit-scrollbar{height:12px}'
	. '.cb-scroll-x::-webkit-scrollbar-track{background:rgba(0,0,0,.06);border-radius:999px}'
	. '.cb-scroll-x::-webkit-scrollbar-thumb{background:rgba(13,110,253,.55);border-radius:999px}'
	. '.cb-scroll-x::-webkit-scrollbar-thumb:hover{background:rgba(13,110,253,.75)}'
	. '.cb-list-header{display:flex;justify-content:flex-end;align-items:center;margin:0 0 .75rem}'
	. '.cb-list-actions{display:flex;align-items:center;gap:.5rem}'
	. '.cb-list-actions .btn{border-radius:999px;padding-inline:1rem;font-weight:600}'
	. '.cb-list-panel{border:1px solid var(--bs-border-color, #dee2e6);border-radius:.9rem;padding:.65rem .75rem;background:var(--bs-body-bg, #fff);box-shadow:0 .35rem .9rem rgba(0,0,0,.06)}'
	. '.cb-list-filters td{padding:.4rem .15rem .75rem}'
	. '.cb-list-filters .form-select,.cb-list-filters .form-control{border-radius:.5rem}'
	. '.cb-list-filters .input-group-text{border-radius:.5rem 0 0 .5rem;background:var(--bs-tertiary-bg, #f8f9fa)}'
	. '.cb-list-table{margin-top:.35rem!important}'
	. '.cb-list-table th{font-size:.875rem;letter-spacing:.01em}'
	. '.cb-list-table td,.cb-list-table th{vertical-align:middle}'
	. '.cb-list-table .hidden-phone{display:table-cell!important}'
	. '.cb-list-table select[onchange*="contentbuilder_ng_state_single"]{display:inline-block!important;width:auto!important;min-width:0!important;max-width:100%!important}'
	. '.cb-pagination-summary{font-weight:500}'
	. '.cb-list-titlebar{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin:0 0 .9rem;padding:.65rem .9rem;border:1px solid rgba(13,110,253,.24);border-left:.35rem solid #0d6efd;border-radius:.85rem;background:linear-gradient(90deg,rgba(13,110,253,.11),rgba(13,110,253,.03));box-shadow:0 .35rem .9rem rgba(13,110,253,.12)}'
	. '.cb-list-title{margin:0!important;font-weight:700;letter-spacing:.01em;color:#12395f}'
	. '.cb-list-title::after{content:\"\";display:block;width:3.75rem;height:.2rem;margin-top:.45rem;border-radius:999px;background:linear-gradient(90deg,#0d6efd,#3f8cff)}'
	. '@media (max-width: 767.98px){.cb-list-actions{width:100%;justify-content:flex-end}.cb-list-panel{padding:.55rem .45rem}}'
	. '@media (max-width: 767.98px){.cb-list-titlebar{padding:.55rem .65rem;margin-bottom:.75rem}.cb-list-title{font-size:1.18rem}}'
);

if (!empty($this->theme_js)) {
	$wa->addInlineScript((string) $this->theme_js);
}
?>
<script>
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

	function contentbuilder_ng_updateBoxchecked(form) {
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
	}

	function contentbuilder_ng_selectAll(toggle) {
		var form = document.getElementById('adminForm');
		if (!form) return;
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		boxes.forEach(function(box) {
			box.checked = !!toggle.checked;
		});
		contentbuilder_ng_updateBoxchecked(form);
	}

	function contentbuilder_ng_delete() {
		if (confirm('<?php echo Text::_('COM_CONTENTBUILDER_NG_CONFIRM_DELETE_MESSAGE'); ?>')) {
			var form = document.getElementById('adminForm');
			document.getElementById('task').value = 'list.delete';
			Joomla.submitform('list.delete', form);
		}
	}

	function contentbuilder_ng_state() {
		var form = document.getElementById('adminForm');
		document.getElementById('task').value = 'list.state';
		Joomla.submitform('list.state', form);
	}

	function contentbuilder_ng_state_single(stateId, recordId) {
		var form = document.getElementById('adminForm');
		if (!form) return;
		if (stateId === undefined || stateId === null || String(stateId) === '') return;

		// Ensure only the clicked record is selected.
		var boxes = form.querySelectorAll('input[name="cid[]"]');
		boxes.forEach(function (box) {
			box.checked = String(box.value) === String(recordId);
		});

		// Prefer the bulk state select if present, otherwise create a hidden input.
		var stateSelect = form.querySelector('select[name="list_state"]');
		if (stateSelect) {
			stateSelect.value = stateId;
		} else {
			var hiddenState = document.getElementById('cb_list_state_value');
			if (!hiddenState) {
				hiddenState = document.createElement('input');
				hiddenState.type = 'hidden';
				hiddenState.name = 'list_state';
				hiddenState.id = 'cb_list_state_value';
				form.appendChild(hiddenState);
			}
			hiddenState.value = stateId;
		}

		document.getElementById('task').value = 'list.state';
		Joomla.submitform('list.state', form);
	}

	function contentbuilder_ng_publish() {
		var form = document.getElementById('adminForm');
		document.getElementById('task').value = 'list.publish';
		Joomla.submitform('list.publish', form);
	}

	function contentbuilder_ng_language() {
		var form = document.getElementById('adminForm');
		document.getElementById('task').value = 'list.language';
		Joomla.submitform('list.language', form);
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
			limitSelect.classList.add('form-select', 'form-select-sm');
			limitSelect.style.maxWidth = '120px';
			limitSelect.style.width = 'auto';
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
				contentbuilder_ng_updateBoxchecked(form);
			});
		});
	});
</script>

<?php if ($this->page_title): ?>
	<div class="cb-list-titlebar">
		<h1 class="h3 cb-list-title">
			<?php echo $this->page_title; ?>
		</h1>
	</div>
<?php endif; ?>
<?php if ($isAdminPreview): ?>
	<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
		<span>
			<?php echo Text::_('COM_CONTENTBUILDER_NG_PREVIEW_MODE') . ' - ' . Text::sprintf('COM_CONTENTBUILDER_NG_PREVIEW_CURRENT_FORM', $previewFormName) . ' - ' . Text::sprintf('COM_CONTENTBUILDER_NG_PREVIEW_CONFIG_TAB', Text::_('COM_CONTENTBUILDER_NG_PREVIEW_TAB_VIEW')); ?>
		</span>
		<a class="btn btn-sm btn-outline-secondary" href="<?php echo $adminReturnUrl; ?>">
			<?php echo Text::_('COM_CONTENTBUILDER_NG_BACK_TO_ADMIN'); ?>
		</a>
	</div>
<?php endif; ?>
<?php if (!empty($this->preview_no_list_fields)): ?>
	<div class="alert alert-warning mb-3">
		<?php echo Text::_('COM_CONTENTBUILDER_NG_PREVIEW_NO_LIST_FIELDS'); ?>
	</div>
<?php endif; ?>
<?php echo $this->intro_text; ?>
<div class="cb-list-header">
	<div class="cb-list-actions">
		<?php
		$showNewButton = ($new_allowed && !empty($this->new_button));
		if ($showNewButton) {
		?>
			<button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1"
				onclick="location.href='<?php echo Route::_(
				    'index.php?option=com_contentbuilder_ng&task=edit.display&backtolist=1&id='
				    . Factory::getApplication()->input->getInt('id', 0)
				    . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '')
				    . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '')
				    . '&record_id=0'
				    . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0)
				    . $previewQuery
				); ?>'">
				<span class="icon-plus" aria-hidden="true"></span>
				<?php echo Text::_('COM_CONTENTBUILDER_NG_NEW'); ?>
			</button>
		<?php } ?>
	</div>
</div>

<!-- 2023-12-19 XDA / GIL - BEGIN - Fix
<form action="index.php" method=<php echo $___getpost;?>" name="adminForm" id
Fix search, delete, pagination and 404 behavior.
Replace line 144 of media/com_contentbuilder_ng/images/list/tmpl/default.php
by this block. -->
<form action="<?php echo Route::_('index.php?option=com_contentbuilder_ng&task=list.display&id=' . (int) Factory::getApplication()->input->getInt('id') . '&Itemid=' . (int) Factory::getApplication()->input->getInt('Itemid', 0) . $previewQuery); ?>"
	method="<?php echo $___getpost; ?>" name="adminForm" id="adminForm">

	<!-- 2023-12-19 END -->
	<div class="cb-scroll-x cb-list-panel">
		<table class="cbFilterTable cb-list-filters" width="100%">
			<?php if ($language_allowed) : ?>
				<tr>
					<td>
						<div class="d-inline-flex align-items-center gap-1 me-2">
								<select class="form-select form-select-sm" style="max-width: 100px;" name="list_language">
								<option value="*"> -
									<?php echo Text::_('COM_CONTENTBUILDER_NG_LANGUAGE'); ?> -
								</option>
								<option value="*">
									<?php echo Text::_('COM_CONTENTBUILDER_NG_ANY'); ?>
								</option>
								<?php foreach ($this->languages as $filter_language) : ?>
									<option value="<?php echo $filter_language; ?>">
										<?php echo $filter_language; ?>
									</option>
								<?php endforeach; ?>
								</select>
								<button class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1" onclick="contentbuilder_ng_language();">
									<span class="icon-check" aria-hidden="true"></span>
									<?php echo Text::_('COM_CONTENTBUILDER_NG_APPLY'); ?>
								</button>
							</div>
					</td>
				</tr>
			<?php endif; ?>

			<tr>
				<td>
					<div class="d-flex flex-wrap align-items-center gap-2">

						<!-- GAUCHE : filtre + selects + boutons (optionnel) -->
						<div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">

							<?php if ($this->list_state && $state_allowed && count($this->states)) : ?>
								<select class="form-select form-select-sm" style="max-width: 140px;"
									name="list_state" title="<?php echo Text::_('COM_CONTENTBUILDER_NG_BULK_OPTIONS'); ?>: <?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT_STATE'); ?>"
									onchange="if (this.value !== '0') { contentbuilder_ng_state(); }">
									<option value="0"> - <?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT_STATE'); ?> -</option>
									<?php foreach ($this->states as $state) : ?>
										<option value="<?php echo $state['id']; ?>">
											<?php echo $state['title']; ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

							<?php if ($this->list_publish && $publish_allowed) : ?>
								<select class="form-select form-select-sm" style="max-width: 160px;"
									name="list_publish" title="<?php echo Text::_('COM_CONTENTBUILDER_NG_BULK_OPTIONS'); ?>: <?php echo Text::_('COM_CONTENTBUILDER_NG_PUBLISH'); ?>"
									onchange="if (this.value !== '-1') { contentbuilder_ng_publish(); }">
									<option value="-1"> - <?php echo Text::_('COM_CONTENTBUILDER_NG_UPDATE_STATUS'); ?> -</option>
									<option value="1"><?php echo Text::_('COM_CONTENTBUILDER_NG_PUBLISH'); ?></option>
									<option value="0"><?php echo Text::_('COM_CONTENTBUILDER_NG_UNPUBLISH'); ?></option>
								</select>
							<?php endif; ?>

							<?php if ($this->display_filter) : ?>
									<div class="input-group input-group-sm" style="max-width: 360px;">
									<span class="input-group-text">
										<?php echo Text::_('COM_CONTENTBUILDER_NG_FILTER'); ?>
									</span>

									<input
										type="text"
										class="form-control"
										id="contentbuilder_ng_filter"
										name="filter"
										value="<?php echo $this->escape($this->lists['filter']); ?>"
										onchange="document.adminForm.submit();" />

										<button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1" id="cbSearchButton">
											<span class="icon-search" aria-hidden="true"></span>
											<?php echo Text::_('COM_CONTENTBUILDER_NG_SEARCH'); ?>
										</button>

										<button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
											onclick="document.getElementById('contentbuilder_ng_filter').value='';
                <?php echo $this->list_language && count($this->languages) ? "if(document.getElementById('list_language_filter')) document.getElementById('list_language_filter').selectedIndex=0;" : ""; ?>
                <?php echo $this->list_state && count($this->states) ? "if(document.getElementById('list_state_filter')) document.getElementById('list_state_filter').selectedIndex=0;" : ""; ?>
                <?php echo $this->list_publish ? "if(document.getElementById('list_publish_filter')) document.getElementById('list_publish_filter').selectedIndex=0;" : ""; ?>
                document.adminForm.submit();">
											<span class="icon-times" aria-hidden="true"></span>
											<?php echo Text::_('COM_CONTENTBUILDER_NG_RESET'); ?>
										</button>
									</div>
								<?php endif; ?>

							<?php if ($this->list_state && count($this->states)) : ?>
								<select class="form-select form-select-sm" style="max-width: 160px;"
									name="list_state_filter" id="list_state_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDER_NG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT_STATE'); ?>"
									onchange="document.adminForm.submit();">
									<option value="0"> - <?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT_STATE'); ?> -</option>
									<?php foreach ($this->states as $state) : ?>
										<option value="<?php echo $state['id'] ?>" <?php echo $this->lists['filter_state'] == $state['id'] ? 'selected' : ''; ?>>
											<?php echo $state['title'] ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

							<?php if ($this->list_publish && $publish_allowed) : ?>
								<select class="form-select form-select-sm" style="max-width: 190px;"
									name="list_publish_filter" id="list_publish_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDER_NG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDER_NG_PUBLISH'); ?>"
									onchange="document.adminForm.submit();">
									<option value="-1"> - <?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?> -</option>
									<option value="1" <?php echo $this->lists['filter_publish'] == 1 ? 'selected' : ''; ?>>
										<?php echo Text::_('COM_CONTENTBUILDER_NG_PUBLISHED') ?>
									</option>
									<option value="0" <?php echo $this->lists['filter_publish'] == 0 ? 'selected' : ''; ?>>
										<?php echo Text::_('COM_CONTENTBUILDER_NG_UNPUBLISHED') ?>
									</option>
								</select>
							<?php endif; ?>

							<?php if ($this->list_language) : ?>
								<select class="form-select form-select-sm" style="max-width: 160px;"
									name="list_language_filter" id="list_language_filter"
									title="<?php echo Text::_('COM_CONTENTBUILDER_NG_FILTER'); ?>: <?php echo Text::_('COM_CONTENTBUILDER_NG_LANGUAGE'); ?>"
									onchange="document.adminForm.submit();">
									<option value=""> - <?php echo Text::_('COM_CONTENTBUILDER_NG_LANGUAGE'); ?> -</option>
									<?php foreach ($this->languages as $filter_language) : ?>
										<option value="<?php echo $filter_language; ?>" <?php echo $this->lists['filter_language'] == $filter_language ? 'selected' : ''; ?>>
											<?php echo $filter_language; ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>

						</div>

						<!-- DROITE : limitbox + excel (indépendants du filtre) -->
							<?php if ($this->show_records_per_page || $this->export_xls) : ?>
								<div class="d-flex align-items-center gap-2 ms-auto">

										<?php if ($delete_allowed) : ?>
											<button class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1 rounded-pill" onclick="contentbuilder_ng_delete();" title="<?php echo Text::_('COM_CONTENTBUILDER_NG_DELETE'); ?>">
												<span class="icon-trash" aria-hidden="true"></span>
												<span class="d-none d-md-inline"><?php echo Text::_('COM_CONTENTBUILDER_NG_DELETE'); ?></span>
											</button>
										<?php endif; ?>

									<?php if ($this->show_records_per_page) : ?>
										<div style="max-width: 120px;">
											<?php
											$currentLimit = (int) ($this->pagination->limit ?? 20);
											$totalItems = (int) ($this->pagination->total ?? 0);
											$limitOptions = [5, 10, 20, 50, 100, 500];
											if ($totalItems > 0) {
												$limitOptions[] = $totalItems;
											}
											?>
											<select
												id="list_limit"
												name="list[limit]"
												class="form-select form-select-sm"
												onchange="document.getElementById('adminForm').elements['list[start]'].value = 0; Joomla.submitform('', document.getElementById('adminForm'));"
											>
												<?php foreach ($limitOptions as $opt) : ?>
													<?php $label = ($totalItems > 0 && $opt === $totalItems) ? Text::_('JALL') : (string) $opt; ?>
													<option value="<?php echo $opt; ?>"<?php echo $opt === $currentLimit ? ' selected' : ''; ?>>
														<?php echo $label; ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
									<?php endif; ?>

										<?php if ($this->export_xls) : ?>
											<a class="btn btn-sm btn-outline-success align-self-center d-inline-flex align-items-center gap-1 rounded-pill"
												href="<?php echo Route::_('index.php?option=com_contentbuilder_ng&view=export&id=' . (int) Factory::getApplication()->input->getInt('id', 0) . '&type=xls&format=raw&tmpl=component'); ?>"
												title="<?php echo Text::_('COM_CONTENTBUILDER_NG_EXPORT_XLSX_TOOLTIP'); ?>">
												<span class="icon-download" aria-hidden="true"></span>
												<span>XLSX</span>
											</a>
										<?php endif; ?>

							</div>
						<?php endif; ?>

					</div>
				</td>
			</tr>
		</table>
			<table class="table table-striped table-hover align-middle cb-list-table">
			<thead>
				<tr>
					<?php
					if ($this->show_id_column) {
					?>
						<th class="table-light hidden-phone" width="5">
							<?php echo HTMLHelper::_('grid.sort', htmlentities('COM_CONTENTBUILDER_NG_ID', ENT_QUOTES, 'UTF-8'), 'colRecord', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->select_column && ($delete_allowed || $state_allowed || $publish_allowed)) {
					?>
						<th class="table-light hidden-phone" width="20">
							<input class="contentbuilder_ng_select_all form-check-input" type="checkbox"
								onclick="contentbuilder_ng_selectAll(this);" />
						</th>
					<?php
					}

					if ($this->edit_button && $edit_allowed) {
					?>
						<th class="table-light" width="20">
							<?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT'); ?>
						</th>
					<?php
					}

						if ($this->list_state) {
						?>
							<th class="table-light hidden-phone">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDER_NG_EDIT_STATE'), 'colState', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

						if ($this->list_publish && $publish_allowed) {
						?>
							<th class="table-light" width="20">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDER_NG_PUBLISHED'), 'colPublished', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

						if ($this->list_language) {
						?>
							<th class="table-light hidden-phone" width="20">
								<?php echo HTMLHelper::_('grid.sort', Text::_('COM_CONTENTBUILDER_NG_LANGUAGE'), 'colLanguage', $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
						<?php
						}

					if ($this->list_article) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', htmlentities('COM_CONTENTBUILDER_NG_ARTICLE', ENT_QUOTES, 'UTF-8'), 'colArticleId', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->list_author) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', htmlentities('COM_CONTENTBUILDER_NG_AUTHOR', ENT_QUOTES, 'UTF-8'), 'colAuthor', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
					<?php
					}

					if ($this->list_rating) {
					?>
						<th class="table-light hidden-phone">
							<?php echo HTMLHelper::_('grid.sort', htmlentities('COM_CONTENTBUILDER_NG_RATING', ENT_QUOTES, 'UTF-8'), 'colRating', $this->lists['order_Dir'], $this->lists['order']); ?>
						</th>
						<?php
					}

					if ($this->labels) {
						$label_count = 0;
						$hidden = ' hidden-phone';
						foreach ($this->labels as $reference_id => $label) {
							if ($label_count == 0) {
								$hidden = '';
							} else {
								$hidden = ' hidden-phone';
							}
						?>
							<th class="table-light<?php echo $hidden; ?>">
								<?php echo HTMLHelper::_('grid.sort', nl2br(htmlentities(ContentbuilderHelper::contentbuilder_ng_wordwrap($label, 20, "\n", true), ENT_QUOTES, 'UTF-8')), "col$reference_id", $this->lists['order_Dir'], $this->lists['order']); ?>
							</th>
					<?php
							$label_count++;
						}
					}
					?>
				</tr>
			</thead>
			<?php
			$k = 0;
			$n = count($this->items);
			for ($i = 0; $i < $n; $i++) {
				$row = $this->items[$i];
				$link = Route::_('index.php?option=com_contentbuilder_ng&task=details.display&id=' . $this->form_id . '&record_id=' . $row->colRecord . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . $previewQuery);
				$edit_link = Route::_('index.php?option=com_contentbuilder_ng&task=edit.display&backtolist=1&id=' . $this->form_id . '&record_id=' . $row->colRecord . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . $previewQuery);
					$isPublished = isset($this->published_items[$row->colRecord]) && $this->published_items[$row->colRecord];
					$togglePublish = $isPublished ? 0 : 1;
					$toggle_link = Route::_('index.php?option=com_contentbuilder_ng&task=edit.publish&backtolist=1&id=' . $this->form_id . '&list_publish=' . $togglePublish . '&cid[]=' . $row->colRecord . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . $previewQuery);
					$select = '<input class="form-check-input" type="checkbox" name="cid[]" value="' . $row->colRecord . '"/>';
				?>
				<tr class="<?php echo "row$k"; ?>">
					<?php
					if ($this->show_id_column) {
					?>
						<td class="hidden-phone">
							<?php
							if (($view_allowed || $this->own_only)) {
							?>
								<a href="<?php echo $link; ?>">
									<?php echo $row->colRecord; ?>
								</a>
							<?php
							} else {
							?>
								<?php echo $row->colRecord; ?>
							<?php
							}
							?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->select_column && ($delete_allowed || $state_allowed || $publish_allowed)) {
					?>
						<td class="hidden-phone">
							<?php echo $select; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->edit_button && $edit_allowed) {
					?>
						<td>
							<a class="text-primary" href="<?php echo $edit_link; ?>"
								title="<?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT'); ?>">
								<span class="fa-solid fa-pen" aria-hidden="true"></span>
							</a>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_state) {
					?>
						<td class="hidden-phone"
							style="background-color: #<?php echo isset($this->state_colors[$row->colRecord]) ? $this->state_colors[$row->colRecord] : 'FFFFFF'; ?>;">
							<?php if ($state_allowed && count($this->states)) : ?>
								<?php $currentStateTitle = $this->state_titles[$row->colRecord] ?? ''; ?>
									<select
										class="form-select form-select-sm"
										style="display:inline-block;width:auto;min-width:0;max-width:100%;"
										onchange="contentbuilder_ng_state_single(this.value, <?php echo (int) $row->colRecord; ?>);"
										title="<?php echo Text::_('COM_CONTENTBUILDER_NG_EDIT_STATE'); ?>">
									<option value="" <?php echo $currentStateTitle === '' ? 'selected' : ''; ?>>-</option>
									<?php foreach ($this->states as $state) : ?>
										<option value="<?php echo (int) $state['id']; ?>" <?php echo $currentStateTitle === $state['title'] ? 'selected' : ''; ?>>
											<?php echo htmlentities($state['title'], ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<?php echo isset($this->state_titles[$row->colRecord]) ? htmlentities($this->state_titles[$row->colRecord], ENT_QUOTES, 'UTF-8') : ''; ?>
							<?php endif; ?>
						</td>
					<?php
					}
					?>
						<?php
						if ($this->list_publish && $publish_allowed) {
						?>
							<td align="center" valign="middle">
								<?php
								$iconClass = $isPublished ? 'icon-publish text-success' : 'icon-unpublish text-danger';
								$iconTitle = $isPublished ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED');
								?>
								<a class="btn btn-sm btn-link p-0" href="<?php echo $toggle_link; ?>" title="<?php echo $iconTitle; ?>">
									<span class="<?php echo $iconClass; ?>" aria-hidden="true"></span>
									<span class="visually-hidden"><?php echo $iconTitle; ?></span>
								</a>
							</td>
						<?php
						}
						?>
					<?php
					if ($this->list_language) {
					?>
						<td class="hidden-phone">
							<?php echo isset($this->lang_codes[$row->colRecord]) && $this->lang_codes[$row->colRecord] ? $this->lang_codes[$row->colRecord] : '*'; ?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_article) {
					?>
						<td class="hidden-phone">
							<?php
							if (($view_allowed || $this->own_only)) {
							?>
								<a href="<?php echo $link; ?>">
									<?php echo $row->colArticleId; ?>
								</a>
							<?php
							} else {
							?>
								<?php echo $row->colArticleId; ?>
							<?php
							}
							?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_author) {
					?>
						<td class="hidden-phone">
							<?php
							if (($view_allowed || $this->own_only)) {
							?>
								<a href="<?php echo $link; ?>">
									<?php echo htmlentities($row->colAuthor, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							<?php
							} else {
							?>
								<?php echo htmlentities($row->colAuthor, ENT_QUOTES, 'UTF-8'); ?>
							<?php
							}
							?>
						</td>
					<?php
					}
					?>
					<?php
					if ($this->list_rating) {
					?>
						<td class="hidden-phone">
							<?php
								echo RatingHelper::getRating(Factory::getApplication()->input->getInt('id', 0), $row->colRecord, $row->colRating, $this->rating_slots, Factory::getApplication()->input->getCmd('lang', ''), $rating_allowed, $row->colRatingCount, $row->colRatingSum);
							?>
						</td>
					<?php
					}
					?>
					<?php
					$label_count = 0;
					$hidden = ' class="hidden-phone"';
					foreach ($row as $key => $value) {
						// filtering out disallowed columns
						if (in_array(str_replace('col', '', $key), $this->visible_cols)) {
							if ($label_count == 0) {
								$hidden = '';
							} else {
								$hidden = ' class="hidden-phone"';
							}
					?>
							<td<?php echo $hidden; ?>>
								<?php
								if (in_array(str_replace('col', '', $key), $this->linkable_elements) && ($view_allowed || $this->own_only)) {
								?>
									<a href="<?php echo $link; ?>">
										<?php echo $value; ?>
									</a>
								<?php
								} else {
								?>
									<?php echo $value; ?>
								<?php
								}
								?>
								</td>
						<?php
							$label_count++;
						}
					}
						?>
				</tr>
			<?php
				$k = 1 - $k;
			} ?>
				<?php
				$pagTotal = (int) ($this->pagination->total ?? 0);
				$pagLimit = max(1, (int) ($this->pagination->limit ?? 0));
				$pagStart = (int) ($this->lists['liststart'] ?? Factory::getApplication()->input->getInt('list[start]', 0));
				$pagPages = (int) ceil($pagTotal / $pagLimit);
				$pagCurrent = $pagPages > 0 ? (int) floor($pagStart / $pagLimit) + 1 : 1;
				$showSummary = $pagTotal > 0;
				$showPagination = $pagPages > 1;
				$rangeStart = $pagTotal > 0 ? $pagStart + 1 : 0;
				$rangeEnd = $pagTotal > 0 ? min($pagStart + $pagLimit, $pagTotal) : 0;

				if ($showSummary) :
				    $params = Uri::getInstance()->getQuery(true);
				    $params['option'] = 'com_contentbuilder_ng';
				    $params['task'] = 'list.display';
				    $params['id'] = Factory::getApplication()->input->getInt('id', 0);
				    $params['Itemid'] = Factory::getApplication()->input->getInt('Itemid', 0);
				    $params['list'] = [
				        'limit' => $pagLimit,
				        'ordering' => $this->lists['order'],
				        'direction' => $this->lists['order_Dir'],
				        'start' => 0,
				    ];

				    $buildPageLink = static function (int $start) use ($params): string {
				        $params['list']['start'] = max(0, $start);
				        return Route::_('index.php?' . http_build_query($params), false);
				    };

				?>
					<tfoot>
						<tr>
							<td colspan="1000">
									<nav class="pagination__wrapper d-flex flex-wrap align-items-center justify-content-start gap-2" aria-label="Pagination">
										<div class="small text-muted me-2 cb-pagination-summary">
											<?php echo $rangeStart . ' - ' . $rangeEnd . ' / ' . $pagTotal . ' items'; ?>
										</div>
									<?php if ($showPagination) : ?>
										<ul class="pagination pagination-sm mb-0">
											<li class="page-item<?php echo $pagCurrent <= 1 ? ' disabled' : ''; ?>">
												<a class="page-link" href="<?php echo $buildPageLink($pagStart - $pagLimit); ?>" aria-label="Previous">
													<span aria-hidden="true">&laquo;</span>
												</a>
											</li>
											<?php for ($p = 1; $p <= $pagPages; $p++) :
											    $startForPage = ($p - 1) * $pagLimit;
											?>
												<li class="page-item<?php echo $p === $pagCurrent ? ' active' : ''; ?>">
													<a class="page-link" href="<?php echo $buildPageLink($startForPage); ?>">
														<?php echo $p; ?>
													</a>
												</li>
											<?php endfor; ?>
											<li class="page-item<?php echo $pagCurrent >= $pagPages ? ' disabled' : ''; ?>">
												<a class="page-link" href="<?php echo $buildPageLink($pagStart + $pagLimit); ?>" aria-label="Next">
													<span aria-hidden="true">&raquo;</span>
												</a>
											</li>
										</ul>
									<?php endif; ?>
								</nav>
							</td>
						</tr>
					</tfoot>
				<?php endif; ?>
			</table>
		</div>
	<?php
	if (Factory::getApplication()->input->get('tmpl', '', 'string') != '') {
	?>
		<input type="hidden" name="tmpl" value="<?php echo Factory::getApplication()->input->get('tmpl', '', 'string'); ?>" />
	<?php
	}
		if ($previewQuery !== '') {
		?>
			<input type="hidden" name="cb_preview" value="1" />
			<input type="hidden" name="cb_preview_until" value="<?php echo (int) $previewUntil; ?>" />
			<input type="hidden" name="cb_preview_actor_id" value="<?php echo (int) $previewActorId; ?>" />
			<input type="hidden" name="cb_preview_actor_name" value="<?php echo htmlentities($previewActorName, ENT_QUOTES, 'UTF-8'); ?>" />
			<input type="hidden" name="cb_preview_sig" value="<?php echo htmlentities($previewSig, ENT_QUOTES, 'UTF-8'); ?>" />
		<?php
		}
	?>
	<input type="hidden" name="option" value="com_contentbuilder_ng" />
	<input type="hidden" name="task" id="task" value="" />
	<input type="hidden" name="view" id="view" value="list" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="Itemid" value="<?php echo Factory::getApplication()->input->getInt('Itemid', 0); ?>" />
	<input type="hidden" name="list[start]" value="<?php echo (int) ($this->lists['liststart'] ?? 0); ?>" />
	<input type="hidden" name="id" value="<?php echo Factory::getApplication()->input->getInt('id', 0) ?>" />
	<input type="hidden" name="list[ordering]" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="list[direction]" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="list[fullordering]" value="<?php echo trim(($this->lists['order'] ?? '') . ' ' . ($this->lists['order_Dir'] ?? '')); ?>" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
