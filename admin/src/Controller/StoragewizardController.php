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

namespace CB\Component\Contentbuilderng\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Model\StorageModel;
use CB\Component\Contentbuilderng\Administrator\Service\DirectStorageFormProvisioningService;
use CB\Component\Contentbuilderng\Administrator\Service\StorageWizardService;

/**
 * "Assistant" wizard: guides the admin from a blank Storage through fields
 * (CSV import or manual, reusing the existing Storage edit screen), a
 * consultation form, and a site menu item — all in one guided flow.
 *
 * The wizard is a thin orchestrator: it owns only the state (session) and
 * the storage-creation / form-creation / menu-creation actions that don't
 * already exist elsewhere. Field management (CSV import, manual add,
 * reorder) is not reimplemented — the wizard links out to the existing,
 * fully-functional Storage edit screen for that step.
 */
final class StoragewizardController extends BaseController
{
    protected $default_view = 'storagewizard';

    private function getApp(): AdministratorApplication
    {
        $app = $this->app;

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

    private function getWizardService(): StorageWizardService
    {
        return new StorageWizardService($this->getApp());
    }

    private function requireManagePermission(): void
    {
        if (!$this->getApp()->getIdentity()->authorise('core.manage', 'com_contentbuilderng')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function redirectToWizard(string $msg = '', string $type = 'message'): void
    {
        $link = Route::_('index.php?option=com_contentbuilderng&view=storagewizard', false);
        $this->setRedirect($link, $msg, $type);
    }

    /**
     * Task: storagewizard.back — revient à l'étape précédente sans perdre
     * les données déjà collectées (storage_id/form_id restent en état).
     */
    public function back(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $wizardService = $this->getWizardService();
        $state = $wizardService->getState();
        $currentIndex = $wizardService->stepIndex((string) ($state['current_step'] ?? StorageWizardService::STEP_STORAGE));

        // Ne jamais redescendre jusqu'à l'étape "storage" : saveStorage() crée
        // toujours un nouveau storage (pas d'édition), y retourner puis
        // cliquer "Suivant" créerait un doublon. L'étape "fields" est le
        // plancher pour "Précédent".
        if ($currentIndex > 1) {
            $state = $wizardService->advanceTo($state, StorageWizardService::STEPS[$currentIndex - 1]);
            $wizardService->saveState($state);
        }

        $this->redirectToWizard();
    }

    /**
     * Task: storagewizard.start — (re)starts the wizard from a clean state.
     */
    public function start(): void
    {
        $this->requireManagePermission();

        $this->getWizardService()->reset();

        $this->redirectToWizard();
    }

    /**
     * Task: storagewizard.saveStorage — étape 1, crée le storage (nom/titre)
     * puis avance à l'étape "fields".
     */
    public function saveStorage(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $name = trim((string) $this->input->post->getString('name', ''));
        $title = trim((string) $this->input->post->getString('title', ''));

        if ($name === '') {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_STORAGE_FIELDS_REQUIRED'), 'error');

            return;
        }

        // Le titre est facultatif : à défaut, on reprend le nom (même
        // comportement que StorageModel::prepareTable() pour l'écran Storage
        // classique).
        if ($title === '') {
            $title = $name;
        }

        /** @var StorageModel|null $model */
        $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);

        if (!$model) {
            throw new \RuntimeException('StorageModel introuvable');
        }

        $data = [
            'id' => 0,
            'name' => $name,
            'title' => $title,
            'published' => 1,
            'ordering' => 0,
        ];

        if (!$model->save($data)) {
            $this->redirectToWizard($model->getError() ?: Text::_('COM_CONTENTBUILDERNG_ERROR'), 'error');

            return;
        }

        $storageId = (int) $model->getState($model->getName() . '.id', 0);

        if (!$storageId) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_ERROR'), 'error');

            return;
        }

        $model->ensureDataTable($storageId, true, null);

        $wizardService = $this->getWizardService();
        $state = $wizardService->getState();
        $state['storage_id'] = $storageId;
        $state = $wizardService->advanceTo($state, StorageWizardService::STEP_FIELDS);
        $wizardService->saveState($state);

        $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_STORAGE_CREATED'));
    }

    /**
     * Task: storagewizard.confirmFields — étape 2, l'admin a géré les champs
     * sur l'écran Storage (CSV ou manuel) puis revient confirmer.
     */
    public function confirmFields(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $wizardService = $this->getWizardService();
        $state = $wizardService->getState();
        $storageId = (int) ($state['storage_id'] ?? 0);

        if (!$storageId) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_NO_STORAGE'), 'error');

            return;
        }

        $db = $this->getComponent()->getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__contentbuilderng_storage_fields'))
            ->where($db->quoteName('storage_id') . ' = ' . (int) $storageId);
        $db->setQuery($query);
        $fieldCount = (int) $db->loadResult();

        if ($fieldCount < 1) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_NO_FIELDS'), 'error');

            return;
        }

        $state = $wizardService->advanceTo($state, StorageWizardService::STEP_FORM);
        $wizardService->saveState($state);

        $this->redirectToWizard();
    }

    /**
     * Task: storagewizard.createForm — étape 3, provisionne (ou réutilise)
     * le #__contentbuilderng_forms du storage via le même service que le
     * mode "storage direct" côté site.
     */
    public function createForm(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $wizardService = $this->getWizardService();
        $state = $wizardService->getState();
        $storageId = (int) ($state['storage_id'] ?? 0);

        if (!$storageId) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_NO_STORAGE'), 'error');

            return;
        }

        try {
            $formId = $this->getComponent()->getContainer()
                ->get(DirectStorageFormProvisioningService::class)
                ->resolveOrCreateFormId($storageId);
        } catch (\Throwable $e) {
            Logger::exception($e);
            $this->redirectToWizard($e->getMessage(), 'error');

            return;
        }

        if (!$formId) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_ERROR'), 'error');

            return;
        }

        // On reste sur l'étape "form" (pas d'avance automatique) : l'utilisateur
        // peut maintenant ouvrir l'écran Formulaire pour le personnaliser avant
        // de passer à l'étape menu, comme pour l'étape "fields"/Storage.
        $state['form_id'] = $formId;
        $wizardService->saveState($state);

        $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_FORM_CREATED'));
    }

    /**
     * Task: storagewizard.confirmForm — étape 3 → 4, valide qu'un formulaire
     * a bien été créé et passe à l'étape menu.
     */
    public function confirmForm(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $wizardService = $this->getWizardService();
        $state = $wizardService->getState();

        if ((int) ($state['form_id'] ?? 0) < 1) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_NO_FORM'), 'error');

            return;
        }

        $state = $wizardService->advanceTo($state, StorageWizardService::STEP_MENU);
        $wizardService->saveState($state);

        $this->redirectToWizard();
    }

    /**
     * Task: storagewizard.createMenu — étape 4, crée un item de menu de site
     * pointant vers la liste du storage.
     */
    public function createMenu(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $wizardService = $this->getWizardService();
        $state = $wizardService->getState();
        $storageId = (int) ($state['storage_id'] ?? 0);

        if (!$storageId) {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_NO_STORAGE'), 'error');

            return;
        }

        $menutype = trim((string) $this->input->post->getCmd('menutype', ''));
        $title = trim((string) $this->input->post->getString('menu_title', ''));
        $parentId = (int) $this->input->post->getInt('parent_id', 1);

        if ($menutype === '' || $title === '') {
            $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_MENU_FIELDS_REQUIRED'), 'error');

            return;
        }

        try {
            $menuItemId = $this->createMenuItem($storageId, $menutype, $title, $parentId);
        } catch (\Throwable $e) {
            Logger::exception($e);
            $this->redirectToWizard($e->getMessage(), 'error');

            return;
        }

        $state['menu_item_id'] = $menuItemId;
        $state = $wizardService->advanceTo($state, StorageWizardService::STEP_DONE);
        $wizardService->saveState($state);

        $this->redirectToWizard(Text::_('COM_CONTENTBUILDERNG_WIZARD_MENU_CREATED'));
    }

    /**
     * Task: storagewizard.skipMenu — passe directement à l'étape finale
     * sans créer d'item de menu.
     */
    public function skipMenu(): void
    {
        $this->checkToken();
        $this->requireManagePermission();

        $wizardService = $this->getWizardService();
        $state = $wizardService->advanceTo($wizardService->getState(), StorageWizardService::STEP_DONE);
        $wizardService->saveState($state);

        $this->redirectToWizard();
    }

    /**
     * Task: storagewizard.finish — termine l'assistant et revient à la liste
     * des storages.
     */
    public function finish(): void
    {
        $this->requireManagePermission();

        $this->getWizardService()->reset();

        $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=storages.display', false));
    }

    /**
     * Réutilise un item de menu existant pointant déjà vers ce storage (même
     * lien exact) dans le menutype choisi, plutôt que d'en créer un doublon
     * à chaque nouveau passage dans l'assistant pour le même storage.
     */
    private function findExistingMenuItemId(DatabaseInterface $db, string $menutype, string $link): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = :menutype')
            ->where($db->quoteName('link') . ' = :link')
            ->where($db->quoteName('client_id') . ' = 0')
            ->bind(':menutype', $menutype)
            ->bind(':link', $link);
        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult();
    }

    /**
     * Résout le menutype effectif d'un item de menu parent (ignore la valeur
     * choisie séparément dans le select "Type de menu" si elle diverge :
     * un item de menu appartient physiquement à l'arbre imbriqué de SON
     * menutype, on ne peut pas le rattacher à un autre).
     */
    private function resolveParentMenutype(DatabaseInterface $db, int $parentId, string $fallbackMenutype): string
    {
        if ($parentId <= 1) {
            return $fallbackMenutype;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('menutype'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('id') . ' = :parentId')
            ->where($db->quoteName('client_id') . ' = 0')
            ->bind(':parentId', $parentId, ParameterType::INTEGER);
        $db->setQuery($query);
        $actual = (string) $db->loadResult();

        return $actual !== '' ? $actual : $fallbackMenutype;
    }

    private function createMenuItem(int $storageId, string $menutype, string $title, int $parentId = 1): int
    {
        $db = $this->getComponent()->getContainer()->get(DatabaseInterface::class);
        $link = 'index.php?option=com_contentbuilderng&task=list.display&storage_id=' . $storageId;
        $menutype = $this->resolveParentMenutype($db, $parentId, $menutype);

        $existingId = $this->findExistingMenuItemId($db, $menutype, $link);

        if ($existingId > 0) {
            return $existingId;
        }

        $menusComponent = $this->getApp()->bootComponent('com_menus');
        $table = $menusComponent->getMVCFactory()->createTable('Menu', 'Administrator', ['dbo' => $db]);

        $alias = OutputFilter::stringUrlSafe($title);
        if (trim($alias) === '') {
            $alias = 'item-' . time();
        }

        $componentId = (int) ComponentHelper::getComponent('com_contentbuilderng')->id;

        $data = [
            'id' => 0,
            'menutype' => $menutype,
            'title' => $title,
            'alias' => $alias,
            'note' => '',
            'link' => $link,
            'type' => 'component',
            'published' => 1,
            'component_id' => $componentId,
            'checked_out' => 0,
            'checked_out_time' => null,
            'browserNav' => 0,
            'access' => 1,
            'img' => '',
            'template_style_id' => 0,
            'params' => '{}',
            'home' => 0,
            'language' => '*',
            'client_id' => 0,
            'publish_up' => null,
            'publish_down' => null,
        ];

        $table->setLocation($parentId > 0 ? $parentId : 1, 'last-child');

        if (!$table->bind($data) || !$table->check() || !$table->store()) {
            throw new \RuntimeException($table->getError() ?: Text::_('COM_CONTENTBUILDERNG_ERROR'));
        }

        $table->rebuildPath((int) $table->id);

        return (int) $table->id;
    }
}
