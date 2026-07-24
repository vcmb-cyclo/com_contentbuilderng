<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\RuntimeUtilityService;
use CB\Component\Contentbuilderng\Tests\Stubs\Application;
use Joomla\CMS\Log\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/RuntimeUtilityService.php';

final class RuntimeUtilityServiceTest extends TestCase
{
    private Application $app;
    private RuntimeUtilityService $service;

    protected function setUp(): void
    {
        $this->app = new Application();
        $this->service = new RuntimeUtilityService($this->app);
        Log::$entries = [];
    }

    public function testSanitizesAndExpandsHiddenFilterVariables(): void
    {
        self::assertSame(
            '42|unit_user|Unit User|2026-02-17 12:00:00|12:00:00|2026-02-17 12:00:00',
            $this->service->sanitizeHiddenFilterValue(
                " {userid}|{username}|{name}|{date}|{time}|{datetime}\r\n"
            )
        );
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function blockedExpressionProvider(): array
    {
        return [
            'value expression' => ['$value = 42;'],
            'mixed-case expression' => ['$VaLuE = 42;'],
            'PHP opening tag' => ['<?php echo 42;'],
            'mixed-case PHP tag' => ['<?PhP echo 42;'],
        ];
    }

    #[DataProvider('blockedExpressionProvider')]
    public function testBlocksPhpExpressions(string $value): void
    {
        self::assertSame('', $this->service->sanitizeHiddenFilterValue($value));
        self::assertSame('Blocked PHP expression in hidden filter value.', Log::$entries[0][0]);
        self::assertSame(Log::WARNING, Log::$entries[0][1]);
    }

    public function testReturnsEmptyStringWithoutLogging(): void
    {
        self::assertSame('', $this->service->sanitizeHiddenFilterValue(" \r\n "));
        self::assertSame([], Log::$entries);
    }

    public function testExecutesPhpTemplateThroughSharedHelper(): void
    {
        self::assertSame('Hello 42', $this->service->execPhp('<?php return "Hello " . (6 * 7); ?>'));
    }

    public function testBuildsPaginationForFirstPage(): void
    {
        $pagination = $this->service->getPagination(0, 10, 35);

        self::assertStringContainsString('<div class="pagination">', $pagination);
        self::assertStringContainsString('start=10', $pagination);
        self::assertStringContainsString('<span class="pagenav">1</span>', $pagination);
        self::assertStringContainsString('start=30', $pagination);
        self::assertStringNotContainsString('start=-', $pagination);
    }

    public function testBuildsPaginationForLastPageAndNormalizesExcessiveStart(): void
    {
        $pagination = $this->service->getPagination(999, 10, 35);

        self::assertStringContainsString('<span class="pagenav">4</span>', $pagination);
        self::assertStringContainsString('start=20', $pagination);
        self::assertStringNotContainsString('start=40', $pagination);
    }

    public function testReturnsNoPaginationForSinglePage(): void
    {
        self::assertSame('', $this->service->getPagination(10, 50, 12));
        self::assertSame('', $this->service->getPagination(10, 0, 12));
    }
}
