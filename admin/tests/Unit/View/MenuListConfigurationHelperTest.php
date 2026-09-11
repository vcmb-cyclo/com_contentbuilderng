<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use CB\Component\Contentbuilderng\Site\Helper\MenuListConfigurationHelper;
use PHPUnit\Framework\TestCase;

final class MenuListConfigurationHelperTest extends TestCase
{
    public function testDisplayActionsOverrideBothViewDefaults(): void
    {
        foreach (['export' => 'export_xls', 'print' => 'print_button', 'rating' => 'list_rating'] as $action => $field) {
            foreach ([0, 1] as $viewValue) {
                foreach (['yes', 'no', 'default'] as $choice) {
                    $parameters = MenuListConfigurationHelper::requestParameters(['action' => [$action => $choice]]);
                    $key = 'cb_new_show_' . $action;
                    self::assertSame($choice !== 'default', array_key_exists($key, $parameters));
                    $data = (object) [$field => $viewValue];
                    MenuListConfigurationHelper::applyDisplayActionOverrides($data, [$action => $parameters[$key] ?? 'default']);
                    self::assertSame($choice === 'default' ? $viewValue : (int) ($choice === 'yes'), $data->{$field});
                    if ($choice === 'no') {
                        self::assertNotContains($action, explode('|', $parameters['cblist_actions']));
                    }
                }
            }
        }
    }

    public function testNewListMenuDetectionIncludesEveryHarmonisedLayout(): void
    {
        foreach (['', 'default', 'listcard', 'listcompact', 'listtiles'] as $layout) {
            $item = (object) ['query' => ['view' => 'list', 'layout' => $layout]];

            self::assertTrue(MenuListConfigurationHelper::isNewListMenu($item), $layout);
        }

        self::assertFalse(MenuListConfigurationHelper::isNewListMenu(
            (object) ['query' => ['view' => 'list', 'layout' => 'unsupported-layout']]
        ));
        self::assertFalse(MenuListConfigurationHelper::isNewListMenu(
            (object) ['query' => ['view' => 'create', 'layout' => 'default']]
        ));
    }

    public function testConfigurationBuildsConstrainedRequestParameters(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'titleMode' => 'custom',
            'title' => '  🚲 Routes  ',
            'columnsMode' => 'custom',
            'columns' => ['12', '9'],
            'linkFields' => ['9'],
            'detailFields' => ['9'],
            'editFields' => ['12'],
            'exportFields' => ['9', '12'],
            'publishedFields' => ['9', '12'],
            'filters' => ['9' => ' Route 1*| *Gravel ', '15' => 'blocked'],
            'searchFields' => ['12'],
            'sortMode' => 'custom',
            'sort' => [
                ['field' => 'ID', 'dir' => 'desc'],
                ['field' => '12', 'dir' => 'asc'],
            ],
            'maximumRecords' => 25,
            'searchShow' => 'no',
            'stateShow' => 'yes',
            'stateBulkShow' => 'yes',
            'stateFilterShow' => 'no',
            'editListButton' => 'no',
            'action' => [
                'export' => 'no',
            ],
            'security' => [
                'delete' => 'disabled',
                'detail' => 'disabled',
            ],
        ]);

        self::assertSame('🚲 Routes', $parameters['cblist_title']);
        self::assertSame('12|9', $parameters['cblist_fields']);
        self::assertSame('{"9":["Route 1*","*Gravel"]}', $parameters['cb_menu_data_filters']);
        self::assertSame('12', $parameters['cb_menu_search_fields']);
        self::assertSame('9', $parameters['cb_menu_link_fields']);
        self::assertSame('9', $parameters['cb_menu_detail_fields']);
        self::assertSame('12', $parameters['cb_menu_edit_fields']);
        self::assertSame('9|12', $parameters['cb_menu_export_fields']);
        self::assertSame('9|12', $parameters['cb_menu_published_fields']);
        self::assertSame(1, $parameters['cb_new_list_menu']);
        self::assertSame('ID|12', $parameters['cblist_sort']);
        self::assertSame('desc|asc', $parameters['cblist_dir']);
        self::assertSame(25, $parameters['cblist_limit']);
        self::assertArrayNotHasKey('cb_show_details_back_button', $parameters);
        self::assertArrayNotHasKey('cb_new_show_limit_selector', $parameters);
        self::assertSame('no', $parameters['cb_new_show_search']);
        self::assertSame('yes', $parameters['cb_new_show_state']);
        self::assertSame('yes', $parameters['cb_new_show_state_bulk']);
        self::assertSame('no', $parameters['cb_new_show_state_filter']);
        self::assertSame('no', $parameters['cb_new_show_list_edit']);
        self::assertStringNotContainsString('delete', (string) $parameters['cblist_actions']);
        self::assertStringNotContainsString('detail', (string) $parameters['cblist_actions']);
        self::assertStringNotContainsString('export', (string) $parameters['cblist_actions']);
        self::assertStringNotContainsString('search', (string) $parameters['cblist_actions']);
        self::assertSame('content-plugin', $parameters['cblist_embed']);
    }

    public function testSearchFieldsOnlyReduceViewSearchableFields(): void
    {
        self::assertSame(
            [7, 12],
            MenuListConfigurationHelper::filterSearchableElements([3, 7, 12], '12|7|99')
        );
    }

    public function testCustomIntroductionPreservesIntentionalLineBreaksAndUnicodeLimit(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'titleMode' => 'custom',
            'title' => "  Première ligne\r\nDeuxième ligne 🚲  ",
        ]);

        self::assertSame("Première ligne\nDeuxième ligne 🚲", $parameters['cblist_title']);
        self::assertLessThanOrEqual(255, mb_strlen($parameters['cblist_title'], 'UTF-8'));
    }

    public function testCustomColumnsCanDisableAllViewSearchFields(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'columnsMode' => 'custom',
            'columns' => [],
            'searchFields' => [],
            'linkFields' => [],
            'detailFields' => [],
            'editFields' => [],
            'exportFields' => [],
            'publishedFields' => [],
        ]);

        self::assertSame('__none__', $parameters['cb_menu_search_fields']);
        self::assertSame('__none__', $parameters['cb_menu_link_fields']);
        self::assertSame('__none__', $parameters['cb_menu_detail_fields']);
        self::assertSame('__none__', $parameters['cb_menu_edit_fields']);
        self::assertSame('__none__', $parameters['cb_menu_export_fields']);
        self::assertSame('__none__', $parameters['cb_menu_published_fields']);
        self::assertSame([], MenuListConfigurationHelper::filterSearchableElements([3, 7, 12], '__none__'));
        self::assertSame('content-plugin', $parameters['cblist_embed']);
    }

    public function testIncompletePrototypeCustomConfigurationKeepsViewDefaults(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'columnsMode' => 'custom',
        ]);

        self::assertSame('', $parameters['cb_menu_search_fields']);
        self::assertSame('', $parameters['cb_menu_link_fields']);
        self::assertSame('', $parameters['cb_menu_export_fields']);
        self::assertArrayNotHasKey('cb_new_list_menu', $parameters);
        self::assertArrayNotHasKey('cblist_embed', $parameters);
    }

    public function testDefaultConfigurationDoesNotCreateEmbeddedContext(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([]);

        self::assertArrayNotHasKey('cblist_embed', $parameters);
        self::assertSame('', $parameters['cb_menu_data_filters']);
        self::assertSame('', $parameters['cb_menu_link_fields']);
        self::assertArrayNotHasKey('cb_new_list_menu', $parameters);
        self::assertArrayNotHasKey('cb_new_show_search', $parameters);
        self::assertArrayNotHasKey('cb_new_show_state', $parameters);
        self::assertArrayNotHasKey('cb_new_show_state_bulk', $parameters);
        self::assertArrayNotHasKey('cb_new_show_state_filter', $parameters);
        self::assertArrayNotHasKey('cb_new_show_list_edit', $parameters);
        self::assertArrayNotHasKey('cb_export_filename_mode', $parameters);
        self::assertArrayNotHasKey('cb_export_filename', $parameters);
    }

    public function testCustomExportFilenameIsForwardedWithoutChangingListBehaviour(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'exportFilenameMode' => 'custom',
            'exportFilename' => '  Participants BRM 200  ',
        ]);

        self::assertSame('custom', $parameters['cb_export_filename_mode']);
        self::assertSame('Participants BRM 200', $parameters['cb_export_filename']);
        self::assertArrayNotHasKey('cblist_embed', $parameters);
    }

    public function testSearchVisibilityCanOverrideTheViewSetting(): void
    {
        self::assertSame(
            'yes',
            MenuListConfigurationHelper::requestParameters(['searchShow' => 'yes'])['cb_new_show_search']
        );
        self::assertSame(
            'no',
            MenuListConfigurationHelper::requestParameters(['searchShow' => 'no'])['cb_new_show_search']
        );
    }

    public function testListEditButtonCanOnlyInheritOrHideTheViewButton(): void
    {
        self::assertArrayNotHasKey(
            'cb_new_show_list_edit',
            MenuListConfigurationHelper::requestParameters(['editListButton' => 'default'])
        );
        self::assertSame(
            'no',
            MenuListConfigurationHelper::requestParameters(['editListButton' => 'no'])['cb_new_show_list_edit']
        );
    }

    public function testMaximumRecordsInheritsTheViewAndAllowsMenuOverrides(): void
    {
        self::assertSame(
            18,
            MenuListConfigurationHelper::requestParameters([], 18)['cblist_limit']
        );
        self::assertArrayNotHasKey(
            'cblist_limit',
            MenuListConfigurationHelper::requestParameters([], 0)
        );
        self::assertSame(
            25,
            MenuListConfigurationHelper::requestParameters(['maximumRecords' => 25], 18)['cblist_limit']
        );
        self::assertArrayNotHasKey(
            'cblist_limit',
            MenuListConfigurationHelper::requestParameters(['maximumRecords' => 0], 18)
        );
    }

    public function testActionAndSecurityStorageKeysMatchTheMenuBuilderContract(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'action' => ['export' => 'no'],
            'security' => ['detail' => 'disabled'],
        ]);

        self::assertArrayHasKey('cblist_actions', $parameters);
        self::assertStringNotContainsString('export', (string) $parameters['cblist_actions']);
        self::assertStringNotContainsString('detail', (string) $parameters['cblist_actions']);
        self::assertSame('content-plugin', $parameters['cblist_embed']);
    }

    public function testHiddenListColumnKeepsItsFilterWithoutBecomingDisplayed(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'columnsMode' => 'custom',
            'columnOrder' => ['9', '12'],
            'columns' => ['12'],
            'searchFields' => ['9', '12'],
            'linkFields' => ['9', '12'],
            'detailFields' => ['9', '12'],
            'editFields' => ['12'],
            'exportFields' => ['9'],
            'publishedFields' => ['9', '12'],
            'filters' => ['9' => 'DAN*'],
        ]);

        self::assertSame('12', $parameters['cblist_fields']);
        self::assertSame('9|12', $parameters['cb_menu_search_fields']);
        self::assertSame('{"9":["DAN*"]}', $parameters['cb_menu_data_filters']);
    }

    public function testCustomExportSelectionIsIndependentFromDisplayedColumns(): void
    {
        $parameters = MenuListConfigurationHelper::requestParameters([
            'columnsMode' => 'custom',
            'columns' => ['12'],
            'exportFields' => ['9'],
        ]);
        $exportModel = (string) file_get_contents(dirname(__DIR__, 4) . '/site/src/Model/ExportModel.php');

        self::assertSame('12', $parameters['cblist_fields']);
        self::assertSame('9', $parameters['cb_menu_export_fields']);
        self::assertStringContainsString("getString('cb_menu_export_fields', '')", $exportModel);
        self::assertStringContainsString("getString('cb_menu_published_fields', '')", $exportModel);
        self::assertStringContainsString('!$newMenuCustomColumns', $exportModel);
    }

    public function testExportKeepsTheMenuColumnOrderAndConfiguration(): void
    {
        self::assertSame(
            [12, 9],
            MenuListConfigurationHelper::selectElementsInMenuOrder([9, 12, 18], '12|9')
        );

        $root = dirname(__DIR__, 4);
        $template = (string) file_get_contents($root . '/site/tmpl/list/default.php');
        $exportModel = (string) file_get_contents($root . '/site/src/Model/ExportModel.php');

        self::assertStringContainsString('MenuDataFilterService::INPUT_NAME =>', $template);
        self::assertStringContainsString("'cb_menu_export_fields' =>", $template);
        self::assertStringContainsString("'cb_menu_published_fields' =>", $template);
        self::assertStringContainsString("'cblist_sort' =>", $template);
        self::assertStringContainsString('selectElementsInMenuOrder(', $exportModel);
        self::assertStringContainsString('$this->recordFilters[$filterKey] = $filterTerms;', $exportModel);
        self::assertStringNotContainsString('$this->recordFilters = $new_filters;', $exportModel);
    }
}
