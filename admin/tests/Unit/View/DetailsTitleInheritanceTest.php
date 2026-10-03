<?php
declare(strict_types=1);
namespace CB\Component\Contentbuilderng\Tests\Unit\View;
use PHPUnit\Framework\TestCase;
use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper;
require_once \dirname(__DIR__, 4) . '/site/src/Helper/MenuParamHelper.php';

final class DetailsTitleInheritanceTest extends TestCase
{
    public function testPrefixIsResolvedWithLoadedViewDefaults(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 4) . '/site/src/Model/DetailsModel.php');
        $loop = strpos($source, 'foreach ($this->_data as $data) {');
        self::assertNotFalse($loop);
        $assignment = strpos($source, '$prefixInTitle =');
        self::assertGreaterThan($loop, $assignment, 'The view must be loaded before resolving its default.');
        $statement = substr($source, $assignment, strpos($source, ';', $assignment) - $assignment + 1);
        foreach ([[-1, 1, 1], [-1, 0, 0], [null, 1, 1], [0, 1, 0], [1, 0, 1]] as [$menu, $default, $expected]) {
            $context = new class ($menu) {
                public function __construct(private mixed $menu) {}
                public function getMenuToggle(string $key, int $default): int
                {
                    return MenuParamHelper::resolveToggleValue($this->menu, $default);
                }
            };
            $result = (function () use ($statement, $default): int {
                $data = (object) ['cb_prefix_in_title' => $default];
                eval($statement);
                return $prefixInTitle;
            })->call($context);
            self::assertSame($expected, $result);
        }
    }
}
