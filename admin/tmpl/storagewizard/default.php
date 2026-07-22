<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use CB\Component\Contentbuilderng\Administrator\Service\StorageWizardService;

$currentStep = (string) ($this->wizardState['current_step'] ?? StorageWizardService::STEP_STORAGE);
$currentIndex = array_search($currentStep, $this->steps, true);
$currentIndex = $currentIndex === false ? 0 : (int) $currentIndex;

$stepLabels = [
    StorageWizardService::STEP_STORAGE => Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_STORAGE'),
    StorageWizardService::STEP_FIELDS => Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_FIELDS'),
    StorageWizardService::STEP_FORM => Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_FORM'),
    StorageWizardService::STEP_MENU => Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_MENU'),
    StorageWizardService::STEP_DONE => Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_DONE'),
];
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
    <div class="cb-wizard mt-3">
        <ol class="list-group list-group-horizontal-md mb-4 flex-wrap">
            <?php foreach ($this->steps as $index => $stepId) :
                $stateClass = 'list-group-item';
                if ($index < $currentIndex) {
                    $stateClass .= ' list-group-item-success';
                } elseif ($index === $currentIndex) {
                    $stateClass .= ' active';
                }
            ?>
                <li class="<?php echo $stateClass; ?> flex-fill text-center">
                    <?php echo (int) $index + 1; ?>. <?php echo htmlspecialchars($stepLabels[$stepId] ?? $stepId, ENT_QUOTES, 'UTF-8'); ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="card">
            <div class="card-body">
                <?php if ($currentStep === StorageWizardService::STEP_STORAGE) : ?>
                    <h2 class="h5"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_STORAGE'); ?></h2>
                    <p class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_STORAGE_DESC'); ?></p>
                    <div class="mb-3">
                        <label class="form-label" for="cb-wizard-title"><?php echo Text::_('COM_CONTENTBUILDERNG_STORAGE_TITLE'); ?></label>
                        <input class="form-control" type="text" id="cb-wizard-title" name="title" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cb-wizard-name"><?php echo Text::_('COM_CONTENTBUILDERNG_NAME'); ?></label>
                        <input class="form-control" type="text" id="cb-wizard-name" name="name" required maxlength="255">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('storagewizard.saveStorage')">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_NEXT'); ?>
                    </button>

                <?php elseif ($currentStep === StorageWizardService::STEP_FIELDS) : ?>
                    <h2 class="h5"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_FIELDS'); ?></h2>
                    <p class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_FIELDS_DESC'); ?></p>
                    <?php if ($this->storage) : ?>
                        <p>
                            <strong><?php echo htmlspecialchars((string) $this->storage->title, ENT_QUOTES, 'UTF-8'); ?></strong>
                            &mdash;
                            <?php echo Text::sprintf('COM_CONTENTBUILDERNG_WIZARD_FIELDS_COUNT', $this->fieldCount); ?>
                        </p>
                        <a
                            class="btn btn-outline-primary mb-3"
                            href="<?php echo Route::_('index.php?option=com_contentbuilderng&view=storage&layout=edit&id=' . (int) $this->storage->id . '&wizard=1'); ?>"
                        >
                            <span class="fa-solid fa-table-list me-1" aria-hidden="true"></span>
                            <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_OPEN_STORAGE_SCREEN'); ?>
                        </a>
                    <?php endif; ?>
                    <div>
                        <button
                            type="button"
                            class="btn btn-primary"
                            onclick="Joomla.submitbutton('storagewizard.confirmFields')"
                            <?php echo $this->fieldCount < 1 ? 'disabled' : ''; ?>
                        >
                            <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_NEXT'); ?>
                        </button>
                    </div>

                <?php elseif ($currentStep === StorageWizardService::STEP_FORM) : ?>
                    <h2 class="h5"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_FORM'); ?></h2>
                    <p class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_FORM_DESC'); ?></p>
                    <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('storagewizard.createForm')">
                        <span class="fa-solid fa-wand-magic-sparkles me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_CREATE_FORM'); ?>
                    </button>

                <?php elseif ($currentStep === StorageWizardService::STEP_MENU) : ?>
                    <h2 class="h5"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_MENU'); ?></h2>
                    <p class="text-muted"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_MENU_DESC'); ?></p>
                    <?php if (empty($this->menutypes)) : ?>
                        <div class="alert alert-warning"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_NO_MENUTYPES'); ?></div>
                    <?php else : ?>
                        <div class="mb-3">
                            <label class="form-label" for="cb-wizard-menutype"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_MENU_TYPE'); ?></label>
                            <select class="form-select" id="cb-wizard-menutype" name="menutype" required>
                                <?php foreach ($this->menutypes as $menutype) : ?>
                                    <option value="<?php echo htmlspecialchars((string) $menutype->menutype, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string) $menutype->title, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="cb-wizard-menu-title"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_MENU_ITEM_TITLE'); ?></label>
                            <input
                                class="form-control"
                                type="text"
                                id="cb-wizard-menu-title"
                                name="menu_title"
                                value="<?php echo htmlspecialchars((string) ($this->storage->title ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                required
                                maxlength="255"
                            >
                        </div>
                        <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('storagewizard.createMenu')">
                            <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_CREATE_MENU'); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-link" onclick="Joomla.submitbutton('storagewizard.skipMenu')">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_SKIP_MENU'); ?>
                    </button>

                <?php else : ?>
                    <h2 class="h5"><?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_STEP_DONE'); ?></h2>
                    <p class="text-success">
                        <span class="fa-solid fa-circle-check me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_DONE_DESC'); ?>
                    </p>
                    <ul class="list-unstyled">
                        <?php if ($this->storage) : ?>
                            <li>
                                <a href="<?php echo Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . (int) $this->storage->id); ?>">
                                    <?php echo htmlspecialchars((string) $this->storage->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ($this->form) : ?>
                            <li>
                                <a href="<?php echo Route::_('index.php?option=com_contentbuilderng&task=form.edit&id=' . (int) $this->form->id); ?>">
                                    <?php echo htmlspecialchars((string) $this->form->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <button type="button" class="btn btn-success" onclick="Joomla.submitbutton('storagewizard.finish')">
                        <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_FINISH'); ?>
                    </button>
                <?php endif; ?>

                <?php if ($currentIndex > 1) : ?>
                    <button type="button" class="btn btn-link mt-2 ms-n2" onclick="Joomla.submitbutton('storagewizard.back')">
                        <span class="fa-solid fa-arrow-left me-1" aria-hidden="true"></span>
                        <?php echo Text::_('COM_CONTENTBUILDERNG_WIZARD_PREVIOUS'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_contentbuilderng" />
    <input type="hidden" name="view" value="storagewizard" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
