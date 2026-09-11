<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

final class PluginInstallerServiceTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = \dirname(__DIR__, 4)
            . '/admin/src/Service/PluginInstallerService.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testAlreadyEnabledPluginsAreLoggedOnce(): void
    {
        self::assertStringContainsString(
            '$alreadyEnabledPlugins = [];',
            $this->source
        );
        self::assertStringContainsString(
            '$alreadyEnabledPlugins[] = "{$folder}/{$element}";',
            $this->source
        );
        self::assertStringContainsString(
            "\$this->log('[OK] Plugins already enabled: ' . implode(', ', \$alreadyEnabledPlugins));",
            $this->source
        );
        self::assertStringNotContainsString(
            '$wasEnabled ? "[OK] Plugin already enabled:',
            $this->source
        );
    }

    public function testRetiredPingPluginIsRemovedWithoutRunningItsUninstallHooks(): void
    {
        self::assertStringContainsString(
            "\$element = 'contentbuilderng_ping';",
            $this->source
        );
        self::assertStringContainsString(
            'public function removeRetiredPlugins(): void',
            $this->source
        );
        self::assertStringContainsString(
            "JPATH_PLUGINS . '/' . \$folder . '/' . \$element",
            $this->source
        );
        self::assertStringNotContainsString(
            "uninstall('plugin', \$extensionId",
            $this->source
        );

        $installerSource = file_get_contents(\dirname(__DIR__, 4) . '/script.php');
        self::assertIsString($installerSource);
        self::assertStringContainsString('$this->removeRetiredPlugins();', $installerSource);
    }
}
