<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Contract;

\defined('_JEXEC') or die;

interface FormElementAfterValidationInterface
{
    public function onSaveRecord(int|string $recordId): void;

    public function onSaveArticle(int|string $articleId): void;
}
