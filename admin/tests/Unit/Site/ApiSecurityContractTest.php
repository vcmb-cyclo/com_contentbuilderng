<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use PHPUnit\Framework\TestCase;

final class ApiSecurityContractTest extends TestCase
{
    private const CONTROLLER = __DIR__ . '/../../../../site/src/Controller/ApiController.php';

    public function testRequestLogDoesNotPersistRawRequestSecrets(): void
    {
        $source = (string) file_get_contents(self::CONTROLLER);

        self::assertStringNotContainsString("\$_SERVER['QUERY_STRING']", $source);
        self::assertStringNotContainsString("Logger::info('API request'", $source);
        self::assertStringContainsString("Logger::debug('API request'", $source);
        self::assertStringNotContainsString("\$_SERVER['REMOTE_ADDR']", substr(
            $source,
            strpos($source, "Logger::debug('API request'") ?: 0,
            500
        ));
    }

    public function testPrivateJsonHeadersAndLimitsAreEnforced(): void
    {
        $source = (string) file_get_contents(self::CONTROLLER);

        self::assertStringContainsString("'Cache-Control', 'private, no-store, max-age=0'", $source);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $source);
        self::assertStringContainsString('private const API_MAX_PAGE_SIZE = 100;', $source);
        self::assertStringContainsString("->get->set('list', \$list)", $source);
    }

    public function testWritesAcceptDocumentedCsrfHeaderAndJsonErrorsAreStrict(): void
    {
        $source = (string) file_get_contents(self::CONTROLLER);

        self::assertStringContainsString("\$_SERVER['HTTP_X_CSRF_TOKEN']", $source);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $source);
        self::assertStringNotContainsString("'navigation' => \$this->resolveSiblingRecordIds", $source);
    }
}
