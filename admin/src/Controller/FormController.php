<?php

/**
 * ContentBuilder NG Form controller.
 *
 * Handles CRUD and publish state for form in the admin interface.
 *
 * @package     ContentBuilderNG
 * @subpackage  Administrator.Controller
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @copyright   Copyright © 2024–2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController as BaseFormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use CB\Component\Contentbuilderng\Administrator\Model\FormModel;
use CB\Component\Contentbuilderng\Administrator\Model\ElementsModel;
use CB\Component\Contentbuilderng\Administrator\Model\ElementoptionsModel;
use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;

class FormController extends BaseFormController
{
    /**
     * Vues utilisées par les redirects du core
     */
    protected $view_list = 'forms';
    protected $view_item = 'form';

    private function getApp()
    {
        $app = $this->app;

        if (!$app instanceof CMSApplicationInterface) {
            throw new \RuntimeException('Unexpected application instance');
        }

        return $app;
    }

    private function getDatabase(): DatabaseInterface
    {
        return $this->getApp()->bootComponent('com_contentbuilderng')->getContainer()->get(DatabaseInterface::class);
    }

    private function getFormModelForSaveActions(): FormModel
    {
        $model = $this->getModel('Form', 'Administrator', ['ignore_request' => true])
            ?: $this->getModel('Form', 'Contentbuilderng', ['ignore_request' => true]);

        if (!$model instanceof FormModel) {
            throw new \RuntimeException('FormModel introuvable');
        }

        return $model;
    }

    private function getElementsModelForListActions(bool $ignoreRequest = false): ElementsModel
    {
        $config = $ignoreRequest ? ['ignore_request' => true] : [];
        $model = $this->getModel('Elements', 'Administrator', $config)
            ?: $this->getModel('Elements', 'Contentbuilderng', $config);

        if (!$model instanceof ElementsModel) {
            throw new \RuntimeException('ElementsModel introuvable');
        }

        return $model;
    }

    private function getElementoptionsModelForListActions(): ElementoptionsModel
    {
        $model = $this->getModel('Elementoptions', 'Administrator', ['ignore_request' => true])
            ?: $this->getModel('Elementoptions', 'Contentbuilderng', ['ignore_request' => true]);

        if (!$model instanceof ElementoptionsModel) {
            throw new \RuntimeException('ElementoptionsModel introuvable');
        }

        return $model;
    }

    #[\Override]
    public function edit($key = null, $urlVar = null)
    {
        try {
            $input = $this->input;

            // Remap cid[] -> id si besoin
            if (!$input->getInt('id')) {
                $cid = $input->get('cid', [], 'array');
                if (!empty($cid)) {
                    $input->set('id', (int) $cid[0]);
                }
            }

            return parent::edit($key, $urlVar);
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=forms.display', false));
            return false;
        }
    }

    /**
     * Nouveau
     */
    #[\Override]
    public function add()
    {
        try {
            // Tu peux aussi faire: return parent::add();
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=0', false));
            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=forms.display', false));
            return false;
        }
    }

    #[\Override]
    public function cancel($key = null): bool
    {
        $this->checkToken();
        $this->setRedirect($this->closeLink());

        return true;
    }

    /**
     * Lien de sortie de l'écran Formulaire (Fermer / Enregistrer & Fermer) :
     * retour à l'assistant Storage si actif, sinon liste Forms. Calculé ici
     * directement plutôt que délégué à `return=` (voir StorageController,
     * ce mécanisme natif s'est montré peu fiable en pratique).
     */
    private function closeLink(): string
    {
        if ($this->input->getBool('wizard', false)) {
            return Route::_('index.php?option=com_contentbuilderng&view=storagewizard', false);
        }

        return Route::_('index.php?option=com_contentbuilderng&view=forms', false);
    }

    #[\Override]
    public function save($key = null, $urlVar = null)
    {
        $isWizard = $this->input->getBool('wizard', false);
        $result = parent::save($key, $urlVar);

        if ($isWizard && $result !== false && !in_array($this->getTask(), ['apply', 'save2new', 'save2copy'], true)) {
            $this->setRedirect($this->closeLink());
        }

        return $result;
    }

    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
        // Preserve Joomla core behavior for "Save & New":
        // the redirect must not carry the current record id.
        if ($recordId === null && $this->getTask() === 'save2new') {
            return parent::getRedirectToItemAppend(null, $urlVar);
        }

        // Si le core ne passe pas l'id, on tente de le retrouver
        if (!$recordId) {
            // 1) depuis jform (POST)
            $jform = $this->input->post->get('jform', [], 'array');
            $recordId = (int) ($jform[$urlVar] ?? 0);

            // 2) depuis l'input (GET/POST)
            if (!$recordId) {
                $recordId = (int) $this->input->getInt($urlVar, 0);
            }

            // 3) depuis le model state (si dispo)
            if (!$recordId) {
                $model = $this->getModel($this->view_item, '', ['ignore_request' => true]);
                if ($model) {
                    $recordId = (int) $model->getState($model->getName() . '.id', 0);
                }
            }
        }

        // Appel au core pour conserver tmpl, return, etc.
        $append = parent::getRedirectToItemAppend($recordId, $urlVar);

        // Filet de sécurité : si le parent n’a pas ajouté id=...
        if ($recordId && strpos($append, $urlVar . '=') === false) {
            $append .= '&' . $urlVar . '=' . (int) $recordId;
        }

        return $append;
    }



    /**
     * Save & New : sauvegarde puis ouvre un nouvel item vide
     */
    public function save2new($key = null, $urlVar = null)
    {
        $this->checkToken();

        $model = $this->getFormModelForSaveActions();

        try {
            $jform = (array) $this->input->post->get('jform', [], 'array');
            $jform['id'] = (int) ($jform['id'] ?? $this->input->getInt('id', 0));
            $ok = $model->save($jform);
            $id = (int) $model->getState($model->getName() . '.id', 0);

            if (!$ok || !$id) {
                throw new \RuntimeException('Store failed (no id returned)');
            }

            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=0', false),
                Text::_('JLIB_APPLICATION_SAVE_SUCCESS'),
                'message'
            );

            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=0', false));
            return false;
        }
    }

    // ==================================================================
    // Toutes les tâches sur la liste des ÉLÉMENTS (champs du formulaire)
    // ==================================================================
    // Ces tâches agissent sur les éléments sélectionnés dans l'édition d'un form
    // Elles doivent utiliser Elements
    public function listorderup(): void
    {
        $formId = $this->resolveFormId();
        if (!$this->persistInlineElementSettings($formId)) {
            return;
        }

        $model = $this->getElementsModelForListActions();
        $model->move(-1); // ou utilise reorder si tu préfères
        $this->setRedirect($this->getEditRedirectUrl($formId));
    }

    public function listorderdown(): void
    {
        $formId = $this->resolveFormId();
        if (!$this->persistInlineElementSettings($formId)) {
            return;
        }

        $model = $this->getElementsModelForListActions();
        $model->move(1);
        $this->setRedirect($this->getEditRedirectUrl($formId));
    }

    public function saveorder(): void
    {
        $this->checkToken();

        $formId = $this->resolveFormId();
        $orderMap = (array) $this->input->post->get('order', [], 'array');

        if (empty($orderMap)) {
            $jform = (array) $this->input->post->get('jform', [], 'array');
            $orderMap = (array) ($jform['order'] ?? []);
        }

        if (empty($orderMap)) {
            $this->setMessage(Text::_('JGLOBAL_NO_MATCHING_RESULTS'), 'warning');
            $this->setRedirect($this->getEditRedirectUrl($formId));
            return;
        }

        if (!$this->persistInlineElementSettings($formId)) {
            return;
        }

        $pks = array_keys($orderMap);
        $order = array_values($orderMap);
        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        $model = $this->getElementsModelForListActions(true);

        $model->setFormId($formId);

        if (!$model->saveorder($pks, $order)) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'), 'warning');
        } else {
            $this->setMessage(Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
        }

        $this->setRedirect($this->getEditRedirectUrl($formId));
    }

    // ==================================================================
    // Toutes les tâches sur les ÉLÉMENTS (champs du formulaire)
    // ==================================================================
    // Ces tâches agissent sur les éléments sélectionnés dans l'édition d'un form
    // Elles doivent utiliser ElementoptionsModel
 
    protected function postSaveHook(BaseDatabaseModel $model, $validData = [])
    {
        $elementsModel = $this->getElementsModelForListActions(true);

        $orderMap = $this->input->post->get('order', [], 'array'); // [id => ordering]
        if (empty($orderMap)) {
            return;
        }

        $pks   = array_keys($orderMap);
        $order = array_values($orderMap);

        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        if (!$elementsModel->saveorder($pks, $order)) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'), 'warning');
        }
    }


    private const ALLOWED_FLAGS = ['linkable', 'editable', 'api_allowed', 'list_include', 'search_include'];

    public function element_flag(): void
    {
        $field = $this->input->getCmd('field');
        $value = $this->input->getInt('value') !== 0 ? 1 : 0;

        if (!in_array($field, self::ALLOWED_FLAGS, true)) {
            throw new \InvalidArgumentException('Invalid flag: ' . $field);
        }

        $this->elementsUpdate($field, $value);
    }

    public function element_publish(): bool
    {
        $value = $this->input->getInt('value') !== 0 ? 1 : 0;
        $msgKey = $value === 1 ? 'COM_CONTENTBUILDERNG_PUBLISHED' : 'COM_CONTENTBUILDERNG_UNPUBLISHED';

        return $this->elementsPublish($value, $msgKey);
    }

    public function save_labels(): bool
    {
        $this->checkToken();

        $formId = (int) $this->input->getInt('id');

        if (!$this->persistInlineElementSettings($formId)) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'));
            }
            return false;
        }

        if ($this->isAjaxCall()) {
            $this->respondAjax(true, Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
            return true;
        }

        $this->setRedirect(
            $this->getEditRedirectUrl($formId),
            Text::_('JLIB_APPLICATION_SAVE_SUCCESS')
        );

        return true;
    }

    public function formpublish(): bool
    {
        return $this->setFormPublishedState(1, 'COM_CONTENTBUILDERNG_PUBLISHED');
    }

    public function formunpublish(): bool
    {
        return $this->setFormPublishedState(0, 'COM_CONTENTBUILDERNG_UNPUBLISHED');
    }

    public function debug_on(): bool
    {
        return $this->setFormFlag('debug_mode', 1);
    }

    public function debug_off(): bool
    {
        return $this->setFormFlag('debug_mode', 0);
    }

    public function form_flag(): bool
    {
        $this->checkToken();

        $field = $this->input->getCmd('field');
        $value = $this->input->getInt('value') !== 0 ? 1 : 0;

        if (!in_array($field, ['debug_mode'], true)) {
            throw new \InvalidArgumentException('Invalid form flag: ' . $field);
        }

        return $this->setFormFlag($field, $value);
    }

    private function setFormFlag(string $field, int $value): bool
    {
        try {
            $this->checkToken();

            $formId = (int) $this->input->getInt('id');
            if ($formId <= 0) {
                $cids = (array) $this->input->get('cid', [], 'array');
                ArrayHelper::toInteger($cids);
                $formId = (int) ($cids[0] ?? 0);
            }

            if ($formId <= 0) {
                $this->setMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('JERROR_NO_ITEMS_SELECTED'));
                } else {
                    $this->setRedirect($this->getEditRedirectUrl(0));
                }
                return false;
            }

            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_forms'))
                ->set($db->quoteName($field) . ' = ' . (int) $value)
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($query);
            $db->execute();

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
                return true;
            }

            $this->setRedirect(
                $this->getEditRedirectUrl($formId),
                Text::_('JLIB_APPLICATION_SAVE_SUCCESS')
            );
            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
            } else {
                $this->setRedirect($this->getEditRedirectUrl((int) ($formId ?? 0)));
            }
            return false;
        }
    }

    // Devrait migrer dans Element*Controller ?
    private function elementsUpdate(string $field, int $value): bool
    {
        $this->checkToken();

        try {
            $cids = $this->input->get('cid', [], 'array');
            $formId = (int) $this->input->getInt('id');
            ArrayHelper::toInteger($cids);

            $formId = $this->input->getInt('id');

            if (empty($cids)) {
                $this->setMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('JERROR_NO_ITEMS_SELECTED'));
                } else {
                    $this->setRedirect($this->getEditRedirectUrl($formId));
                }
                return false;
            }

            if ($value === 1 && in_array($field, ['editable', 'search_include'], true)) {
                $cids = $this->filterEditableSystemFieldIds($cids, $formId);

                if (empty($cids)) {
                    $this->setMessage(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_RESTRICTED_ACTION'), 'warning');
                    if ($this->isAjaxCall()) {
                        $this->respondAjax(false, Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_RESTRICTED_ACTION'));
                    } else {
                        $this->setRedirect($this->getEditRedirectUrl($formId));
                    }
                    return false;
                }
            }

            if (!$this->persistInlineElementSettings($formId)) {
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'));
                }
                return false;
            }

            $model = $this->getElementoptionsModelForListActions();
            if (!$model->fieldUpdate($cids, $field, $value)) {
                $error = Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED');
                $this->setMessage($error, 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, $error);
                } else {
                    $this->setRedirect($this->getEditRedirectUrl($formId), $error, 'error');
                }
                return false;
            }

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
                return true;
            }

            $this->setRedirect(
                $this->getEditRedirectUrl($formId),
                Text::_('JLIB_APPLICATION_SAVE_SUCCESS')
            );
            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
            } else {
                $this->setRedirect($this->getEditRedirectUrl((int) $formId));
            }
            return false;
        }
    }

    public function add_bf_system_field(): bool
    {
        $this->checkToken();

        $formId = (int) $this->input->getInt('id');
        $referenceId = (int) $this->input->getInt('bf_system_reference_id', 0);

        try {
            if ($formId <= 0 || $referenceId >= 0) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_SELECT_REQUIRED'));
            }

            if (!$this->persistInlineElementSettings($formId)) {
                return false;
            }

            $db = $this->getDatabase();
            $formQuery = $db->getQuery(true)
                ->select($db->quoteName(['type', 'reference_id']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($formQuery);
            $formRow = $db->loadAssoc();

            if (!is_array($formRow) || empty($formRow['type']) || empty($formRow['reference_id'])) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'));
            }

            if ((string) $formRow['type'] !== 'com_breezingformsng') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_BF_ONLY'));
            }

            $sourceForm = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);

            if (
                !is_object($sourceForm)
                || empty($sourceForm->exists)
                || !method_exists($sourceForm, 'getSystemFieldDefinitions')
                || !$sourceForm::isSystemFieldReferenceId($referenceId)
            ) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_SELECT_REQUIRED'));
            }

            $definitions = $sourceForm::getSystemFieldDefinitions();
            $definition = $definitions[$referenceId];

            $existsQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('reference_id') . ' = ' . $referenceId);
            $db->setQuery($existsQuery);

            if ((int) $db->loadResult() > 0) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_ALREADY_ADDED'));
            }

            $orderingQuery = $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ') + 1')
                ->from($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . $formId);
            $db->setQuery($orderingQuery);
            $ordering = (int) $db->loadResult();

            $options = new \stdClass();
            $options->readonly = 1;
            $options->length = '';
            $options->maxlength = '';
            $options->password = 0;
            $options->seperator = ',';

            $insertQuery = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_elements'))
                ->columns($db->quoteName([
                    'label',
                    'form_id',
                    'reference_id',
                    'type',
                    'options',
                    'list_include',
                    'search_include',
                    'linkable',
                    'editable',
                    'api_allowed',
                    'published',
                    'order_type',
                    'ordering',
                ]))
                ->values(implode(',', [
                    $db->quote((string) $definition['label']),
                    $formId,
                    $referenceId,
                    $db->quote('text'),
                    $db->quote(PackedDataHelper::encodePackedData($options)),
                    0,
                    0,
                    0,
                    0,
                    0,
                    1,
                    $db->quote((string) ($definition['type'] ?? '')),
                    $ordering > 0 ? $ordering : 1,
                ]));
            $db->setQuery($insertQuery);
            $db->execute();

            $this->setRedirect(
                $this->getEditRedirectUrl($formId),
                Text::sprintf('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_ADDED', (string) $definition['label'])
            );
            return true;
        } catch (\Throwable $e) {
            $this->setRedirect($this->getEditRedirectUrl($formId), $e->getMessage(), 'warning');
            return false;
        }
    }

    public function ajax_add_bf_system_field(): void
    {
        $this->checkToken();

        $formId      = (int) $this->input->getInt('id');
        $referenceId = (int) $this->input->getInt('reference_id', 0);

        try {
            if ($formId <= 0 || $referenceId >= 0) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_SELECT_REQUIRED'));
            }

            $db        = $this->getDatabase();
            $formQuery = $db->getQuery(true)
                ->select($db->quoteName(['type', 'reference_id']))
                ->from($db->quoteName('#__contentbuilderng_forms'))
                ->where($db->quoteName('id') . ' = ' . $formId);
            $db->setQuery($formQuery);
            $formRow = $db->loadAssoc();

            if (!is_array($formRow) || empty($formRow['type']) || empty($formRow['reference_id'])) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_FORM_NOT_FOUND'));
            }

            if ((string) $formRow['type'] !== 'com_breezingformsng') {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_BF_ONLY'));
            }

            $sourceForm = FormSourceFactory::getForm((string) $formRow['type'], (string) $formRow['reference_id']);

            if (
                !is_object($sourceForm)
                || empty($sourceForm->exists)
                || !method_exists($sourceForm, 'getSystemFieldDefinitions')
                || !$sourceForm::isSystemFieldReferenceId($referenceId)
            ) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_SELECT_REQUIRED'));
            }

            $existsQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('reference_id') . ' = ' . $referenceId);
            $db->setQuery($existsQuery);

            if ((int) $db->loadResult() > 0) {
                throw new \RuntimeException(Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_ALREADY_ADDED'));
            }

            $definitions = $sourceForm::getSystemFieldDefinitions();
            $definition  = $definitions[$referenceId];

            $orderingQuery = $db->getQuery(true)
                ->select('MAX(' . $db->quoteName('ordering') . ') + 1')
                ->from($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('form_id') . ' = ' . $formId);
            $db->setQuery($orderingQuery);
            $ordering = (int) $db->loadResult();

            $options            = new \stdClass();
            $options->readonly  = 1;
            $options->length    = '';
            $options->maxlength = '';
            $options->password  = 0;
            $options->seperator = ',';

            $insertQuery = $db->getQuery(true)
                ->insert($db->quoteName('#__contentbuilderng_elements'))
                ->columns($db->quoteName([
                    'label', 'form_id', 'reference_id', 'type', 'options',
                    'list_include', 'search_include', 'linkable', 'editable',
                    'api_allowed', 'published', 'order_type', 'ordering',
                ]))
                ->values(implode(',', [
                    $db->quote((string) $definition['label']),
                    $formId,
                    $referenceId,
                    $db->quote('text'),
                    $db->quote(PackedDataHelper::encodePackedData($options)),
                    0, 0, 0, 0, 0, 1,
                    $db->quote((string) ($definition['type'] ?? '')),
                    $ordering > 0 ? $ordering : 1,
                ]));
            $db->setQuery($insertQuery);
            $db->execute();
            $newElementId = (int) $db->insertid();

            $this->respondAjaxData(
                true,
                Text::sprintf('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_ADDED', (string) $definition['label']),
                ['element_id' => $newElementId]
            );
        } catch (\Throwable $e) {
            $this->respondAjax(false, $e->getMessage());
        }
    }

    public function ajax_remove_bf_system_field(): void
    {
        $this->checkToken();

        $formId    = (int) $this->input->getInt('id');
        $elementId = (int) $this->input->getInt('element_id', 0);

        try {
            if ($formId <= 0 || $elementId <= 0) {
                throw new \RuntimeException(Text::_('JERROR_NO_ITEMS_SELECTED'));
            }

            $db          = $this->getDatabase();
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('id') . ' = ' . $elementId)
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('reference_id') . ' < 0');
            $db->setQuery($deleteQuery);
            $db->execute();

            if ((int) $db->getAffectedRows() === 0) {
                throw new \RuntimeException(Text::_('JERROR_NO_ITEMS_SELECTED'));
            }

            $table = $this->getElementsModelForListActions(true)->getTable('Elementoptions');
            $table->reorder('form_id = ' . $formId);

            $this->respondAjax(true, Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_DELETED'));
        } catch (\Throwable $e) {
            $this->respondAjax(false, $e->getMessage());
        }
    }

    public function remove_bf_system_field(): bool
    {
        $this->checkToken();

        $formId = (int) $this->input->getInt('id');
        $elementId = (int) $this->input->getInt('bf_system_element_id', 0);

        try {
            if ($formId <= 0 || $elementId <= 0) {
                throw new \RuntimeException(Text::_('JERROR_NO_ITEMS_SELECTED'));
            }

            if (!$this->persistInlineElementSettings($formId)) {
                return false;
            }

            $db = $this->getDatabase();
            $deleteQuery = $db->getQuery(true)
                ->delete($db->quoteName('#__contentbuilderng_elements'))
                ->where($db->quoteName('id') . ' = ' . $elementId)
                ->where($db->quoteName('form_id') . ' = ' . $formId)
                ->where($db->quoteName('reference_id') . ' < 0');
            $db->setQuery($deleteQuery);
            $db->execute();

            $table = $this->getElementsModelForListActions(true)->getTable('Elementoptions');
            $table->reorder('form_id = ' . $formId);

            $this->setRedirect($this->getEditRedirectUrl($formId), Text::_('COM_CONTENTBUILDERNG_BF_SYSTEM_FIELD_DELETED'));
            return true;
        } catch (\Throwable $e) {
            $this->setRedirect($this->getEditRedirectUrl($formId), $e->getMessage(), 'warning');
            return false;
        }
    }

    // Passe par le modèle.
    private function elementsPublish(int $state, string $successMsgKey)
    {
        try {
            $cids = $this->input->get('cid', [], 'array');
            ArrayHelper::toInteger($cids);

            $formId = (int) $this->input->getInt('id');

            if (empty($cids)) {
                $this->setMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('JERROR_NO_ITEMS_SELECTED'));
                } else {
                    $this->setRedirect($this->getEditRedirectUrl($formId));
                }
                return false;
            }

            if (!$this->persistInlineElementSettings($formId)) {
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'));
                }
                return false;
            }

            $model = $this->getElementoptionsModelForListActions();
            if (!$model->publish($cids, $state)) {
                $error = Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED');
                $this->setMessage($error, 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, $error);
                } else {
                    $this->setRedirect($this->getEditRedirectUrl($formId), $error, 'error');
                }
                return false;
            }

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_($successMsgKey));
                return true;
            }

            $this->setRedirect(
                $this->getEditRedirectUrl($formId),
                Text::_($successMsgKey)
            );

            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
            } else {
                $this->setRedirect($this->getEditRedirectUrl((int) ($formId ?? 0)));
            }
            return false;
        }
    }

    private function setFormPublishedState(int $state, string $successMsgKey): bool
    {
        try {
            $this->checkToken();

            $formId = (int) $this->input->getInt('id');
            if ($formId <= 0) {
                $cids = (array) $this->input->get('cid', [], 'array');
                ArrayHelper::toInteger($cids);
                $formId = (int) ($cids[0] ?? 0);
            }

            if ($formId <= 0) {
                $this->setMessage(Text::_('JERROR_NO_ITEMS_SELECTED'), 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('JERROR_NO_ITEMS_SELECTED'));
                } else {
                    $this->setRedirect($this->getEditRedirectUrl(0));
                }
                return false;
            }

            $model = $this->getFormModelForSaveActions();
            $pks = [$formId];
            if (!$model->publish($pks, (int) $state)) {
                $error = Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED');
                $this->setMessage($error, 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, $error);
                } else {
                    $this->setRedirect(
                        $this->getEditRedirectUrl($formId),
                        $error,
                        'error'
                    );
                }
                return false;
            }

            if ($this->isAjaxCall()) {
                $this->respondAjax(true, Text::_($successMsgKey));
                return true;
            }

            $this->setRedirect(
                $this->getEditRedirectUrl($formId),
                Text::_($successMsgKey)
            );

            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
            } else {
                $this->setRedirect(
                    $this->getEditRedirectUrl((int) ($formId ?? 0)),
                    $e->getMessage(),
                    'warning'
                );
            }
            return false;
        }
    }

    private function filterEditableSystemFieldIds(array $cids, int $formId): array
    {
        ArrayHelper::toInteger($cids);
        $cids = array_values(array_filter($cids));

        if ($cids === [] || $formId <= 0) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__contentbuilderng_elements'))
            ->where($db->quoteName('form_id') . ' = ' . $formId)
            ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $cids)) . ')')
            ->where($db->quoteName('reference_id') . ' >= 0');
        $db->setQuery($query);

        return array_map('intval', (array) $db->loadColumn());
    }

    private function resolveFormId(): int
    {
        $formId = (int) $this->input->getInt('id');

        if ($formId <= 0) {
            $jform = (array) $this->input->post->get('jform', [], 'array');
            $formId = (int) ($jform['id'] ?? 0);
        }

        return $formId;
    }

    private function getEditRedirectUrl(int $formId): string
    {
        $query = [
            'option=com_contentbuilderng',
            'task=form.display',
            'layout=edit',
            'id=' . max(0, $formId),
        ];

        $limitStart = $this->input->getInt('limitstart', -1);
        if ($limitStart >= 0) {
            $query[] = 'limitstart=' . $limitStart;
        }

        $limit = $this->input->getInt('limit', -1);
        if ($limit < 0) {
            $list = (array) $this->input->get('list', [], 'array');
            if (array_key_exists('limit', $list)) {
                $limit = (int) $list['limit'];
            }
        }
        if ($limit >= 0) {
            $query[] = 'limit=' . $limit;
        }

        $list = (array) $this->input->get('list', [], 'array');
        $ordering = (string) ($list['ordering'] ?? $this->input->getCmd('filter_order', ''));
        if ($ordering !== '') {
            $query[] = 'list[ordering]=' . rawurlencode($ordering);
        }

        $direction = strtoupper((string) ($list['direction'] ?? $this->input->getCmd('filter_order_Dir', '')));
        if ($direction !== '') {
            $query[] = 'list[direction]=' . rawurlencode($direction);
        }

        return Route::_(
            'index.php?' . implode('&', $query),
            false
        );
    }

    private function isAjaxCall(): bool
    {
        return (bool) $this->input->getInt('cb_ajax', 0);
    }

    private function respondAjax(bool $success, string $message = ''): void
    {
        echo new JsonResponse(['ok' => $success], $message, !$success);
        $this->getApp()->close();
    }

    private function respondAjaxData(bool $success, string $message, array $data): void
    {
        echo new JsonResponse(array_merge(['ok' => $success], $data), $message, !$success);
        $this->getApp()->close();
    }

    private function persistInlineElementSettings(int $formId): bool
    {
        if ($formId <= 0) {
            return true;
        }

        $formModel = $this->getFormModelForSaveActions();

        if (!$formModel->saveElementListSettingsFromRequest($formId)) {
            $this->setMessage(Text::_('COM_CONTENTBUILDERNG_SAVE_FAILED'), 'error');
            if (!$this->isAjaxCall()) {
                $this->setRedirect($this->getEditRedirectUrl($formId));
            }
            return false;
        }

        return true;
    }
}
