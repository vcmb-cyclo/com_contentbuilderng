<?php

/**
 * ContentBuilder NG Storage controller.
 *
 * Handles actions for storage in the admin interface.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Controller
 * @author      Xavier DANO
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Controller;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController as BaseFormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Utilities\ArrayHelper;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use CB\Component\Contentbuilderng\Administrator\Model\StorageModel;
use CB\Component\Contentbuilderng\Administrator\Model\StoragefieldsModel;

class StorageController extends BaseFormController
{
    /**
     * Vue item et vue liste utilisées par les redirects du core
     */
    protected $view_list = 'storages';
    protected $view_item = 'storage';

    private function getApp()
    {
        return Factory::getApplication();
    }

    private function closeApp(): void
    {
        $this->getApp()->close();
    }

    private function externalStorageTableExists(string $tableName): bool
    {
        $tableName = trim($tableName);

        if ($tableName === '') {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tableList = array_map('strtolower', (array) $db->getTableList());
        $resolvedName = strtolower($db->replacePrefix($tableName));

        return in_array($resolvedName, $tableList, true);
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
            $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=storages.display', false));
            return false;
        }
    }

    /**
     * Surcharge save pour appeler les méthodes spécifiques du modèle (save/storeCsv).
     * Task: storage.save / storage.apply
     */
    public function save($key = null, $urlVar = null)
    {
        $this->checkToken();

        $file = $this->input->files->get('csv_file', null, 'array');

        // Ensure required fields exist when using bytable (name/title may be disabled in the form).
        $data = $this->input->post->get('jform', [], 'array');
        if (!empty($data['bytable'])) {
            if (empty($data['name'])) {
                $data['name'] = $data['bytable'];
            }
            if (empty($data['title'])) {
                $data['title'] = $data['bytable'];
            }
            $this->input->post->set('jform', $data);
        }

        // Pas de CSV → core
        if (!is_array($file) || empty($file['name']) || (int) ($file['size'] ?? 0) <= 0) {
            $isNew = empty($data['id']);
            /** @var \CB\Component\Contentbuilderng\Administrator\Model\StorageModel $model */
            $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);

            // Conserver le nom de la table data existante pour permettre un RENAME lors d'un changement de nom.
            $oldName = null;
            if (!$isNew && $model) {
                $existing = $model->getItem((int) $data['id']);
                if ($existing && (int) ($existing->bytable ?? 0) === 0) {
                    $oldName = (string) ($existing->name ?? '');
                }
            }

            $result = parent::save($key, $urlVar);
            if ($result === false) {
                return false;
            }

            // Récupère l'id réellement sauvegardé (création: id n'est pas dans le POST initial).
            $id = 0;
            if ($model) {
                $id = (int) $model->getState($model->getName() . '.id', 0);
            }
            if (!$id) {
                $jform = $this->input->post->get('jform', [], 'array');
                $id = (int) ($jform['id'] ?? 0);
            }
            if (!$id) {
                $id = (int) $this->input->getInt('id');
            }
            if (!$id) {
                try {
                    $redirect = (string) ($this->redirect ?? '');
                    $query = parse_url($redirect, PHP_URL_QUERY);
                    if (is_string($query) && $query !== '') {
                        parse_str($query, $queryVars);
                        $id = (int) ($queryVars['id'] ?? 0);
                    }
                } catch (\Throwable $e) {
                    Logger::exception($e);
                }
            }
            if (!$id) {
                try {
                    $lookupName = '';
                    $lookupBytable = null;

                    if (!empty($data['bytable'])) {
                        $lookupName = (string) $data['bytable'];
                        $lookupBytable = 1;
                    } elseif (!empty($data['name'])) {
                        $lookupName = (string) $data['name'];
                        $lookupBytable = 0;
                    }

                    if ($lookupName !== '') {
                        $db = Factory::getContainer()->get(DatabaseInterface::class);
                        $query = $db->getQuery(true)
                            ->select($db->quoteName('id'))
                            ->from($db->quoteName('#__contentbuilderng_storages'))
                            ->where($db->quoteName('name') . ' = ' . $db->quote($lookupName));

                        if ($lookupBytable !== null) {
                            $query->where($db->quoteName('bytable') . ' = ' . (int) $lookupBytable);
                        }

                        $query->order($db->quoteName('id') . ' DESC');
                        $db->setQuery($query, 0, 1);
                        $id = (int) $db->loadResult();
                    }
                } catch (\Throwable $e) {
                    Logger::exception($e);
                }
            }

            if ($id && $model) {
                $model->ensureDataTable($id, $isNew, $oldName);
                $model->syncEditedFieldsFromRequest($id);

                $renameInfo = $model->getLastDataTableRename();
                if (is_array($renameInfo) && !empty($renameInfo['from']) && !empty($renameInfo['to'])) {
                    $renameMessage = Text::sprintf(
                        'COM_CONTENTBUILDERNG_STORAGE_TABLE_RENAMED',
                        '#__' . (string) $renameInfo['from'],
                        '#__' . (string) $renameInfo['to']
                    );

                    $currentMessage = trim((string) ($this->message ?? ''));
                    $this->setMessage(
                        $currentMessage !== '' ? ($currentMessage . ' ' . $renameMessage) : $renameMessage
                    );
                }
            }

            if (!empty($data['bytable'])) {
                $externalTableName = trim((string) ($data['name'] ?? ''));

                if ($externalTableName !== '') {
                    try {
                        if (!$this->externalStorageTableExists($externalTableName)) {
                            $db = Factory::getContainer()->get(DatabaseInterface::class);
                            $missingTableMessage = Text::sprintf(
                                'COM_CONTENTBUILDERNG_STORAGE_SAVE_EXTERNAL_TABLE_MISSING',
                                $db->replacePrefix($externalTableName)
                            );
                            $this->getApp()->enqueueMessage($missingTableMessage, 'warning');
                        }
                    } catch (\Throwable $e) {
                        Logger::exception($e);
                    }
                }
            }

            return $result;
        }

        // File import (CSV/Excel) → 1) core save storage, 2) import
        $file['name'] = File::makeSafe($file['name']);

        try {
            // (A) Sauver l'item via le core (ça gère jform + table + hooks)
            $data  = $this->input->post->get('jform', [], 'array');
            /** @var StorageModel $model */
            $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);
            if (!$model) {
                throw new \RuntimeException('StorageModel introuvable');
            }

            Logger::info('Controller got model class', ['class' => get_class($model)]);
            $saved = $model->save($data);
            if (!$saved) {
                $this->setRedirect(
                    Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . (int) ($data['id'] ?? 0), false),
                    Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'),
                    'error'
                );
                return false;
            }

            // IMPORTANT: AdminModel::save() returns bool, not the saved id.
            // Resolve the effective storage id to avoid importing into an unrelated row (e.g. id=1).
            $id = (int) $model->getState($model->getName() . '.id', 0);
            if (!$id) {
                $id = (int) ($data['id'] ?? 0);
            }
            if (!$id) {
                $id = (int) $this->input->getInt('id');
            }
            if (!$id) {
                try {
                    $lookupName = '';
                    $lookupBytable = null;

                    if (!empty($data['bytable'])) {
                        $lookupName = (string) $data['bytable'];
                        $lookupBytable = 1;
                    } elseif (!empty($data['name'])) {
                        $lookupName = (string) $data['name'];
                        $lookupBytable = 0;
                    }

                    if ($lookupName !== '') {
                        $db = Factory::getContainer()->get(DatabaseInterface::class);
                        $query = $db->getQuery(true)
                            ->select($db->quoteName('id'))
                            ->from($db->quoteName('#__contentbuilderng_storages'))
                            ->where($db->quoteName('name') . ' = ' . $db->quote($lookupName));

                        if ($lookupBytable !== null) {
                            $query->where($db->quoteName('bytable') . ' = ' . (int) $lookupBytable);
                        }

                        $query->order($db->quoteName('id') . ' DESC');
                        $db->setQuery($query, 0, 1);
                        $id = (int) $db->loadResult();
                    }
                } catch (\Throwable $e) {
                    Logger::exception($e);
                }
            }

            if (!$id) {
                $this->setRedirect(
                    Route::_('index.php?option=com_contentbuilderng&task=storages.display', false),
                    'Save succeeded but storage id could not be resolved',
                    'error'
                );
                return false;
            }

            // (B) Import file (CSV/Excel)
            $ok = $model->storeCsv($file, (int) $id);
            if (!$ok) {
                $error = trim((string) $model->getError());
                if ($error === '') {
                    $error = Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED');
                }
                $this->setRedirect(
                    Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . (int) $id, false),
                    $error,
                    'error'
                );
                return false;
            }

            $importSummary = $model->getLastImportSummary();
            if (!empty($importSummary)) {
                $summaryParts = [];
                $summaryParts[] = Text::sprintf(
                    'COM_CONTENTBUILDERNG_STORAGE_IMPORT_SUMMARY',
                    (int) ($importSummary['rows_imported'] ?? 0),
                    (int) ($importSummary['rows_read'] ?? 0),
                    (int) ($importSummary['columns'] ?? 0),
                    (string) ($importSummary['file_format'] ?? 'CSV')
                );

                if (!empty($importSummary['drop_records'])) {
                    $summaryParts[] = Text::sprintf(
                        'COM_CONTENTBUILDERNG_STORAGE_IMPORT_SUMMARY_DROPPED',
                        (int) ($importSummary['dropped_data_records'] ?? 0),
                        (int) ($importSummary['dropped_meta_records'] ?? 0),
                        (int) ($importSummary['dropped_article_links'] ?? 0)
                    );
                }

                if (!empty($importSummary['rows_skipped_empty'])) {
                    $summaryParts[] = Text::sprintf(
                        'COM_CONTENTBUILDERNG_STORAGE_IMPORT_SUMMARY_SKIPPED_EMPTY',
                        (int) $importSummary['rows_skipped_empty']
                    );
                }

                if (!empty($importSummary['duration_ms'])) {
                    $summaryParts[] = Text::sprintf(
                        'COM_CONTENTBUILDERNG_STORAGE_IMPORT_SUMMARY_DURATION',
                        (int) $importSummary['duration_ms']
                    );
                }

                $this->setMessage(implode(' ', $summaryParts));
            }
        } catch (\Throwable $e) {
            Logger::exception($e);
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=storages.display', false),
                $e->getMessage(),
                'error'
            );
            return false;
        }

        // Redirect apply/save
        $task = $this->getTask();
        $link = ($task === 'apply')
            ? Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . (int) $id, false)
            : Route::_('index.php?option=com_contentbuilderng&task=storages.display', false);

        $message = trim((string) ($this->message ?? ''));
        if ($message === '') {
            $message = Text::_('COM_CONTENTBUILDERNG_SAVED');
        }
        $this->setRedirect($link, $message);
        return true;
    }

    /**
     * Force apply task through this controller custom save flow.
     */
    public function apply($key = null, $urlVar = null)
    {
        return $this->save($key, $urlVar);
    }

    /**
     * Ajax: preview CSV/XLSX headers to create storage fields.
     */
    public function previewHeaders(): void
    {
        $this->checkToken('post');

        $file = $this->input->files->get('csv_file', null, 'array');
        $delimiter = $this->input->post->getString('csv_delimiter', ',');
        $repairEncoding = $this->input->post->getString('csv_repair_encoding', '');

        /** @var StorageModel $model */
        $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);
        $headers = [];

        if ($model && is_array($file) && !empty($file['name'])) {
            $headers = $model->extractHeaderColumnsFromUpload($file, $delimiter, $repairEncoding);
        }

        echo new JsonResponse($headers);
        $this->closeApp();
    }


    public function addfield(): bool
    {
        $this->checkToken();

        $jform = $this->input->post->get('jform', [], 'array');
        $storageId = (int) ($jform['id'] ?? $this->input->getInt('id'));

        /** @var \CB\Component\Contentbuilderng\Administrator\Model\StorageModel $model */
        $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);

        if (!$model) {
            throw new \RuntimeException('StorageModel not found');
        }

        $ok = $model->addFieldFromRequest($storageId);

        $msg = $ok
            ? Text::_('COM_CONTENTBUILDERNG_FIELD_ADDED')
            : Text::_('COM_CONTENTBUILDERNG_FIELD_ADD_FAILED');

        $type = $ok ? 'message' : 'warning';

        // Redirect vers l’édition du storage
        $this->setRedirect(
            Route::_('index.php?option=com_contentbuilderng&task=storage.edit&id=' . (int) $storageId, false),
            $msg,
            $type
        );

        return $ok;
    }


    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
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
     * Task: storage.delete (au lieu de remove)
     * Joomla va passer cid[] dans l’input.
     */
    public function delete()
    {
        $this->checkToken();

        $input = $this->getApp()->getInput();

        $cid = $input->get('cid', [], 'array');
        ArrayHelper::toInteger($cid);

        if (!$cid) {
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=storages.display', false),
                Text::_('JERROR_NO_ITEMS_SELECTED'),
                'warning'
            );
            return false;
        }

        /** @var \CB\Component\Contentbuilderng\Administrator\Model\StorageModel $model */
        $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true])
            ?: $this->getModel('Storage', 'Contentbuilderng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('StorageModel not found');
        }

        // The model delete() consumes selected primary keys from the request payload.
        try {
            $ok = $model->delete($cid);
            if (!$ok) {
                $this->setRedirect(
                    Route::_('index.php?option=com_contentbuilderng&task=storages.display', false),
                    Text::_('COM_CONTENTBUILDERNG_ERROR'),
                    'error'
                );
                return false;
            }
        } catch (\Throwable $e) {
            Logger::exception($e);
            $this->setMessage($e->getMessage(), 'warning');
            return false;
        }

        $this->setRedirect(
            Route::_('index.php?option=com_contentbuilderng&task=storages.display', false),
            Text::_('COM_CONTENTBUILDERNG_DELETED'),
            'message'
        );

        return $ok;
    }

    public function save2new()
    {
        /** @var StorageModel|null $model */
        $model = $this->getModel('Storage', 'Administrator', ['ignore_request' => true])
            ?: $this->getModel('Storage', 'Contentbuilderng', ['ignore_request' => true]);
        if (!$model) {
            throw new \RuntimeException('StorageModel not found');
        }
        $model->save((array) $this->input->post->get('jform', [], 'array'));

        $this->setRedirect('index.php?option=com_contentbuilderng&task=storage.display&layout=edit&id=0');
        return true;
    }


    public function add()
    {
        $this->setRedirect('index.php?option=com_contentbuilderng&task=storage.display&layout=edit&id=0');
        return true;
    }


    public function publish(): bool
    {
        return $this->storagesPublish(1, 'COM_CONTENTBUILDERNG_PUBLISHED');
    }

    public function unpublish(): bool
    {
        return $this->storagesPublish(0, 'COM_CONTENTBUILDERNG_UNPUBLISHED');
    }

    public function publishItem(): bool
    {
        return $this->setStorageItemPublished(1, 'COM_CONTENTBUILDERNG_PUBLISHED');
    }

    public function unpublishItem(): bool
    {
        return $this->setStorageItemPublished(0, 'COM_CONTENTBUILDERNG_UNPUBLISHED');
    }

    /* 
    public function publish()
    {
        $app = Factory::getApplication();
        $input = $app->getInput();
        $cid = $input->get('cid', [], 'array');
        ArrayHelper::toInteger($cid);

        if (count($cid) == 1) {
            $model = $this->getModel('Storage', 'Contentbuilderng');
            $model->setPublished();
        } else if (count($cid) > 1) {
            $model = $this->getModel('Storage', 'Contentbuilderng');
            $model->setPublished();
        }

        $this->setRedirect(
            Route::_('index.php?option=com_contentbuilderng&task=storage.display&limitstart=' . $this->input->getInt('limitstart'), false),
            Text::_('COM_CONTENTBUILDERNG_PUBLISHED'));
    }

    public function unpublish()
    {
        $app = Factory::getApplication();
        $input = $app->getInput();
        $cid = $input->get('cid', [], 'array');
        ArrayHelper::toInteger($cid);

        if (count($cid) == 1) {
            $model = $this->getModel('Storage', 'Contentbuilderng');
            $model->setUnpublished();
        } else if (count($cid) > 1) {
            $model = $this->getModel('Storage', 'Contentbuilderng');
            $model->setUnpublished();
        }

        $this->setRedirect(
            Route::_('index.php?option=com_contentbuilderng&task=storage.display&limitstart=' . $this->input->getInt('limitstart'), false),
            Text::_('COM_CONTENTBUILDERNG_UNPUBLISHED'));
    }
*/

    // Passe par le modèle.
    private function storagesPublish(int $state, string $successMsgKey)
    {
        try {
            $cids = $this->input->get('cid', [], 'array');
            ArrayHelper::toInteger($cids);

            $storageId = (int) $this->input->getInt('id');

            if (empty($cids)) {
                $error = Text::_('JERROR_NO_ITEMS_SELECTED');
                $this->setMessage($error, 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, $error);
                } else {
                    $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=storage.display' . '&id=' . $storageId, false));
                }
                return false;
            }

            if ($storageId > 0) {
                /** @var StorageModel|null $storageModel */
                $storageModel = $this->getModel('Storage', 'Administrator', ['ignore_request' => true]);
                if ($storageModel && method_exists($storageModel, 'syncEditedFieldsFromRequest')) {
                    $storageModel->syncEditedFieldsFromRequest($storageId);
                }
            }

            /** @var StoragefieldsModel|null $model */
            $model = $this->getModel('Storagefields', 'Administrator', ['ignore_request' => true]);
            if (!$model) {
                throw new \RuntimeException('StoragefieldsModel introuvable');
            }
            $model->setStorageId($storageId);
            if (!$model->publish($cids, $state)) {
                $error = Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED');
                $this->setMessage($error, 'error');
                if ($this->isAjaxCall()) {
                    $this->respondAjax(false, $error);
                } else {
                    $this->setRedirect(
                        Route::_('index.php?option=com_contentbuilderng&task=storage.display&layout=edit&id=' . $storageId, false),
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
                Route::_('index.php?option=com_contentbuilderng&task=storage.display&layout=edit&id=' . $storageId, false),
                Text::_($successMsgKey)
            );

            return true;
        } catch (\Throwable $e) {
            $this->setMessage($e->getMessage(), 'warning');
            if ($this->isAjaxCall()) {
                $this->respondAjax(false, $e->getMessage());
            } else {
                $this->setRedirect(Route::_('index.php?option=com_contentbuilderng&task=storage.display', false));
            }
            return false;
        }
    }

    private function setStorageItemPublished(int $state, string $successMsgKey): bool
    {
        $this->checkToken();

        try {
            $storageId = (int) $this->input->getInt('id', 0);

            if ($storageId <= 0) {
                $jform = $this->input->post->get('jform', [], 'array');
                $storageId = (int) ($jform['id'] ?? 0);
            }

            if ($storageId <= 0) {
                $cids = (array) $this->input->get('cid', [], 'array');
                ArrayHelper::toInteger($cids);
                $storageId = (int) ($cids[0] ?? 0);
            }

            if ($storageId <= 0) {
                throw new \RuntimeException(Text::_('JERROR_NO_ITEMS_SELECTED'));
            }

            if (!$this->getApp()->getIdentity()->authorise('core.edit.state', 'com_contentbuilderng')) {
                throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__contentbuilderng_storages'))
                ->set($db->quoteName('published') . ' = ' . (int) $state)
                ->where($db->quoteName('id') . ' = ' . (int) $storageId);
            $db->setQuery($query);
            $db->execute();

            $tabStartOffset = trim((string) $this->input->getString('tabStartOffset', 'tab1'));
            if ($tabStartOffset === '') {
                $tabStartOffset = 'tab1';
            }
            $this->getApp()->getSession()->set('tabStartOffset', $tabStartOffset, 'com_contentbuilderng');

            $this->setRedirect(
                Route::_(
                    'index.php?option=com_contentbuilderng&task=storage.edit&id='
                    . $storageId
                    . '&tabStartOffset=' . rawurlencode($tabStartOffset)
                    . '#' . rawurlencode($tabStartOffset),
                    false
                ),
                Text::_($successMsgKey),
                'message'
            );

            return true;
        } catch (\Throwable $e) {
            $this->setRedirect(
                Route::_('index.php?option=com_contentbuilderng&task=storage.display&layout=edit&id=' . (int) $this->input->getInt('id', 0), false),
                $e->getMessage(),
                'error'
            );

            return false;
        }
    }

    private function isAjaxCall(): bool
    {
        return (bool) $this->input->getInt('cb_ajax', 0);
    }

    private function respondAjax(bool $success, string $message = ''): void
    {
        echo new JsonResponse(['ok' => $success], $message, !$success);
        $this->closeApp();
    }
}
