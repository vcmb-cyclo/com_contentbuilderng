<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   (C) 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$toolbarClass = trim((string) ($displayData['toolbarClass'] ?? ''));
$currentRecordLabel = trim((string) ($displayData['currentRecordLabel'] ?? ''));
$showCurrentRecordLabel = !empty($displayData['showCurrentRecordLabel']) && $currentRecordLabel !== '';
$prevRecordId = (int) ($displayData['prevRecordId'] ?? 0);
$nextRecordId = (int) ($displayData['nextRecordId'] ?? 0);
$navBaseLink = (string) ($displayData['navBaseLink'] ?? '');
$extraHtml = (string) ($displayData['extraHtml'] ?? '');
$showDelete = !empty($displayData['showDelete']);
$deleteTitle = (string) ($displayData['deleteTitle'] ?? Text::_('COM_CONTENTBUILDERNG_DELETE'));
$deleteTooltip = (string) ($displayData['deleteTooltip'] ?? $deleteTitle);
$showClose = !empty($displayData['showClose']);
$closeTitle = (string) ($displayData['closeTitle'] ?? Text::_('COM_CONTENTBUILDERNG_BACK'));
$closeTooltip = (string) ($displayData['closeTooltip'] ?? $closeTitle);
$closeHref = trim((string) ($displayData['closeHref'] ?? ''));
$closeOnclick = trim((string) ($displayData['closeOnclick'] ?? ''));
$prevHref = trim((string) ($displayData['prevHref'] ?? ''));
$nextHref = trim((string) ($displayData['nextHref'] ?? ''));
$prevTooltip = (string) ($displayData['prevTooltip'] ?? Text::_('JPREVIOUS'));
$nextTooltip = (string) ($displayData['nextTooltip'] ?? Text::_('JNEXT'));

$hasNav = $navBaseLink !== '' && ($prevRecordId > 0 || $nextRecordId > 0);
$hasActions = $hasNav || $extraHtml !== '' || $showDelete || $showClose;

if (!$hasActions) {
    return;
}
?>
<div class="cbToolBar<?php echo $toolbarClass !== '' ? ' ' . htmlspecialchars($toolbarClass, ENT_QUOTES, 'UTF-8') : ''; ?> d-flex flex-wrap justify-content-end gap-2">
    <?php if ($hasNav) : ?>
        <span class="cbRecordNavGroup d-inline-flex flex-wrap gap-2 me-auto">
            <?php if ($showCurrentRecordLabel) : ?>
                <span class="small text-muted align-self-center px-1 cbCurrentRecordId">#<?php echo htmlspecialchars($currentRecordLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php if ($prevRecordId > 0) : ?>
                <a class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbPrevButton"
                    href="<?php echo Route::_($prevHref !== '' ? $prevHref : ($navBaseLink . '&record_id=' . $prevRecordId)); ?>"
                    title="<?php echo htmlspecialchars($prevTooltip, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                    <?php echo Text::_('JPREVIOUS'); ?>
                </a>
            <?php endif; ?>
            <?php if ($nextRecordId > 0) : ?>
                <a class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbNextButton"
                    href="<?php echo Route::_($nextHref !== '' ? $nextHref : ($navBaseLink . '&record_id=' . $nextRecordId)); ?>"
                    title="<?php echo htmlspecialchars($nextTooltip, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('JNEXT'); ?>
                    <span class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></span>
                </a>
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <?php echo $extraHtml; ?>

    <?php if ($showDelete) : ?>
        <button class="btn btn-sm btn-outline-danger cbButton cbDeleteButton d-inline-flex align-items-center gap-1 rounded-pill"
            onclick="contentbuilderng_delete();"
            title="<?php echo htmlspecialchars($deleteTooltip, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="fa-solid fa-trash" aria-hidden="true"></span>
            <span><?php echo htmlspecialchars($deleteTitle, ENT_QUOTES, 'UTF-8'); ?></span>
        </button>
    <?php endif; ?>

    <?php if ($showClose) : ?>
        <?php if ($closeOnclick !== '') : ?>
            <button class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbCloseButton"
                title="<?php echo htmlspecialchars($closeTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                onclick="<?php echo htmlspecialchars($closeOnclick, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="icon-undo me-1" aria-hidden="true"></span>
                <?php echo htmlspecialchars($closeTitle, ENT_QUOTES, 'UTF-8'); ?>
            </button>
        <?php elseif ($closeHref !== '') : ?>
            <a class="btn btn-sm btn-outline-secondary cbButton cbBackButton cbCloseButton"
                title="<?php echo htmlspecialchars($closeTooltip, ENT_QUOTES, 'UTF-8'); ?>"
                href="<?php echo $closeHref; ?>">
                <span class="icon-undo me-1" aria-hidden="true"></span>
                <?php echo htmlspecialchars($closeTitle, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>
