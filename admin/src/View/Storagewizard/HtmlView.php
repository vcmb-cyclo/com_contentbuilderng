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

namespace CB\Component\Contentbuilderng\Administrator\View\Storagewizard;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;
use CB\Component\Contentbuilderng\Administrator\Service\StorageWizardService;
use CB\Component\Contentbuilderng\Administrator\View\Contentbuilderng\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $wizardState = [];
    public array $steps = [];
    public ?object $storage = null;
    public ?object $form = null;
    public int $fieldCount = 0;
    public array $menutypes = [];
    public array $menuItems = [];

    private function getApp(): AdministratorApplication
    {
        $app = RuntimeContextHelper::getApplication();

        if (!$app instanceof AdministratorApplication) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = $this->getApp()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getDatabase(): DatabaseInterface
    {
        return $this->getComponent()->getContainer()->get(DatabaseInterface::class);
    }

    #[\Override]
    public function display($tpl = null): void
    {
        $app = $this->getApp();
        $app->getInput()->set('hidemainmenu', true);

        $wizardService = new StorageWizardService($app);
        $this->wizardState = $wizardService->getState();
        $this->steps = StorageWizardService::STEPS;

        $db = $this->getDatabase();
        $storageId = (int) ($this->wizardState['storage_id'] ?? 0);

        if ($storageId > 0) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'title', 'bytable']))
                ->from($db->quoteName('#__contentbuilderng_storages'))
                ->where($db->quoteName('id') . ' = ' . $storageId);
            $db->setQuery($query);
            $this->storage = $db->loadObject() ?: null;

            $countQuery = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__contentbuilderng_storage_fields'))
                ->where($db->quoteName('storage_id') . ' = ' . $storageId);
            $db->setQuery($countQuery);
            $this->fieldCount = (int) $db->loadResult();
        }

        $formId = (int) ($this->wizardState['form_id'] ?? 0);

        if ($formId > 0) {
            $formQuery = $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'tag']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($formQuery);
            $this->form = $db->loadObject() ?: null;
        }

        $menutypesQuery = $db->getQuery(true)
            ->select($db->quoteName(['menutype', 'title']))
            ->from($db->quoteName('#__menu_types'))
            ->order($db->quoteName('title'));
        $db->setQuery($menutypesQuery);
        $this->menutypes = $db->loadObjectList() ?: [];

        $menuItemsQuery = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'menutype', 'level']))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('menutype') . ' != ' . $db->quote(''))
            ->order($db->quoteName('menutype') . ', ' . $db->quoteName('lft'));
        $db->setQuery($menuItemsQuery);
        $this->menuItems = $db->loadObjectList() ?: [];

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(
            Text::_('COM_CONTENTBUILDERNG') . ' / ' . Text::_('COM_CONTENTBUILDERNG_WIZARD_TITLE'),
            'logo_left'
        );

        $toolbar = $this->getDocument()->getToolbar('toolbar');
        $toolbar->standardButton('restart')
            ->task('storagewizard.start')
            ->text('COM_CONTENTBUILDERNG_WIZARD_RESTART')
            ->icon('fa fa-rotate-left')
            ->listCheck(false);

        ToolbarHelper::cancel('storagewizard.finish', 'JTOOLBAR_CLOSE');
    }
}
