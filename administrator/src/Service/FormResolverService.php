<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\FormSourceFactory;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;
use Joomla\CMS\Factory;

class FormResolverService
{
    public function getForm($type, $referenceId)
    {
        static $forms;

        Logger::info('Instanciation Legacy', [
            'type' => $type,
            'reference_id' => $referenceId,
        ]);

        $type = trim((string) $type);
        if ($type === '') {
            return null;
        }

        if (isset($forms[$type][$referenceId])) {
            return $forms[$type][$referenceId];
        }

        $app = Factory::getApplication();
        $isAdminPreview = $app->input->getBool('cb_preview_ok', false);
        $isAdministrator = $app->isClient('administrator');
        $allowUnpublishedSource = $isAdminPreview || $isAdministrator;

        $form = FormSourceFactory::getForm($type, $referenceId);

        if ((!$form || !($form->exists ?? false)) && $allowUnpublishedSource) {
            $namespace = 'CB\\Component\\Contentbuilderng\\Administrator\\types\\';
            $classCandidates = [$namespace . 'contentbuilderng_' . $type];
            if ($type === 'com_contentbuilderng') {
                $classCandidates[] = $namespace . 'contentbuilderng_com_contentbuilder';
            } elseif ($type === 'com_contentbuilder') {
                $classCandidates[] = $namespace . 'contentbuilderng_com_contentbuilderng';
            } else {
                $siteCandidate = JPATH_SITE . '/media/contentbuilderng/types/' . $type . '.php';
                if (file_exists($siteCandidate)) {
                    require_once $siteCandidate;
                }
                $classCandidates[] = 'contentbuilderng_' . $type;
            }

            foreach ($classCandidates as $class) {
                if (!class_exists($class)) {
                    continue;
                }
                try {
                    $previewForm = new $class($referenceId, false);
                    if (is_object($previewForm)) {
                        $form = $previewForm;
                        break;
                    }
                } catch (\ArgumentCountError|\TypeError $e) {
                } catch (\Throwable $e) {
                    Logger::exception($e);
                    throw $e;
                }
            }
        }

        if (!is_array($forms)) {
            $forms = [];
        }
        $forms[$type][$referenceId] = $form;

        return $form;
    }
}
