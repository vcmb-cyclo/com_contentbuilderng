<?php
/**
 * ContentBuilder NG Form controller.
 *
 * Handles CRUD and publish state for form in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Controller
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController as BaseFormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use CB\Component\Contentbuilderng\Administrator\Model\FormModel;
use CB\Component\Contentbuilderng\Administrator\Model\ElementsModel;
use CB\Component\Contentbuilderng\Administrator\Model\ElementoptionsModel;

class FormController extends BaseFormController
{
    /**
     * Vues utilisées par les redirects du core
     */
    protected $view_list = 'forms';
    protected $view_item = 'form';

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


    /**
     * Apply : sauvegarde et reste sur l'édition
     */
    /*public function apply($key = null, $urlVar = null)
    {
        $model = $this->getModel('Form', '', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('FormModel introuvable');
        }

        try {
            $id = $model->store();

            if (!$id) {
                $this->setRedirect(
                    Route::_(
                        'index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . (int) $this->input->getInt('id', 0),
                        false
                    ),
                    Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'),
                    'error'
                );
                return false;
            }

            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . (int) $id, false),
                Text::_('JLIB_APPLICATION_SAVE_SUCCESS'),
                'message'
            );

            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . (int) $this->input->getInt('id', 0),
                    false
                )
            );
            return false;
        }
    }
*/
    /*
    public function save($key = null, $urlVar = null)
    {
        $model = $this->getModel('Form', '', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('FormModel introuvable');
        }

        try {
            $id = $model->store();

            if (!$id) {
                $this->setRedirect(
                    Route::_(
                        'index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . (int) $this->input->getInt('id', 0),
                        false
                    ),
                    Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'),
                    'error'
                );
                return false;
            }

            // ✅ Comportement actuel: rester sur l'édition
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . (int) $id, false),
                Text::_('JLIB_APPLICATION_SAVE_SUCCESS'),
                'message'
            );

            // 👉 Si tu veux plutôt revenir à la liste après Save :
            // $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=forms.display', false), Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));

            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            $this->setRedirect(
                Route::_(
                    'index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . (int) $this->input->getInt('id', 0),
                    false
                )
            );
            return false;
        }
    }*/


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

    public function editable_include(): void
    {
        $this->editable();
    }


    // ==================================================================
    // Toutes les tâches sur la liste des ÉLÉMENTS (champs du formulaire)
    // ==================================================================
    // Ces tâches agissent sur les éléments sélectionnés dans l'édition d'un form
    // Elles doivent utiliser Elements
    public function listorderup(): void
    {
        $formId = (int) $this->input->getInt('id');
        if (!$this->persistInlineElementSettings($formId)) {
            return;
        }

        $model = $this->getElementsModelForListActions();
        $model->move(-1); // ou utilise reorder si tu préfères
        $this->setRedirect($this->getEditRedirectUrl($formId));
    }

    public function listorderdown(): void
    {
        $formId = (int) $this->input->getInt('id');
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

        $formId = (int) $this->input->getInt('id');
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
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'warning');
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
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'warning');
        }
    }


    // Les tâches batch sur les éléments (linkable, editable, etc.)
    public function linkable(): void
    {
        $this->elementsUpdate('linkable', 1);
    }

    public function not_linkable(): void
    {
        $this->elementsUpdate('linkable', 0);
    }

    public function editable(): void
    {
        $this->elementsUpdate('editable', 1);
    }

    public function not_editable(): void
    {
        $this->elementsUpdate('editable', 0);
    }

    public function list_include(): void
    {
        $this->elementsUpdate('list_include', 1);
    }

    public function no_list_include(): void
    {
        $this->elementsUpdate('list_include', 0);
    }

    public function search_include(): void
    {
        $this->elementsUpdate('search_include', 1);
    }

    public function no_search_include(): void
    {
        $this->elementsUpdate('search_include', 0);
    }

    public function save_labels(): bool
    {
        $this->checkToken();

        $formId = (int) $this->input->getInt('id');

        if (!$this->persistInlineElementSettings($formId)) {
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));
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

    public function listpublish(): bool
    {
        return $this->elementsPublish(1, 'COM_CONTENTBUILDERNG_PUBLISHED');
    }

    public function listunpublish(): bool
    {
        return $this->elementsPublish(0, 'COM_CONTENTBUILDERNG_UNPUBLISHED');
    }

    public function publish(): bool
    {
        return $this->elementsPublish(1, 'COM_CONTENTBUILDERNG_PUBLISHED');
    }

    public function unpublish(): bool
    {
        return $this->elementsPublish(0, 'COM_CONTENTBUILDERNG_UNPUBLISHED');
    }

    public function formpublish(): bool
    {
        return $this->setFormPublishedState(1, 'COM_CONTENTBUILDERNG_PUBLISHED');
    }

    public function formunpublish(): bool
    {
        return $this->setFormPublishedState(0, 'COM_CONTENTBUILDERNG_UNPUBLISHED');
    }


    // Devrait migrer dans Element*Controller ?
    private function elementsUpdate(string $field, int $value): bool
    {
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

            if (!$this->persistInlineElementSettings($formId)) {
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));
                }
                return false;
            }

            $model = $this->getElementoptionsModelForListActions();
            if (!$model->fieldUpdate($cids, $field, $value)) {
                $error = Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED');
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
                    $this->respondAjax(false, Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));
                }
                return false;
            }

            $model = $this->getElementoptionsModelForListActions();
            if (!$model->publish($cids, $state)) {
                $error = Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED');
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
                $error = Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED');
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

    private function getEditRedirectUrl(int $formId): string
    {
        return Route::_(
            'index.php?option=com_contentbuilderng&task=form.display&layout=edit&id=' . max(0, $formId),
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
        Factory::getApplication()->close();
    }

    private function persistInlineElementSettings(int $formId): bool
    {
        if ($formId <= 0) {
            return true;
        }

        $formModel = $this->getFormModelForSaveActions();

        if (!$formModel->saveElementListSettingsFromRequest($formId)) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'), 'error');
            if (!$this->isAjaxCall()) {
                $this->setRedirect($this->getEditRedirectUrl($formId));
            }
            return false;
        }

        return true;
    }
}
