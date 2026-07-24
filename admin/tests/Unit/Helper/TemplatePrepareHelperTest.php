<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Administrator\Helper\TemplatePrepareHelper;
use CB\Component\Contentbuilderng\Tests\Stubs\Application;
use Joomla\CMS\Log\Log;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Helper/TemplatePrepareHelper.php';

final class TemplatePrepareHelperTest extends TestCase
{
    public function testEmptyCodeIsNotExecuted(): void
    {
        $executed = false;

        TemplatePrepareHelper::execute(
            new Application(),
            '',
            'prepare',
            static function () use (&$executed): void {
                $executed = true;
            }
        );

        self::assertFalse($executed);
    }

    public function testValidCodeIsPassedToExecutor(): void
    {
        $received = null;

        TemplatePrepareHelper::execute(
            new Application(),
            '$value = 1;',
            'prepare',
            static function (string $code) use (&$received): void {
                $received = $code;
            }
        );

        self::assertSame('$value = 1;', $received);
    }

    public function testParseErrorIsLoggedAndReported(): void
    {
        $app = new Application();
        Log::$entries = [];

        TemplatePrepareHelper::execute(
            $app,
            'invalid code',
            'prepare_code',
            static function (): void {
                throw new \ParseError('Unexpected token');
            }
        );

        self::assertCount(1, Log::$entries);
        self::assertStringContainsString('Invalid prepare_code code; skipped.', Log::$entries[0][0]);
        self::assertSame(Log::WARNING, Log::$entries[0][1]);
        self::assertSame('com_contentbuilderng', Log::$entries[0][2]);
        self::assertSame('warning', $app->messages[0][1]);
    }
}
