<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use CB\Component\Contentbuilderng\Site\Service\Router;
use CB\Component\Contentbuilderng\Tests\Stubs\Application;
use Joomla\CMS\Menu\AbstractMenu;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Application $app;
    private Router $router;

    protected function setUp(): void
    {
        $this->app = new Application();
        $menu = new class extends AbstractMenu {
        };

        $this->router = new Router($this->app, $menu);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:array<int,string>,2:array<string,mixed>}>
     */
    public static function buildProvider(): array
    {
        return [
            'list controller' => [
                ['controller' => 'list', 'view' => 'list', 'id' => 25, 'title' => 'monticyclo'],
                ['', '25', 'monticyclo'],
                ['title' => 'monticyclo'],
            ],
            'details controller' => [
                ['controller' => 'details', 'view' => 'details', 'id' => 25, 'record_id' => 236, 'title' => 'entry'],
                ['details', '25', '236', 'entry'],
                ['title' => 'entry'],
            ],
            'edit controller' => [
                ['controller' => 'edit', 'view' => 'edit', 'id' => 25, 'record_id' => 236, 'title' => 'entry'],
                ['edit', '25', '236', 'entry'],
                ['title' => 'entry'],
            ],
            'export view without controller' => [
                ['view' => 'export', 'id' => 25, 'title' => 'monticyclo', 'format' => 'raw'],
                ['export', '25'],
                ['format' => 'raw'],
            ],
            'verify view without controller' => [
                ['view' => 'verify', 'id' => 25, 'title' => 'monticyclo', 'format' => 'raw'],
                ['verify', '25'],
                ['format' => 'raw'],
            ],
            'export controller keeps raw parameters' => [
                ['controller' => 'export', 'view' => 'export', 'id' => 25, 'type' => 'xls', 'format' => 'raw', 'tmpl' => 'component'],
                ['export', '25'],
                ['type' => 'xls', 'format' => 'raw', 'tmpl' => 'component'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<int,string>   $expectedSegments
     * @param array<string,mixed> $expectedRemainingQuery
     */
    #[DataProvider('buildProvider')]
    public function testBuildCreatesExpectedSegmentsAndMutatesQuery(array $query, array $expectedSegments, array $expectedRemainingQuery): void
    {
        $segments = $this->router->build($query);

        self::assertSame($expectedSegments, $segments);
        self::assertSame($expectedRemainingQuery, $query);
    }

    /**
     * @return array<string,array{0:array<int,string>,1:array<string,mixed>,2:array<string,mixed>}>
     */
    public static function parseProvider(): array
    {
        return [
            'numeric first segment maps to list' => [
                ['25', 'monticyclo'],
                ['controller' => 'list', 'id' => '25', 'title' => ''],
                ['controller' => 'list', 'id' => '25', 'title' => ''],
            ],
            'export route' => [
                ['export', '25'],
                ['controller' => 'export', 'id' => '25', 'title' => ''],
                ['controller' => 'export', 'id' => '25', 'title' => ''],
            ],
            'verify route' => [
                ['verify', '25'],
                ['controller' => 'verify', 'id' => '25', 'title' => ''],
                ['controller' => 'verify', 'id' => '25', 'title' => ''],
            ],
            'details route' => [
                ['details', '25', '236', 'entry'],
                ['controller' => 'details', 'id' => '25', 'record_id' => '236', 'title' => 'entry', 'view' => 'details'],
                ['controller' => 'details', 'id' => '25', 'record_id' => '236', 'title' => 'entry', 'view' => 'details'],
            ],
            'edit route' => [
                ['edit', '25', '236', 'entry'],
                ['controller' => 'edit', 'id' => '25', 'record_id' => '236', 'title' => 'entry', 'view' => 'edit'],
                ['controller' => 'edit', 'id' => '25', 'record_id' => '236', 'title' => 'entry', 'view' => 'edit'],
            ],
        ];
    }

    /**
     * @param array<int,string>   $segments
     * @param array<string,mixed> $expectedVars
     * @param array<string,mixed> $expectedInput
     */
    #[DataProvider('parseProvider')]
    public function testParseCreatesExpectedVarsAndSetsInput(array $segments, array $expectedVars, array $expectedInput): void
    {
        $vars = $this->router->parse($segments);

        self::assertSame($expectedVars, $vars);
        self::assertSame([], $segments);

        foreach ($expectedInput as $key => $expectedValue) {
            self::assertSame((string) $expectedValue, $this->app->input->getString($key));
        }
    }

    public function testPreprocessReturnsQueryUnchanged(): void
    {
        $query = ['view' => 'export', 'id' => 25];

        self::assertSame($query, $this->router->preprocess($query));
    }
}
