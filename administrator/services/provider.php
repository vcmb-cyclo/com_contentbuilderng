<?php

/**
 * @package     Extension
 * @author      Xavier DANO
 * @link        
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
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
use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use CB\Component\Contentbuilderng\Administrator\Service\FormSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateSampleService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Administrator\Service\ArticleService;
use CB\Component\Contentbuilderng\Administrator\Service\ListSupportService;
use CB\Component\Contentbuilderng\Administrator\Service\MenuService;
use CB\Component\Contentbuilderng\Administrator\Service\FormResolverService;
use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Administrator\Service\TemplateRenderService;
use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;

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
            PathService::class,
            static fn(Container $c) => new PathService()
        );
        $container->set(
            TemplateSampleService::class,
            static fn(Container $c) => new TemplateSampleService()
        );
        $container->set(
            FormSupportService::class,
            static fn(Container $c) => new FormSupportService($c->get(PathService::class))
        );
        $container->set(
            PermissionService::class,
            static fn(Container $c) => new PermissionService()
        );
        $container->set(
            ArticleService::class,
            static fn(Container $c) => new ArticleService()
        );
        $container->set(
            ListSupportService::class,
            static fn(Container $c) => new ListSupportService()
        );
        $container->set(
            RuntimeUtilityService::class,
            static fn(Container $c) => new RuntimeUtilityService()
        );
        $container->set(
            MenuService::class,
            static fn(Container $c) => new MenuService()
        );
        $container->set(
            FormResolverService::class,
            static fn(Container $c) => new FormResolverService()
        );
        $container->set(
            TextUtilityService::class,
            static fn(Container $c) => new TextUtilityService()
        );
        $container->set(
            TemplateRenderService::class,
            static fn(Container $c) => new TemplateRenderService()
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
