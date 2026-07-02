<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Site\Model\Edit;

// No direct access
\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;

/**
 * Menu-toggle resolution, component boot, ownership resolution, and cache
 * cleanup helpers extracted from EditModel.
 */
trait OwnershipTrait
{
    private function getMenuToggle(string $key, int $default = 0): int
    {
        return MenuParamHelper::resolveInputOrMenuToggle($this->app, $key, $default);
    }

    private function getComponent(): ContentbuilderngComponent
    {
        $component = Factory::getApplication()->bootComponent('com_contentbuilderng');

        if (!$component instanceof ContentbuilderngComponent) {
            throw new \RuntimeException('Unexpected component instance');
        }

        return $component;
    }

    private function getEffectiveOwnershipUserId(bool $useOwnOnly): int
    {
        if (!$useOwnOnly) {
            return -1;
        }

        if ($this->app->getInput()->getBool('cb_preview_ok', false)) {
            $previewActorId = (int) $this->app->getInput()->getInt('cb_preview_actor_id', 0);

            if ($previewActorId > 0) {
                return $previewActorId;
            }
        }

        return (int) ($this->app->getIdentity()->id ?? 0);
    }

    private function cleanComponentCaches(): void
    {
        $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
        $cacheBase = (string) $this->app->get('cache_path', JPATH_SITE . '/cache');

        foreach (array('com_content', 'com_contentbuilderng') as $group) {
            $cacheFactory->createCacheController(
                'callback',
                array(
                    'defaultgroup' => $group,
                    'cachebase' => $cacheBase,
                )
            )->clean();
        }
    }
}
