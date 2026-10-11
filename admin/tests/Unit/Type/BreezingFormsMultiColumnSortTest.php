<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Type;

use CB\Component\Contentbuilderng\Administrator\types\contentbuilderng_com_breezingformsng;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once \dirname(__DIR__, 4) . '/admin/src/types/com_breezingformsng.php';

final class BreezingFormsMultiColumnSortTest extends TestCase
{
    public function testDirectionsAndTypedExpressionsAreKeptInMenuOrder(): void
    {
        $method = new ReflectionMethod(contentbuilderng_com_breezingformsng::class, 'buildMultiOrderClause');
        self::assertSame(
            'CAST(nom AS CHAR) ASC, CAST(prenom AS CHAR) DESC, `colRecord` ASC',
            $method->invoke(null, 'col12|col13|colRecord', 'asc|desc|asc', [
                'col12' => 'CAST(nom AS CHAR)',
                'col13' => 'CAST(prenom AS CHAR)',
            ], ['col12' => 'CHAR', 'col13' => 'CHAR'], ['colRecord'], null)
        );
    }

    public function testUnknownColumnsCannotEnterTheSql(): void
    {
        $method = new ReflectionMethod(contentbuilderng_com_breezingformsng::class, 'buildMultiOrderClause');
        self::assertSame('', $method->invoke(null, 'col12|invalid', 'asc|desc', [], ['col12' => 'CHAR'], [], null));
    }

    public function testSingleColumnKeepsExistingSortingPath(): void
    {
        $method = new ReflectionMethod(contentbuilderng_com_breezingformsng::class, 'buildMultiOrderClause');
        self::assertSame('', $method->invoke(null, 'col12', 'desc', [], ['col12' => 'CHAR'], [], null));
    }
}
