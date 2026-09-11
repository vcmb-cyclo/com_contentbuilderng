<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

final class CanonicalComponentManifestTest extends TestCase
{
    public function testLatestDatabaseSchemaMarkerMatchesTheComponentVersion(): void
    {
        $root = \dirname(__DIR__, 4);
        $manifest = simplexml_load_file($root . '/com_contentbuilderng.xml');
        $schemaFiles = glob($root . '/admin/sql/updates/mysql/*.sql');

        self::assertInstanceOf(\SimpleXMLElement::class, $manifest);
        self::assertIsArray($schemaFiles);
        self::assertNotEmpty($schemaFiles);

        $versions = array_map(
            static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME),
            $schemaFiles
        );
        usort($versions, 'version_compare');

        self::assertSame((string) $manifest->version, end($versions));
    }

    public function testInstallerMaintainsTheManifestFilenameExpectedByJoomla(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 4) . '/script.php');

        self::assertStringContainsString('$this->ensureCanonicalComponentManifest();', $source);
        self::assertStringContainsString('$source = $componentPath . \'/com_contentbuilderng.xml\';', $source);
        self::assertStringContainsString('$target = $componentPath . \'/contentbuilderng.xml\';', $source);
        self::assertStringContainsString('File::copy($source, $target);', $source);
        self::assertStringContainsString(
            '$manifestPath = JPATH_ADMINISTRATOR . \'/components/com_contentbuilderng/contentbuilderng.xml\';',
            $source
        );
    }

    public function testSmokeTestRunsJoomlaDatabaseMaintenance(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 4) . '/scripts/joomla-install-smoke.sh');

        self::assertStringContainsString('joomla:6.1.3-php8.4-apache', $source);
        self::assertStringContainsString('maintenance:database', $source);
        self::assertStringContainsString('All database table structures are up to date.', $source);
        self::assertStringContainsString('Database schema version does not match the component manifest', $source);
        self::assertStringContainsString("grep -Fq 'Warning:'", $source);
    }
}
