<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

final class InstallerEnvironmentLogOrderTest extends TestCase
{
    public function testInstalledVersionIsLoggedBeforeRuntimeVersions(): void
    {
        $path = \dirname(__DIR__, 4) . '/script.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);

        $installedVersionPosition = strpos($source, 'Detected installed version:');
        $joomlaVersionPosition = strpos($source, 'Joomla version:');
        $phpVersionPosition = strpos($source, 'PHP version:');
        $databasePosition = strpos($source, '$this->logDatabaseRuntimeInfo();');
        $userAgentPosition = strpos($source, 'User agent:');

        self::assertIsInt($installedVersionPosition);
        self::assertIsInt($joomlaVersionPosition);
        self::assertIsInt($phpVersionPosition);
        self::assertIsInt($databasePosition);
        self::assertIsInt($userAgentPosition);
        self::assertLessThan($joomlaVersionPosition, $installedVersionPosition);
        self::assertLessThan($phpVersionPosition, $joomlaVersionPosition);
        self::assertLessThan($databasePosition, $phpVersionPosition);
        self::assertLessThan($userAgentPosition, $databasePosition);
    }
}
