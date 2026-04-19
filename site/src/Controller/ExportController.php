<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Site\Controller;

// No direct access
\defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use CB\Component\Contentbuilderng\Site\Helper\PreviewLinkHelper;

class ExportController extends BaseController
{
    public function display($cachable = false, $urlparams = []): void
    {
        $formId = (int) $this->input->getInt('id', 0);
        $isAdminPreview = $this->isValidAdminPreviewRequest($formId);
        $this->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);
        Factory::getApplication()->input->set('cb_preview_ok', $isAdminPreview ? 1 : 0);

        $this->input->set('tmpl', $this->input->getWord('tmpl', null));
        $this->input->set('layout', $this->input->getWord('layout', null));
        $this->input->set('view', 'export');
        $this->input->set('format', 'raw');

        parent::display();
    }

    private function isValidAdminPreviewRequest(int $formId): bool
    {
        if ($formId < 1 || !$this->input->getBool('cb_preview', false)) {
            return false;
        }

        $until = (int) $this->input->getInt('cb_preview_until', 0);
        $sig = trim((string) $this->input->getString('cb_preview_sig', ''));
        $actorId = (int) $this->input->getInt('cb_preview_actor_id', 0);
        $actorName = trim((string) $this->input->getString('cb_preview_actor_name', ''));
        $userId = (int) $this->input->getInt('cb_preview_user_id', 0);
        if ($userId < 1) {
            return false;
        }

        if ($until < time() || $sig === '') {
            return false;
        }

        $secret = (string) Factory::getApplication()->get('secret');
        if ($secret === '') {
            return false;
        }

        $payload = PreviewLinkHelper::buildPayload((string) $formId, $until, $actorId, $actorName, $userId);

        if (hash_equals(hash_hmac('sha256', $payload, $secret), $sig)) {
            $this->input->set('cb_preview_actor_id', $actorId);
            $this->input->set('cb_preview_actor_name', $actorName);
            Factory::getApplication()->input->set('cb_preview_actor_id', $actorId);
            Factory::getApplication()->input->set('cb_preview_actor_name', $actorName);
            return true;
        }

        return false;
    }
}
