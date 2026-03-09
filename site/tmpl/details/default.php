<?php

/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2024-2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;

$frontend = Factory::getApplication()->isClient('site');

$edit_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('edit') : ContentbuilderLegacyHelper::authorize('edit');
$delete_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('delete') : ContentbuilderLegacyHelper::authorize('delete');
$view_allowed = $frontend ? ContentbuilderLegacyHelper::authorizeFe('view') : ContentbuilderLegacyHelper::authorize('view');

$input = Factory::getApplication()->input;

$list = (array) $input->get('list', [], 'array');
$listStart = isset($list['start']) ? $input->getInt('list[start]', 0) : 0;
$listLimit = isset($list['limit']) ? $input->getInt('list[limit]', 0) : 0;
if ($listLimit === 0) {
    $listLimit = (int) Factory::getApplication()->get('list_limit');
}

$listOrdering = isset($list['ordering']) ? $input->getCmd('list[ordering]', '') : '';
$listDirection = isset($list['direction']) ? $input->getCmd('list[direction]', '') : '';
$listQuery = http_build_query(['list' => [
    'start' => $listStart,
    'limit' => $listLimit,
    'ordering' => $listOrdering,
    'direction' => $listDirection,
]]);
$previewQuery = '';
$previewEnabled = $input->getBool('cb_preview', false);
$previewUntil = $input->getInt('cb_preview_until', 0);
$previewSig = (string) $input->getString('cb_preview_sig', '');
$previewActorId = $input->getInt('cb_preview_actor_id', 0);
$previewActorName = (string) $input->getString('cb_preview_actor_name', '');
$isAdminPreview = $input->getBool('cb_preview_ok', false);
$showTopBar = $input->getInt('cb_show_details_top_bar', 1) === 1;
$directStorageMode = !empty($this->direct_storage_mode);
$directStorageId = (int) ($this->direct_storage_id ?? 0);
$directStorageUnpublished = !empty($this->direct_storage_unpublished);
$adminReturnContext = trim((string) $input->getCmd('cb_admin_return', ''));
$adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&task=form.edit&id=' . (int) $input->getInt('id', 0);
if ($directStorageMode && $directStorageId > 0) {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . $directStorageId;
}

if ($adminReturnContext === 'forms') {
    $adminReturnUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=forms';
}

$previewFormName = trim((string) ($this->form_name ?? ''));
if ($previewFormName === '') {
    $previewFormName = trim((string) ($this->page_title ?? ''));
}

if ($previewFormName === '') {
    $previewFormName = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
}

$previewFormName = htmlspecialchars($previewFormName, ENT_QUOTES, 'UTF-8');
$detailsTemplateMissing = $isAdminPreview && trim((string) ($this->tpl ?? '')) === '';
$detailsScreenAdminUrl = Uri::root() . 'administrator/index.php?option=com_contentbuilderng&view=form&layout=edit&id=' . (int) $input->getInt('id', 0) . '&tab=tab3&force_view_tab=tab3';

if ($previewEnabled && $previewUntil > 0 && $previewSig !== '') {
    $previewQuery = '&cb_preview=1'
        . '&cb_preview_until=' . $previewUntil
        . '&cb_preview_actor_id=' . (int) $previewActorId
        . '&cb_preview_actor_name=' . rawurlencode($previewActorName)
        . '&cb_preview_sig=' . rawurlencode($previewSig)
        . ($adminReturnContext !== '' ? '&cb_admin_return=' . rawurlencode($adminReturnContext) : '');
}

$printLink = Route::_('index.php?option=com_contentbuilderng&title=' . $input->get('title', '', 'string')
    . ($input->get('tmpl', '', 'string') != '' ? '&tmpl=' . $input->get('tmpl', '', 'string') : '')
    . ($input->get('layout', '', 'string') != '' ? '&layout=' . $input->get('layout', '', 'string') : '')
    . '&task=details.display&layout=print&tmpl=component&id=' . $input->getInt('id', 0)
    . '&record_id=' . $input->getCmd('record_id', 0)
    . $previewQuery);


$app = Factory::getApplication();
/** @var CMSApplication $app */

$document = $app->getDocument();
$wa = $document->getWebAssetManager();

// Charge le manifeste joomla.asset.json du composant
$wa->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');

$wa->useScript('jquery');
$wa->useScript('com_contentbuilderng.contentbuilderng');
?>

<?php if ($this->author)
    $document->setMetaData('author', $this->author); ?>

<?php if ($this->robots)
    $document->setMetaData('robots', $this->robots); ?>

<?php if ($this->rights)
    $document->setMetaData('rights', $this->rights); ?>

<?php if ($this->metakey)
    $document->setMetaData('keywords', $this->metakey); ?>

<?php if ($this->metadesc)
    $document->setMetaData('description', $this->metadesc); ?>

<?php if ($this->xreference)
    $document->setMetaData('xreference', $this->xreference); ?>

<?php
$themeCss = trim((string) ($this->theme_css ?? ''));
if ($themeCss !== '') {
    $wa->addInlineStyle($themeCss);
}

$themeJs = trim((string) ($this->theme_js ?? ''));
if ($themeJs !== '') {
    $wa->addInlineScript($themeJs);
}
?>
<?php
$wa->addInlineStyle(
    <<<'CSS'
.cbDetailsWrapper .cbToolBar.cbToolBar--top{
    position:sticky;
    top:var(--cb-details-sticky-top, .5rem);
    z-index:9;
    margin:.25rem 0 .9rem !important;
    padding:.42rem .5rem;
    border:1px solid rgba(36,61,86,.2);
    border-radius:.72rem;
    background:rgba(255,255,255,.96);
    box-shadow:0 .38rem .95rem rgba(16,32,56,.15);
    backdrop-filter:blur(6px);
}
.cbDetailsWrapper .cbToolBar.cbToolBar--top .btn{
    white-space:nowrap;
}
@media (prefers-color-scheme: dark){
    .cbDetailsWrapper .cbToolBar.cbToolBar--top{
        border-color:rgba(173,193,216,.3);
        background:rgba(26,36,49,.94);
        box-shadow:0 .38rem .95rem rgba(0,0,0,.42);
    }
    .cbDetailsWrapper .cbToolBar.cbToolBar--top .btn-outline-secondary{
        border-color:rgba(173,193,216,.34);
        color:#d8e5f5;
        background-color:transparent;
    }
    .cbDetailsWrapper .cbToolBar.cbToolBar--top .btn-outline-secondary:hover,
    .cbDetailsWrapper .cbToolBar.cbToolBar--top .btn-outline-secondary:focus{
        border-color:rgba(173,193,216,.45);
        background:#22344a;
        color:#f2f7ff;
    }
}
@media (max-width:767.98px){
    .cbDetailsWrapper .cbToolBar.cbToolBar--top{
        top:0;
        padding:.38rem;
    }
    .cbDetailsWrapper .cbToolBar.cbToolBar--top .btn{
        flex:1 1 calc(50% - .5rem);
        justify-content:center;
    }
}
CSS
);
?>
<script type="text/javascript">
    <!--
    function contentbuilderng_delete() {
        var confirmed = confirm('<?php echo Text::_('COM_CONTENTBUILDERNG_CONFIRM_DELETE_MESSAGE'); ?>');
        if (confirmed) {
            location.href = '<?php echo Uri::root() . ltrim(Route::_('index.php?option=com_contentbuilderng&title=' . Factory::getApplication()->input->get('title', '', 'string') . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . '&task=edit.delete&id=' . Factory::getApplication()->input->getInt('id', 0) . '&cid[]=' . Factory::getApplication()->input->getCmd('record_id', 0) . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . ($listQuery !== '' ? '&' . $listQuery : ''), false), '/'); ?>';
        }
    }
    //
    -->
</script>
<?php if (!$showTopBar && $this->print_button): ?>
    <div class="hidden-phone cbPrintBar d-flex justify-content-end mb-2">
        <a
            class="btn btn-sm btn-outline-secondary"
            href="javascript:window.open('<?php echo $printLink; ?>','win2','status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no');void(0);"><i
                class="fa fa-print" aria-hidden="true"></i> <?php echo Text::_('JGLOBAL_PRINT'); ?></a>
    </div>
<?php endif; ?>
<div class="cbDetailsWrapper">

    <?php if ($isAdminPreview || $directStorageMode): ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_MODE') . ' - ' . Text::sprintf($directStorageMode ? 'COM_CONTENTBUILDERNG_PREVIEW_CURRENT_STORAGE' : 'COM_CONTENTBUILDERNG_PREVIEW_CURRENT_FORM', $previewFormName); ?>
                <?php if (!$directStorageMode) : ?>
                    <?php echo ' - ' . Text::sprintf('COM_CONTENTBUILDERNG_PREVIEW_CONFIG_TAB', Text::_('COM_CONTENTBUILDERNG_PREVIEW_TAB_CONTENT_TEMPLATE')); ?>
                <?php endif; ?>
                <?php if ($isAdminPreview && $detailsTemplateMissing): ?>
                    <br />
                    <strong><?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_DETAILS_TEMPLATE_MISSING'); ?></strong>
                <?php endif; ?>
            </span>
            <span class="d-inline-flex flex-wrap align-items-center gap-2">
                <?php if ($isAdminPreview && $detailsTemplateMissing): ?>
                    <a class="btn btn-sm btn-outline-warning" href="<?php echo htmlspecialchars($detailsScreenAdminUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_OPEN_DETAIL_SCREEN'); ?>
                    </a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $adminReturnUrl; ?>">
                    <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                    <?php echo Text::_('COM_CONTENTBUILDERNG_BACK_TO_ADMIN'); ?>
                </a>
            </span>
        </div>
    <?php endif; ?>
    <?php if ($directStorageMode && $directStorageUnpublished): ?>
        <div class="alert alert-warning mb-3">
            <?php echo Text::_('COM_CONTENTBUILDERNG_PREVIEW_UNPUBLISHED_STORAGE_NOTICE'); ?>
        </div>
    <?php endif; ?>

    <?php
    $prevRecordId = property_exists($this, 'prev_record_id') ? (int) $this->prev_record_id : 0;
    $nextRecordId = property_exists($this, 'next_record_id') ? (int) $this->next_record_id : 0;
    $currentRecordLabel = trim((string) $input->getCmd('record_id', ''));
    $showCurrentRecordLabel = !in_array($currentRecordLabel, ['', '0'], true);
    $showCurrentRecordLabel = $showCurrentRecordLabel && (int) ($this->show_id_column ?? 0) === 1;
    $headingTitle = (string) ($this->page_title ?? '');
    if ($currentRecordLabel !== '') {
        foreach ([': ' . $currentRecordLabel, ' &raquo; ' . $currentRecordLabel] as $idSuffix) {
            if (str_ends_with($headingTitle, $idSuffix)) {
                $headingTitle = substr($headingTitle, 0, -strlen($idSuffix));
                break;
            }
        }
    }
    $detailsNavBaseLink = 'index.php?option=com_contentbuilderng&title=' . $input->get('title', '', 'string')
        . '&task=details.display&' . ($directStorageMode ? 'storage_id=' . $directStorageId : 'id=' . $input->getInt('id', 0))
        . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '')
        . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '')
        . '&Itemid=' . $input->getInt('Itemid', 0)
        . ($listQuery !== '' ? '&' . $listQuery : '')
        . $previewQuery;

    $showCloseButton = $this->show_back_button && Factory::getApplication()->input->getBool('cb_show_details_back_button', 1);
    $closeListLink = Route::_('index.php?option=com_contentbuilderng&title=' . Factory::getApplication()->input->get('title', '', 'string') . '&view=list&task=list.display&' . ($directStorageMode ? 'storage_id=' . $directStorageId : 'id=' . Factory::getApplication()->input->getInt('id', 0)) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . ($listQuery !== '' ? '&' . $listQuery : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . $previewQuery);
    $showActionToolbar = (
        (Factory::getApplication()->input->getInt('cb_show_details_back_button', 1) && $this->show_back_button)
        || $delete_allowed
        || $edit_allowed
        || ($showTopBar && ($this->print_button || $prevRecordId > 0 || $nextRecordId > 0 || $showCloseButton))
    );
    $showAuditTrail = $input->getInt('cb_show_author', 1) === 1;

    $createdOnText = '';
    if (!empty($this->created)) {
        $createdOnText = Text::_('COM_CONTENTBUILDERNG_CREATED_ON') . ' ' . HTMLHelper::_('date', $this->created, Text::_('DATE_FORMAT_LC5'));
    }

    $createdByText = '';
    if (!empty($this->created_by)) {
        $createdByText = Text::_('COM_CONTENTBUILDERNG_BY') . ' ' . htmlentities((string) $this->created_by, ENT_QUOTES, 'UTF-8');
    }

    $modifiedOnText = '';
    if (!empty($this->modified)) {
        $modifiedOnText = Text::_('COM_CONTENTBUILDERNG_LAST_UPDATED_ON') . ' ' . HTMLHelper::_('date', $this->modified, Text::_('DATE_FORMAT_LC5'));
    }

    $modifiedByText = '';
    if (!empty($this->modified_by)) {
        $modifiedByText = Text::_('COM_CONTENTBUILDERNG_BY') . ' ' . htmlentities((string) $this->modified_by, ENT_QUOTES, 'UTF-8');
    }

    $createdTrailText = trim($createdOnText . (($createdOnText !== '' && $createdByText !== '') ? ' ' : '') . $createdByText);
    $modifiedTrailText = trim($modifiedOnText . (($modifiedOnText !== '' && $modifiedByText !== '') ? ' ' : '') . $modifiedByText);
    ?>

    <?php
    if ($this->show_page_heading && $headingTitle !== '') {
    ?>
        <h1 class="display-6 mb-4">
            <?php if (!$showTopBar && ($prevRecordId > 0 || $nextRecordId > 0 || $showCloseButton)): ?>
                <span class="cbTitleRecordNav d-inline-flex flex-wrap gap-2 float-start me-2 mb-2">
                    <?php if ($showCurrentRecordLabel): ?>
                        <span class="small text-muted align-self-center px-1 cbCurrentRecordId">#<?php echo htmlspecialchars($currentRecordLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($prevRecordId > 0): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbPrevButton"
                            href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $prevRecordId); ?>"
                            title="<?php echo Text::_('JPREVIOUS'); ?>">
                            <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                            <?php echo Text::_('JPREVIOUS'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($nextRecordId > 0): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbNextButton"
                            href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $nextRecordId); ?>"
                            title="<?php echo Text::_('JNEXT'); ?>">
                            <?php echo Text::_('JNEXT'); ?>
                            <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($showCloseButton): ?>
                        <a
                            class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbCloseButton"
                            href="<?php echo $closeListLink; ?>"
                            title="<?php echo Text::_('COM_CONTENTBUILDERNG_CLOSE'); ?>">
                            <span class="fa-solid fa-xmark me-1" aria-hidden="true"></span>
                            <?php echo Text::_('COM_CONTENTBUILDERNG_CLOSE'); ?>
                        </a>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php echo $headingTitle; ?>
        </h1>
    <?php
    }
    ?>
    <?php echo $this->event->afterDisplayTitle; ?>

    <?php
    ob_start();
    ?>

    <?php
    if ($showActionToolbar) {
    ?>

        <div class="cbToolBar d-flex justify-content-end gap-2 flex-wrap mb-3">
        <?php
    }
        ?>

        <?php if ($showTopBar && ($prevRecordId > 0 || $nextRecordId > 0)): ?>
            <span class="cbRecordNavGroup d-inline-flex flex-wrap gap-2 me-auto">
                <?php if ($showCurrentRecordLabel): ?>
                    <span class="small text-muted align-self-center px-1 cbCurrentRecordId">#<?php echo htmlspecialchars($currentRecordLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if ($prevRecordId > 0): ?>
                    <a
                        class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbPrevButton"
                        href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $prevRecordId); ?>"
                        title="<?php echo Text::_('JPREVIOUS'); ?>">
                        <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                        <?php echo Text::_('JPREVIOUS'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($nextRecordId > 0): ?>
                    <a
                        class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbNextButton"
                        href="<?php echo Route::_($detailsNavBaseLink . '&record_id=' . $nextRecordId); ?>"
                        title="<?php echo Text::_('JNEXT'); ?>">
                        <?php echo Text::_('JNEXT'); ?>
                        <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
                    </a>
                <?php endif; ?>
            </span>
        <?php endif; ?>

        <?php if ($showTopBar && $this->print_button): ?>
            <a
                class="hidden-phone btn btn-sm btn-outline-secondary cbButton cbPrintButton"
                href="javascript:window.open('<?php echo $printLink; ?>','win2','status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no');void(0);"
                title="<?php echo Text::_('JGLOBAL_PRINT'); ?>">
                <i class="fa fa-print" aria-hidden="true"></i>
                <?php echo Text::_('JGLOBAL_PRINT'); ?>
            </a>
        <?php endif; ?>

        <?php if ($edit_allowed) { ?>
            <a class="btn btn-sm btn-primary cbButton cbEditButton"
                href="<?php echo Route::_('index.php?option=com_contentbuilderng&task=edit.display&id=' . Factory::getApplication()->input->getInt('id', 0) . '&record_id=' . Factory::getApplication()->input->getCmd('record_id', 0) . (Factory::getApplication()->input->get('tmpl', '', 'string') != '' ? '&tmpl=' . Factory::getApplication()->input->get('tmpl', '', 'string') : '') . '&Itemid=' . Factory::getApplication()->input->getInt('Itemid', 0) . (Factory::getApplication()->input->get('layout', '', 'string') != '' ? '&layout=' . Factory::getApplication()->input->get('layout', '', 'string') : '') . ($listQuery !== '' ? '&' . $listQuery : '') . $previewQuery); ?>"
                title="<?php echo Text::_('COM_CONTENTBUILDERNG_EDIT'); ?>">
                <span class="fa-solid fa-pen me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_EDIT') ?>
            </a>
        <?php
        }
        ?>
        <?php if ($delete_allowed) { ?>
            <button class="btn btn-sm btn-outline-danger cbButton cbDeleteButton d-inline-flex align-items-center gap-1 rounded-pill" onclick="contentbuilderng_delete();"
                title="<?php echo Text::_('COM_CONTENTBUILDERNG_DELETE'); ?>">
                <span class="fa-solid fa-trash" aria-hidden="true"></span>
                <span><?php echo Text::_('COM_CONTENTBUILDERNG_DELETE') ?></span>
            </button>
        <?php
        }
        ?>
        <?php if ($showCloseButton && ($showTopBar || (!$showTopBar && (!$this->show_page_heading || !$this->page_title)))): ?>
            <a class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbCloseButton"
                href="<?php echo $closeListLink; ?>"
                title="<?php echo Text::_('COM_CONTENTBUILDERNG_CLOSE'); ?>">
                <span class="fa-solid fa-xmark me-1" aria-hidden="true"></span>
                <?php echo Text::_('COM_CONTENTBUILDERNG_CLOSE') ?>
            </a>
        <?php endif; ?>

        <?php
        if ($showActionToolbar) {
        ?>

        </div>

    <?php
        }
    ?>

    <?php
    $buttons = ob_get_contents();
    ob_end_clean();

    if ($showTopBar) {
    ?>
        <div style="clear:right;"></div>
    <?php
        if ($buttons !== '') {
            echo str_replace('class="cbToolBar ', 'class="cbToolBar cbToolBar--top ', $buttons);
        }
    }
    ?>

    <div class="cbDetailsBody">
        <?php echo $this->event->beforeDisplayContent; ?>
        <?php echo $this->toc ?>
        <?php echo $this->tpl ?>
        <?php echo $this->event->afterDisplayContent; ?>
    </div>


    <?php if ($showAuditTrail && ($createdTrailText !== '' || $modifiedTrailText !== '')) : ?>
        <div class="cbAuditTrail mt-2 mb-2">
            <?php if ($createdTrailText !== '') : ?>
                <span class="small created-by"><?php echo $createdTrailText; ?></span>
            <?php endif; ?>
            <?php if ($modifiedTrailText !== '') : ?>
                <span class="small created-by"><?php echo $modifiedTrailText; ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <br />

    <?php
    if (Factory::getApplication()->input->getInt('cb_show_details_bottom_bar', 1)) {
        if ($buttons !== '') {
            echo str_replace('class="cbToolBar ', 'class="cbToolBar cbToolBar--bottom ', $buttons);
        }
    ?>
        <div style="clear:right;"></div>
    <?php
    }
    ?>

</div>
