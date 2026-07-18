<?php

/**
 * ContentBuilder NG initialisation.
 *
 * Component initialization.
 *
 * @package     Extension
 * @author      Xavier DANO
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// administrator/src/Extension/ContentbuilderngComponent.php

namespace CB\Component\Contentbuilderng\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Psr\Container\ContainerInterface;
use LogicException;
use CB\Component\Contentbuilderng\Administrator\Helper\RuntimeContextHelper;


class ContentbuilderngComponent extends MVCComponent implements BootableExtensionInterface, RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;

    private ?ContainerInterface $container = null;

    #[\Override]
    public function boot(ContainerInterface $container): void
    {
        $this->container = $container;
        // Note: $container here is the component's own service container (built by
        // services/provider.php), not the application container — it never has
        // CMSApplicationInterface bound. The running application and its database
        // connection must come from the global Factory instead.
        $app = Factory::getApplication();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        RuntimeContextHelper::initialize($app, $db);

        // Charge les langues du core (lib_joomla) pour avoir les clés JLIB_*/J* traduites.
        $app->getLanguage()->load('lib_joomla', JPATH_ADMINISTRATOR, null, true);

        $app->getLanguage()->load(
            'com_contentbuilderng',
            JPATH_ADMINISTRATOR . '/components/com_contentbuilderng',
            null,
            true
        );
    }

    public function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            throw new LogicException('Component container has not been booted yet.');
        }

        return $this->container;
    }

}
