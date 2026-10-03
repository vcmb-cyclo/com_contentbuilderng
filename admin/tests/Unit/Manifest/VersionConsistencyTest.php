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

    public function testPackageAndPublishedUpdateVersionsAreValid(): void
    {
        $installVersion = $this->readValue(
            $this->root . '/com_contentbuilderng.xml',
            '/extension/version'
        );
        $updateVersion = $this->readOptionalValue(
            $this->root . '/com_contentbuilderng_update.xml',
            '/updates/update/version'
        );

        self::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+(?:\.\d+)?(?:-[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*)?$/',
            $installVersion
        );
        if ($updateVersion !== '') {
            self::assertMatchesRegularExpression(
                '/^\d+\.\d+\.\d+(?:\.\d+)?(?:-[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*)?$/',
                $updateVersion
            );
            self::assertTrue(
                version_compare($updateVersion, $installVersion, '<='),
                'The Joomla update stream must not advertise a version newer than the package.'
            );
        }

        $assetManifest = json_decode(
            (string) file_get_contents($this->root . '/media/joomla.asset.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertSame($installVersion, $assetManifest['version'] ?? null);

        $expectedChangelogUrl = 'https://raw.githubusercontent.com/vcmb-cyclo/com_contentbuilderng/main/'
            . 'com_contentbuilderng_changelog.xml';

        self::assertSame(
            $expectedChangelogUrl,
            $this->readValue($this->root . '/com_contentbuilderng.xml', '/extension/changelogurl')
        );
        if ($updateVersion !== '') {
            $downloadUrl = $this->readValue(
                $this->root . '/com_contentbuilderng_update.xml',
                '/updates/update/downloads/downloadurl'
            );
            self::assertSame(
                'https://github.com/vcmb-cyclo/com_contentbuilderng/releases/download/v'
                    . $updateVersion
                    . '/com_contentbuilderng-'
                    . $updateVersion
                    . '.zip',
                $downloadUrl
            );
            self::assertSame(
                $expectedChangelogUrl,
                $this->readValue($this->root . '/com_contentbuilderng_update.xml', '/updates/update/changelogurl')
            );
        }
        self::assertSame(
            $installVersion,
            $this->readValue($this->root . '/com_contentbuilderng_changelog.xml', '/changelogs/changelog[1]/version')
        );
    }

    public function testInstallCreationDateIsValidAndNotInFuture(): void
    {
        $creationDate = $this->readValue(
            $this->root . '/com_contentbuilderng.xml',
            '/extension/creationDate'
        );
        $timezone = new DateTimeZone('Europe/Paris');
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $creationDate, $timezone);
        $today = new DateTimeImmutable('today', $timezone);

        self::assertInstanceOf(DateTimeImmutable::class, $parsedDate);
        self::assertSame($creationDate, $parsedDate->format('Y-m-d'));
        self::assertLessThanOrEqual(
            (int) $today->format('Ymd'),
            (int) $parsedDate->format('Ymd')
        );
    }

    private function readValue(string $path, string $expression): string
    {
        $document = new DOMDocument();
        self::assertTrue($document->load($path), 'Unable to load XML file: ' . $path);

        $value = (new DOMXPath($document))->evaluate('string(' . $expression . ')');
        self::assertNotSame('', $value, 'Missing XML value: ' . $expression);

        return $value;
    }

    private function readOptionalValue(string $path, string $expression): string
    {
        $document = new DOMDocument();
        self::assertTrue($document->load($path), 'Unable to load XML file: ' . $path);

        return (string) (new DOMXPath($document))->evaluate('string(' . $expression . ')');
    }
}
