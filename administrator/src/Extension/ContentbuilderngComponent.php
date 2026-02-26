<?php
/**
 * ContentBuilder NG initialisation.
 *
 * Component initialization.
 *
 * @package     Extension
 * @author      Xavier DANO
 * @copyright   Copyright (C) 2026 by XDA+GIL
 * @license     GNU/GPL v2 or later
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 compatibility rewrite.
 */

// administrator/src/Extension/ContentbuilderngComponent.php

namespace CB\Component\Contentbuilderng\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;
use Joomla\CMS\Factory;
use LogicException;


class ContentbuilderngComponent extends MVCComponent implements BootableExtensionInterface
{
    use HTMLRegistryAwareTrait;

    private ?ContainerInterface $container = null;

    public function boot(ContainerInterface $container): void
    {
        $this->container = $container;
        // Charge les langues du core (lib_joomla) pour avoir les clés JLIB_* traduites
        // Factory::getApplication()->getLanguage()->load('lib_joomla', JPATH_ADMINISTRATOR, null, true);

        // Et celles du composant (normalement déjà fait, mais safe)
        Factory::getApplication()->getLanguage()->load('com_contentbuilderng', JPATH_ADMINISTRATOR, null, true);
    }

    public function getContainer(): ContainerInterface
    {
        if ($this->container === null) {
            throw new LogicException('Component container has not been booted yet.');
        }

        return $this->container;
    }

}
