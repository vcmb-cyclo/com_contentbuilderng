<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use CB\Component\Contentbuilderng\Tests\Stubs\Application;
use CB\Component\Contentbuilderng\Tests\Stubs\Container;
use CB\Component\Contentbuilderng\Tests\Stubs\Database;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use PHPUnit\Framework\TestCase;

final class PermissionServiceTest extends TestCase
{
    private PermissionService $service;
    private Application $app;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(PermissionService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
        $this->app = new Application();
        $this->app->setIdentity(0, '', '');
        Factory::setContainer(new Container([
            \Joomla\Database\DatabaseInterface::class => new Database(),
        ]));
        Factory::setApplication($this->app);
        Access::$groupsByUser = [
            0 => [9],
        ];
    }

    public function testAuthorizeFeHonorsInheritedPublicGroupPermission(): void
    {
        $this->app->getSession()->set('com_contentbuilderng.permissions_fe', [
            'published' => true,
            1 => ['listaccess' => true],
        ]);

        self::assertTrue($this->service->authorizeFe('listaccess'));
    }

    public function testAuthorizeFeRejectsMissingInheritedPermission(): void
    {
        $this->app->getSession()->set('com_contentbuilderng.permissions_fe', [
            'published' => true,
            2 => ['listaccess' => true],
        ]);

        self::assertFalse($this->service->authorizeFe('listaccess'));
    }
}
