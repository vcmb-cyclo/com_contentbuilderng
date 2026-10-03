<?php

declare(strict_types=1);

namespace Joomla\CMS\Component {
    final class ComponentHelper
    {
        public static function getParams(string $component): object
        {
            return new class {
                public function get(string $key, mixed $default = null): mixed { return $default; }
            };
        }
    }
}

namespace CB\Component\Contentbuilderng\Tests\Unit\View {

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 4) . '/site/src/Helper/MenuParamHelper.php';

final class ListFilterPanelVisibilityTest extends TestCase
{
    public static function visibilityCases(): array
    {
        return [
            'panel hidden, search visible' => [0, true, true],
            'panel visible, search visible' => [1, true, true],
            'panel inherited, search visible' => [-1, true, true],
            'both edit panels hidden, search visible' => [0, true, true],
            'panel hidden, search hidden' => [0, false, true],
            'panel visible, search hidden' => [1, false, true],
        ];
    }

    #[DataProvider('visibilityCases')]
    public function testSearchAndEditActionsHaveIndependentVisibility(int $top, bool $search, bool $actions): void
    {
        $html = $this->renderPanel($top, $search);
        self::assertSame($search, str_contains($html, 'id="contentbuilderng_filter"'));
        self::assertSame($search, str_contains($html, 'id="cbSearchButton"'));
        self::assertSame($search, str_contains($html, 'id="cbResetButton"'));
        self::assertSame($actions, str_contains($html, 'cb-list-new-btn'));
        self::assertStringContainsString('id="list_limit"', $html);
        self::assertStringContainsString('fa-download', $html);
    }

    public function testStateFilterRemainsVisibleWithTheEditPanelHidden(): void
    {
        $html = $this->renderPanel(0, false, true);
        self::assertStringContainsString('id="list_state_filter"', $html);
        self::assertStringContainsString('id="list_state"', $html);
        self::assertStringContainsString('cb-list-new-btn', $html);
    }

    public function testDisabledPresentationSearchDoesNotExposeSearchControls(): void
    {
        $html = $this->renderPanel(0, true, false, false);
        self::assertStringNotContainsString('id="contentbuilderng_filter"', $html);
    }

    private function renderPanel(int $top, bool $search, bool $state = false, bool $allowSearch = true): string
    {
        $template = file_get_contents(\dirname(__DIR__, 4) . '/site/tmpl/list/default.php');
        $start = strpos($template, '$showNewButton =');
        $end = strpos($template, '$listEditBaseParams =', $start);
        $variables = substr($template, $start, $end - $start);
        $start = strpos($template, '<?php if ($hasTopBarContent)');
        $end = strpos($template, '<?php if ($usesCardLayout)', $start);
        $panel = substr($template, $start, $end - $start);

        $view = new class ($search) {
            public bool $new_button = true;
            public bool $select_column = true;
            public bool $button_bar_sticky = false;
            public bool $show_preview_link = false;
            public int $cb_show_top_bar = 1;
            public int $cb_show_bottom_bar = 1;
            public bool $list_publish = false;
            public bool $list_language = false;
            public bool $show_records_per_page = true;
            public bool $export_xls = true;
            public bool $invalid_list_setup = false;
            public object $state;
            public object $pagination;
            public array $languages = [];
            public array $states = [['id' => 1, 'title' => 'Ready', 'color' => '#198754']];
            public array $lists = ['filter' => '', 'filter_state' => 0];
            public function __construct(public bool $display_filter) {
                $this->state = new class { public function get(string $key): int { return 20; } };
                $this->pagination = (object) ['limit' => 20, 'total' => 100];
            }
            public function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
        };
        $render = function () use ($variables, $panel, $top, $state, $allowSearch): string {
            $app = new class ($top) {
                public function __construct(private int $top) {}
                public function getInput(): object
                {
                    return new class ($this->top) {
                        public function __construct(private int $top) {}
                        public function get(string $key, mixed $default = null, string $filter = 'raw'): mixed
                        {
                            return in_array($key, ['cb_show_top_bar', 'cb_show_bottom_bar'], true) ? $this->top : 1;
                        }
                    };
                }
            };
            $new_allowed = true;
            $language_allowed = false;
            $state_allowed = true;
            $publish_allowed = false;
            $delete_allowed = false;
            $showStateBulkControl = true;
            $showStateFilter = $state;
            $embeddedListHidePagination = false;
            $newRecordLink = '/new';
            $exportQueryParams = ['option' => 'com_contentbuilderng', 'view' => 'export'];
            $previewQuery = '';
            $cbListActionAllowed = static fn(string $action): bool => $action !== 'search' || $allowSearch;
            ob_start();
            try {
                eval('use Joomla\CMS\Language\Text; use Joomla\CMS\Router\Route; use CB\Component\Contentbuilderng\Administrator\Helper\ListLimitHelper; use CB\Component\Contentbuilderng\Site\Helper\MenuParamHelper; '
                    . $variables . '?>' . $panel);
                return ob_get_contents();
            } finally {
                ob_end_clean();
            }
        };
        return $render->call($view);
    }
}

}
