<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

final class ControllerApplicationTypeTest extends TestCase
{
    public function testStoragesControllerImportsItsApplicationType(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 4) . '/admin/src/Controller/StoragesController.php'
        );

        self::assertIsString($source);
        self::assertStringContainsString(
            'use Joomla\CMS\Application\CMSWebApplicationInterface;',
            $source
        );
        self::assertStringContainsString(
            '?CMSWebApplicationInterface $app = null',
            $source
        );
        self::assertStringNotContainsString(
            'use Joomla\CMS\Application\CMSApplicationInterface;',
            $source
        );
    }
}
