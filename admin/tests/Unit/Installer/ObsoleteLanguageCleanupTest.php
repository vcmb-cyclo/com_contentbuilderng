<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

final class ObsoleteLanguageCleanupTest extends TestCase
{
    public function testPostflightRemovesObsoleteLanguageFilesAfterLegacyPluginLanguageFiles(): void
    {
        $path = \dirname(__DIR__, 4) . '/script.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);

        $postflightPosition = strpos($source, 'public function postflight(');
        $pluginLanguagePosition = strpos($source, '$this->removeLegacyPluginLanguageFiles();');
        $obsoleteLanguagePosition = strpos($source, '$this->removeObsoleteLanguageFiles();');

        self::assertIsInt($postflightPosition);
        self::assertIsInt($pluginLanguagePosition);
        self::assertIsInt($obsoleteLanguagePosition, 'postflight() must call removeObsoleteLanguageFiles().');
        self::assertLessThan($pluginLanguagePosition, $postflightPosition);
        self::assertLessThan($obsoleteLanguagePosition, $pluginLanguagePosition);
        self::assertStringContainsString('$this->installerService->removeObsoleteLanguageFiles();', $source);
    }
}
