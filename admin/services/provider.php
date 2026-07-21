<?php

/**
 * @package     Extension
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
// administrator/services/provider.php

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Extension\ContentbuilderngComponent;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use CB\Component\Contentbuilderng\Administrator\Service\DatatableService;
use CB\Component\Contentbuilderng\Administrator\Service\StorageFieldService;
use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateSampleService;
use CB\Component\Contentbuilderng\Administrator\Service\DirectStorageFormProvisioningService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Service\ArticleService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\MenuService;
use CB\Component\Contentbuilderng\Administrator\Service\FormResolverService;
use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateRenderService;
use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\ApiFieldPermissionService;

return new class implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $namespace = '\\CB\\Component\\Contentbuilderng';

        $container->registerServiceProvider(new MVCFactory($namespace));
        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));
        $container->registerServiceProvider(new RouterFactory($namespace));

        // Joomla's global container has no CMSApplicationInterface key (see the
        // note in libraries/src/Service/Provider/Session.php): the running
        // application only exists on the global Factory. Bind it here so every
        // service factory below and the component's models can resolve it.
        $container->set(
            CMSApplicationInterface::class,
            static fn() => Factory::getApplication()
        );

        $container->set(
            DatatableService::class,
            static fn(Container $c) => new DatatableService($c->get(DatabaseInterface::class))
        );
        $container->set(
            StorageFieldService::class,
            static fn(Container $c) => new StorageFieldService($c->get(DatabaseInterface::class))
        );
        $container->set(
            PathService::class,
            static fn(Container $c) => new PathService()
        );
        $container->set(
            TemplateSampleService::class,
            static fn(Container $c) => new TemplateSampleService(
                $c->get(CMSApplicationInterface::class),
                $c->get(DatabaseInterface::class)
            )
        );
        $container->set(
            FormSupportService::class,
            static fn(Container $c) => new FormSupportService(
                $c->get(PathService::class),
                $c->get(DatabaseInterface::class),
                $c->get(TemplateSampleService::class)
            )
        );
        $container->set(
            DirectStorageFormProvisioningService::class,
            static fn(Container $c) => new DirectStorageFormProvisioningService(
                $c->get(DatabaseInterface::class),
                $c->get(FormSupportService::class)
            )
        );
        $container->set(
            PermissionService::class,
            static fn(Container $c) => new PermissionService(
                $c->get(CMSApplicationInterface::class),
                $c->get(DatabaseInterface::class),
                $c->get(FormResolverService::class)
            )
        );
        $container->set(
            ArticleService::class,
            static fn(Container $c) => new ArticleService(
                $c->get(CMSApplicationInterface::class),
                $c->get(TemplateRenderService::class),
                $c->get(FormResolverService::class),
                $c->get(DatabaseInterface::class),
                $c->get(CacheControllerFactoryInterface::class)
            )
        );
        $container->set(
            ListSupportService::class,
            static fn(Container $c) => new ListSupportService($c->get(DatabaseInterface::class))
        );
        $container->set(
            RuntimeUtilityService::class,
            static fn(Container $c) => new RuntimeUtilityService($c->get(CMSApplicationInterface::class))
        );
        $container->set(
            MenuService::class,
            static fn(Container $c) => new MenuService($c->get(DatabaseInterface::class))
        );
        $container->set(
            FormResolverService::class,
            static fn(Container $c) => new FormResolverService($c->get(CMSApplicationInterface::class))
        );
        $container->set(
            ApiFieldPermissionService::class,
            static fn(Container $c) => new ApiFieldPermissionService($c->get(DatabaseInterface::class))
        );
        $container->set(
            TextUtilityService::class,
            static fn(Container $c) => new TextUtilityService()
        );
        $container->set(
            TemplateRenderService::class,
            static fn(Container $c) => new TemplateRenderService(
                $c->get(CMSApplicationInterface::class),
                $c->get(DatabaseInterface::class),
                $c->get(FormResolverService::class),
                $c->get(FormSupportService::class),
                $c->get(RuntimeUtilityService::class),
                $c->get(TextUtilityService::class)
            )
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
                $component->setRouterFactory(
                    $container->get(RouterFactoryInterface::class)
                );

                return $component;
            }
        );
    }
};
