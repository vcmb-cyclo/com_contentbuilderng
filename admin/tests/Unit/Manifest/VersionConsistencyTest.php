<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Manifest;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class VersionConsistencyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testInstallAndUpdateVersionsAreConsistent(): void
    {
        $installVersion = $this->readValue(
            $this->root . '/com_contentbuilderng.xml',
            '/extension/version'
        );
        $updateVersion = $this->readValue(
            $this->root . '/com_contentbuilderng_update.xml',
            '/updates/update/version'
        );

        self::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+(?:-[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*)?$/',
            $installVersion
        );
        self::assertSame($installVersion, $updateVersion);

        $downloadUrl = $this->readValue(
            $this->root . '/com_contentbuilderng_update.xml',
            '/updates/update/downloads/downloadurl'
        );

        self::assertSame(
            'https://github.com/vcmb-cyclo/com_contentbuilderng/releases/download/v'
                . $installVersion
                . '/com_contentbuilderng-'
                . $installVersion
                . '.zip',
            $downloadUrl
        );
    }

    public function testInstallCreationDateIsToday(): void
    {
        $creationDate = $this->readValue(
            $this->root . '/com_contentbuilderng.xml',
            '/extension/creationDate'
        );
        $today = new DateTimeImmutable('now', new DateTimeZone('Europe/Paris'));

        self::assertSame($today->format('Y-m-d'), $creationDate);
    }

    private function readValue(string $path, string $expression): string
    {
        $document = new DOMDocument();
        self::assertTrue($document->load($path), 'Unable to load XML file: ' . $path);

        $value = (new DOMXPath($document))->evaluate('string(' . $expression . ')');
        self::assertNotSame('', $value, 'Missing XML value: ' . $expression);

        return $value;
    }
}
