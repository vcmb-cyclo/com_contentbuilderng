<?php

/**
 * @package     Extension
 * @author      Xavier DANO
 * @link        
 * @copyright   Copyright (C) 2026 by XDA+GIL
 * @license     GNU/GPL
 */
// administrator/services/provider.php

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;
use CB\Component\Contentbuilderng\Administrator\Service\StorageFieldService;

//\Joomla\CMS\Factory::getApplication()->enqueueMessage('provider.php chargé', 'warning');
return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $namespace = '\\CB\\Component\\Contentbuilderng';

        $container->registerServiceProvider(new MVCFactory($namespace));
        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));

        // ✅ Enregistre les services ici
        $container->set(
            DatatableService::class,
            static fn(Container $c) => new DatatableService()
        );
        $container->set(
            StorageFieldService::class,
            static fn(Container $c) => new StorageFieldService()
        );      

        $container->set(
            ComponentInterface::class,
            function (Container $container): ComponentInterface {
                $component = new ContentbuilderngComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class)
                );

                $component->setMVCFactory(
                    $container->get(MVCFactoryInterface::class)
                );

                return $component;
            }
        );
    }
};
