<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Installer;

use PHPUnit\Framework\TestCase;

final class UpdateSummaryLogTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $path = \dirname(__DIR__, 4) . '/script.php';
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $this->source = $source;
    }

    public function testUpdatedHighlightsAreOnlyShownInFinalSummary(): void
    {
        self::assertStringContainsString(
            "'[INFO] Visible update summary: shipped plugins/scripts modified during this update.'",
            $this->source
        );
        self::assertStringContainsString(
            "'<strong>Updated shipped plugins/scripts:</strong><br>'",
            $this->source
        );
        self::assertStringNotContainsString(
            "\$this->log('[UPDATED] ' . \$highlight, Log::INFO);",
            $this->source
        );
    }
}
