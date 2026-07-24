<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\PathService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/PathService.php';

final class PathServiceTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function pathProvider(): array
    {
        return [
            'relative path' => ['folder/sub folder/file.txt', 'folder/sub_folder/file.txt'],
            'absolute path' => ['/var//www/./site', '/var/www/site'],
            'parent segments' => ['folder/../safe/file', 'folder/safe/file'],
            'hidden segments' => ['folder/.hidden/.../file', 'folder/hidden/file'],
            'windows path' => ['C:\\folder\\sub folder\\file.txt', 'C:/folder/sub_folder/file.txt'],
            'unsafe characters' => ['folder/<bad>|name', 'folder/_bad__name'],
            'empty segments' => ['./../...', ''],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testMakesFolderPathSafe(string $path, string $expected): void
    {
        self::assertSame($expected, (new PathService())->makeSafeFolder($path));
    }
}
