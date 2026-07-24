<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\TextUtilityService;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/TextUtilityService.php';

final class TextUtilityServiceTest extends TestCase
{
    private TextUtilityService $service;

    protected function setUp(): void
    {
        $this->service = new TextUtilityService();
    }

    public function testCreatesUnicodeSlug(): void
    {
        self::assertSame(
            'déjà-vu-test',
            $this->service->stringURLUnicodeSlug("  Déjà-vu: test?  ")
        );
    }

    public function testEscapesHtmlAndTemplateDelimiters(): void
    {
        self::assertSame(
            '&lt;b&gt;&#91;x&#93; &#124; &#40;y&#41;&lt;/b&gt;',
            $this->service->allhtmlspecialchars('<b>[x] | (y)</b>')
        );
    }

    public function testCleansAllTemplateDelimiters(): void
    {
        self::assertSame(
            '&#91;&#93;&#123;&#125;&#40;&#41;&#124;',
            $this->service->cleanString('[]{}()|')
        );
    }
}
