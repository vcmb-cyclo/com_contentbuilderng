<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\OpenApiSpecService;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/OpenApiSpecService.php';

final class OpenApiSpecServiceTest extends TestCase
{
    public function testBuildsCompleteSpecification(): void
    {
        $spec = (new OpenApiSpecService())->build(' 6.2.0 ');

        self::assertSame('3.0.3', $spec['openapi']);
        self::assertSame('6.2.0', $spec['info']['version']);
        self::assertSame('https://example.test', $spec['servers'][0]['url']);
        self::assertArrayHasKey('SuccessEnvelope', $spec['components']['schemas']);
        self::assertArrayHasKey('Error', $spec['components']['responses']);
        self::assertArrayHasKey('joomlaSession', $spec['components']['securitySchemes']);
        self::assertArrayHasKey('/index.php', $spec['paths']);
        self::assertSame(['joomlaSession' => []], $spec['paths']['/index.php']['patch']['security'][0]);
    }

    public function testUsesFallbackVersionForBlankInput(): void
    {
        self::assertSame('0.0.0', (new OpenApiSpecService())->build('  ')['info']['version']);
    }

    public function testEveryOperationDefinesResponses(): void
    {
        $paths = (new OpenApiSpecService())->build('1.0.0')['paths'];

        foreach ($paths as $path) {
            foreach ($path as $operation) {
                self::assertArrayHasKey('responses', $operation);
            }
        }
    }

    public function testUsesOnlyOpenApiPathComponentsWithoutQueryStrings(): void
    {
        foreach (array_keys((new OpenApiSpecService())->build('1.0.0')['paths']) as $path) {
            self::assertStringStartsWith('/', $path);
            self::assertStringNotContainsString('?', $path);
        }
    }

    public function testDocumentsBoundedPaginationAndCsrfHeader(): void
    {
        $components = (new OpenApiSpecService())->build('1.0.0')['components'];

        self::assertSame(100, $components['parameters']['ListLimit']['schema']['maximum']);
        self::assertSame('X-CSRF-Token', $components['parameters']['CsrfToken']['name']);
    }
}
