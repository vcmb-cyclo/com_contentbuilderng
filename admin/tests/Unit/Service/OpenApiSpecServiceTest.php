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
        self::assertSame('https://example.test/index.php', $spec['servers'][0]['url']);
        self::assertArrayHasKey('SuccessEnvelope', $spec['components']['schemas']);
        self::assertArrayHasKey('ErrorResponse', $spec['components']['responses']);
        self::assertArrayHasKey('/index.php?task=api.display (list / detail)', $spec['paths']);
        self::assertArrayHasKey('/index.php?task=api.display&action=stats', $spec['paths']);
        self::assertArrayHasKey('/index.php?task=api.display&action=cbstats', $spec['paths']);
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
}
