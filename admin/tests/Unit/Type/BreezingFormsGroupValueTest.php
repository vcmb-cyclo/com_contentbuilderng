<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Type;

use CB\Component\Contentbuilderng\Administrator\types\contentbuilderng_com_breezingformsng;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once \dirname(__DIR__, 4) . '/admin/src/types/com_breezingformsng.php';

final class BreezingFormsGroupValueTest extends TestCase
{
    public function testGroupValueMatchNormalizesFrenchAccentsAndApostrophes(): void
    {
        $method = new ReflectionMethod(contentbuilderng_com_breezingformsng::class, 'normalizeGroupValueForMatch');
        $method->setAccessible(true);

        self::assertSame(
            $method->invoke(null, "Soirée pizzas vendredi 19 juin - 19h"),
            $method->invoke(null, "Soire\u{0301}e pizzas vendredi 19 juin - 19h")
        );

        self::assertSame(
            $method->invoke(null, "L'école des jeunes"),
            $method->invoke(null, "L\u{2019}e\u{0301}cole\u{00A0}des   jeunes")
        );
    }
}
