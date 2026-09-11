<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

final class MenuListViewLayoutsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testListViewIsTheOnlyClassicTableLayout(): void
    {
        $listView = $this->loadMetadata('default.xml');

        self::assertSame(
            'COM_CONTENTBUILDERNG_MENU_ITEM_LIST',
            (string) $listView->layout['title']
        );
        self::assertFileExists($this->root . '/site/tmpl/list/default.php');
        self::assertFileDoesNotExist($this->root . '/site/tmpl/list/listclassic.php');
        self::assertFileDoesNotExist($this->root . '/site/tmpl/list/listclassic.xml');
        self::assertFileDoesNotExist($this->root . '/site/src/Field/CbfilterField.php');
        self::assertFileDoesNotExist($this->root . '/site/src/Field/CbfilterhiddenField.php');
        self::assertFileDoesNotExist($this->root . '/site/src/Field/CborderhiddenField.php');
    }

    public function testListViewUsesOnlyTheModernBuilder(): void
    {
        $newContract = $this->fieldContract($this->loadMetadata('default.xml'));

        self::assertContains(
            ['name' => 'cb_new_config', 'type' => 'menulistbuilder', 'default' => '{}'],
            $newContract
        );
        self::assertNotContains(
            ['name' => 'cb_list_filter', 'type' => 'cbfilter', 'default' => ''],
            $newContract
        );
    }

    public function testSpecialisedLayoutsUseTheSameContractAsListView(): void
    {
        $newContract = $this->fieldContract($this->loadMetadata('default.xml'));

        foreach (['listcard.xml', 'listcompact.xml', 'listtiles.xml'] as $file) {
            $contract = $this->fieldContract($this->loadMetadata($file));

            self::assertSame($newContract, $contract, $file);
            self::assertContains(
                ['name' => 'cb_new_config', 'type' => 'menulistbuilder', 'default' => '{}'],
                $contract
            );
            self::assertNotContains(
                ['name' => 'cb_list_filter', 'type' => 'cbfilter', 'default' => ''],
                $contract
            );
        }
    }

    public function testRemovedClassicLabelsDoNotRemainInMenuLanguageDomains(): void
    {
        foreach (['en-GB', 'fr-FR', 'de-DE'] as $tag) {
            foreach (
                [
                    'admin/language/' . $tag . '/com_contentbuilderng.menu.ini',
                    'admin/language/' . $tag . '/com_contentbuilderng.sys.ini',
                    'site/language/' . $tag . '/com_contentbuilderng.sys.ini',
                ] as $path
            ) {
                $contents = \file_get_contents($this->root . '/' . $path);

                self::assertIsString($contents);
                self::assertStringNotContainsString('COM_CONTENTBUILDERNG_MENU_ITEM_LIST_CLASSIC=', $contents);
                self::assertStringNotContainsString('COM_CONTENTBUILDERNG_MENU_CLASSIC_LEGACY_WARNING=', $contents);
                self::assertStringNotContainsString('COM_CONTENTBUILDERNG_MENU_NEW_CLASSIC_FILTERS_FOUND=', $contents);
            }
        }
    }

    public function testLockedCapabilityHelpExistsInEveryMenuLanguageDomain(): void
    {
        foreach (['en-GB', 'fr-FR', 'de-DE'] as $tag) {
            foreach (['com_contentbuilderng.menu.ini', 'com_contentbuilderng.sys.ini'] as $file) {
                $contents = (string) \file_get_contents(
                    $this->root . '/admin/language/' . $tag . '/' . $file
                );

                self::assertStringContainsString('COM_CONTENTBUILDERNG_MENU_NEW_FIELD_INHERITED_TIP=', $contents);
                self::assertStringContainsString('COM_CONTENTBUILDERNG_MENU_NEW_FIELD_UNAVAILABLE_TIP=', $contents);
            }
        }
    }

    public function testNewBuilderUsesExternalAssetAndFullWidthJoomlaLayout(): void
    {
        $metadata = $this->loadMetadata('default.xml');
        $fields = $metadata->xpath('/metadata/layout/config/fields/fieldset/field[@name="cb_new_config"]');

        self::assertIsArray($fields);
        self::assertCount(1, $fields);
        self::assertSame('true', (string) $fields[0]['hiddenLabel']);

        $fieldSource = (string) \file_get_contents($this->root . '/site/src/Field/MenulistbuilderField.php');
        $assetSource = (string) \file_get_contents($this->root . '/media/js/menu-list-options.js');

        self::assertStringNotContainsString('<script>', $fieldSource);
        self::assertStringContainsString("storage.closest('form')?.addEventListener('submit', () => {", $assetSource);
        self::assertStringContainsString('if (!storage.dataset.cbMenuOriginalName)', $assetSource);
        self::assertStringContainsString('data-cb-new-list-builder', $fieldSource);
        self::assertStringContainsString("\$config['action']", $fieldSource);
        self::assertStringContainsString("\$config['security']", $fieldSource);
        self::assertStringNotContainsString("\$config['actions']", $fieldSource);
        self::assertStringContainsString('data-can-search', $fieldSource);
        self::assertStringContainsString('data-cb-search-field', $fieldSource);
        self::assertStringContainsString('data-can-link', $fieldSource);
        self::assertStringContainsString('data-cb-link-field', $fieldSource);
        self::assertStringContainsString("['linkable']", $fieldSource);
        self::assertStringContainsString('hide-aware-inline-help d-none', $fieldSource);
        self::assertStringContainsString("'maximumRecords'", $fieldSource);
        self::assertStringContainsString("'cb_maximum_records'", $fieldSource);
        self::assertStringNotContainsString("'limitEnabled'", $fieldSource);
    }

    public function testListViewUsesTheValidatedB12TopAndSectionOrder(): void
    {
        $metadata = $this->loadMetadata('default.xml');
        $fields = $metadata->xpath('/metadata/layout/config/fields/fieldset/field');

        self::assertIsArray($fields);
        $contract = array_map(
            static fn (\SimpleXMLElement $field): array => [
                'name' => (string) $field['name'],
                'type' => (string) $field['type'],
                'label' => (string) $field['label'],
            ],
            $fields
        );

        self::assertSame('note', $contract[0]['type']);
        self::assertSame('COM_CONTENTBUILDERNG_MENU_NEW_INTRO', $contract[0]['label']);
        self::assertSame('form_id', $contract[1]['name']);
        self::assertSame('cb_theme_plugin', $contract[2]['name']);
        self::assertSame('cb_menu_reset', $contract[3]['name']);
        self::assertSame('cb_show_details_top_bar', $contract[4]['name']);
        self::assertSame(0, count(array_filter(
            $contract,
            static fn (array $field): bool => $field['label'] === 'COM_CONTENTBUILDERNG_MENU_ITEM_LIST'
        )));
        self::assertLessThan(
            array_search('cb_show_author', array_column($contract, 'name'), true),
            array_search('cb_new_config', array_column($contract, 'name'), true)
        );

        $fieldSource = (string) file_get_contents($this->root . '/site/src/Field/MenulistbuilderField.php');
        $scriptSource = (string) file_get_contents($this->root . '/media/js/menu-list-options.js');
        $topMarkers = [
            'data-cb-native-field-slot="cb_list_limit"',
            "Text::_('COM_CONTENTBUILDERNG_MENU_NEW_MAX_RECORDS')",
            "Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SORT_MODE')",
        ];
        $offset = 0;
        foreach ($topMarkers as $marker) {
            $position = strpos($fieldSource, $marker, $offset);
            self::assertNotFalse($position, $marker . ' is missing or out of order.');
            $offset = $position + 1;
        }

        $expectedSections = [
            'COM_CONTENTBUILDERNG_MENU_NEW_DISPLAY',
            'COM_CONTENTBUILDERNG_MENU_NEW_SEARCH_STATE',
            'COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_DISPLAY',
            'COM_CONTENTBUILDERNG_MENU_NEW_COLUMNS_FILTERS',
        ];
        $offset = 0;
        foreach ($expectedSections as $languageKey) {
            $position = strpos($fieldSource, "Text::_('" . $languageKey . "')", $offset);
            self::assertNotFalse($position, $languageKey . ' is missing or out of order.');
            $offset = $position + 1;
        }

        self::assertStringContainsString('data-cb-native-field-slot="cb_list_limit"', $fieldSource);
        foreach ([
            'cb_show_details_top_bar',
            'cb_show_details_bottom_bar',
            'cb_show_top_bar',
            'cb_show_bottom_bar',
            'cb_show_details_back_button',
        ] as $fieldName) {
            self::assertStringContainsString('data-cb-native-field-slot="' . $fieldName . '"', $fieldSource);
        }
        self::assertStringContainsString("slot.append(group);", $scriptSource);
        self::assertStringNotContainsString("section(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ACTIONS')", $fieldSource);
        self::assertStringNotContainsString("section(Text::_('COM_CONTENTBUILDERNG_MENU_NEW_SORTING_PAGINATION')", $fieldSource);
        self::assertStringContainsString('cb-menu-introduction-settings', $fieldSource);
        self::assertStringContainsString('data-cb-key="titleMode"', $fieldSource);
        self::assertStringContainsString('data-cb-show-when="titleMode:custom"', $fieldSource);
        self::assertStringContainsString("'exportFilenameMode'", $fieldSource);
        self::assertStringContainsString('data-cb-show-when="exportFilenameMode:custom"', $fieldSource);
        self::assertStringContainsString('data-cb-key="exportFilename"', $fieldSource);
        self::assertStringContainsString(". \$introductionHtml . '</div>';", $fieldSource);
        self::assertStringContainsString("\$displayHtml = '<div class=\"cb-menu-native-display-fields\">'", $fieldSource);

        $displayMarkers = [
            "'action.export'",
            'data-cb-native-field-slot="cb_show_details_back_button"',
            "'action.rating'",
            'data-cb-native-field-slot="cb_show_details_top_bar"',
            'data-cb-native-field-slot="cb_show_details_bottom_bar"',
            "'action.print'",
            'data-cb-native-field-slot="cb_show_top_bar"',
            'data-cb-native-field-slot="cb_show_bottom_bar"',
            "'editListButton'",
        ];
        $offset = strpos($fieldSource, '$displayHtml =');
        self::assertNotFalse($offset);
        foreach ($displayMarkers as $marker) {
            $position = strpos($fieldSource, $marker, $offset);
            self::assertNotFalse($position, $marker . ' is missing or out of order.');
            $offset = $position + 1;
        }
        self::assertSame(3, substr_count($fieldSource, 'cb-menu-display-grid'));
    }

    public function testB12MenuHelpIsAvailableInEveryLanguageDomain(): void
    {
        $requiredKeys = [
            'COM_CONTENTBUILDERNG_MENU_NEW_SEARCH_STATE',
            'COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_DISPLAY',
            'COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_DISPLAY_DESC',
            'COM_CONTENTBUILDERNG_MENU_DETAIL_TOP_PANEL',
            'COM_CONTENTBUILDERNG_MENU_DETAIL_BOTTOM_PANEL',
            'COM_CONTENTBUILDERNG_MENU_EDIT_TOP_PANEL',
            'COM_CONTENTBUILDERNG_MENU_EDIT_BOTTOM_PANEL',
            'COM_CONTENTBUILDERNG_MENU_LINES_PER_PAGE',
            'COM_CONTENTBUILDERNG_MENU_NEW_CHARACTER_COUNT',
            'COM_CONTENTBUILDERNG_MENU_NEW_LINE',
            'COM_CONTENTBUILDERNG_MENU_NEW_LINES',
            'COM_CONTENTBUILDERNG_MENU_NEW_ACTION_PRINT',
            'COM_CONTENTBUILDERNG_MENU_NEW_EDIT_LIST_BUTTON',
            'COM_CONTENTBUILDERNG_MENU_NEW_EDIT_LIST_BUTTON_DESC',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PERMISSIONS_VALUE',
            'COM_CONTENTBUILDERNG_MENU_NEW_HIDE',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EXPORT',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EXPORT_DESC',
            'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_MODE',
            'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_DEFAULT',
            'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_CUSTOM',
            'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_CUSTOM_LABEL',
            'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_DESC',
            'COM_CONTENTBUILDERNG_MENU_EXPORT_FILENAME_CUSTOM_DESC',
            'COM_CONTENTBUILDERNG_MENU_NEW_FIELD_UNPUBLISHED_TIP',
            'COM_CONTENTBUILDERNG_MENU_NEW_FIELD_NOT_LISTED_TIP',
        ];

        foreach (['en-GB', 'fr-FR', 'de-DE'] as $tag) {
            foreach (['com_contentbuilderng.menu.ini', 'com_contentbuilderng.sys.ini'] as $file) {
                $translations = parse_ini_file($this->root . '/admin/language/' . $tag . '/' . $file);

                self::assertIsArray($translations);
                foreach ($requiredKeys as $key) {
                    self::assertArrayHasKey($key, $translations, $tag . '/' . $file . ': ' . $key);
                    self::assertNotSame('', $translations[$key]);
                }
                self::assertStringEndsWith(
                    '<code>data1 | data2* | da*ta3 | *data4</code>',
                    (string) $translations['COM_CONTENTBUILDERNG_MENU_NEW_FILTER_HELP']
                );
            }
        }

        $english = parse_ini_file($this->root . '/admin/language/en-GB/com_contentbuilderng.menu.ini');
        self::assertIsArray($english);
        self::assertSame('Detail - Top panel', $english['COM_CONTENTBUILDERNG_MENU_DETAIL_TOP_PANEL']);
        self::assertSame('Detail - Bottom panel', $english['COM_CONTENTBUILDERNG_MENU_DETAIL_BOTTOM_PANEL']);
        self::assertSame('Edit - Top panel', $english['COM_CONTENTBUILDERNG_MENU_EDIT_TOP_PANEL']);
        self::assertSame('Edit - Bottom panel', $english['COM_CONTENTBUILDERNG_MENU_EDIT_BOTTOM_PANEL']);
        self::assertSame('Detail - Print', $english['COM_CONTENTBUILDERNG_MENU_NEW_ACTION_PRINT']);
    }

    public function testResetRestoresEveryDynamicBuilderFieldFamily(): void
    {
        $fieldSource = (string) file_get_contents($this->root . '/site/src/Field/MenulistbuilderField.php');
        $scriptSource = (string) file_get_contents($this->root . '/media/js/menu-list-options.js');

        self::assertStringContainsString('data-cb-reset-value="default"', $fieldSource);
        self::assertStringContainsString("str_starts_with(\$key, 'security.') ? 'inherit' : 'default'", $fieldSource);
        self::assertStringContainsString('data-cb-reset-value="-1"', $fieldSource);
        self::assertStringContainsString('data-view-order="', $fieldSource);
        self::assertStringContainsString("field.matches('[data-cb-new-list-config]')", $scriptSource);
        self::assertStringContainsString("field.value = '{}';", $scriptSource);
        self::assertStringContainsString("field.matches('[data-cb-list-limit-storage]')", $scriptSource);
        self::assertStringContainsString('field.dataset.cbResetValue !== undefined', $scriptSource);
        self::assertStringContainsString('const configuredSort = Array.isArray(state.sort)', $scriptSource);
        self::assertStringContainsString("direction.value = String(sort.field || '') === ''", $scriptSource);
        self::assertStringContainsString("state.columnsMode !== 'custom'", $scriptSource);
        self::assertStringContainsString('Number(left.dataset.viewOrder || 0)', $scriptSource);
        self::assertStringContainsString('body?.appendChild(row)', $scriptSource);
        self::assertSame(
            2,
            substr_count($scriptSource, "event.target === storage || event.target.closest('[data-cb-native-field-slot]')"),
            'Storage and relocated native Joomla fields must not recreate builder overrides during Reset.'
        );
    }

    public function testCustomIntroductionUsesUnicodeAwareCharacterCounter(): void
    {
        $fieldSource = (string) file_get_contents($this->root . '/site/src/Field/MenulistbuilderField.php');
        $scriptSource = (string) file_get_contents($this->root . '/media/js/menu-list-options.js');

        self::assertStringContainsString('data-cb-title-character-count', $fieldSource);
        self::assertStringContainsString('<textarea rows="2"', $fieldSource);
        self::assertStringContainsString('cb-menu-introduction-textarea', $fieldSource);
        self::assertStringContainsString('data-cb-line-label-one', $fieldSource);
        self::assertStringContainsString('data-cb-line-label-many', $fieldSource);
        self::assertStringContainsString('Array.from(titleField.value)', $scriptSource);
        self::assertStringContainsString('characters.length > 255', $scriptSource);
        self::assertStringContainsString("characters.slice(0, 255).join('')", $scriptSource);
        self::assertStringContainsString("titleField.value.split('\\n').length", $scriptSource);
        self::assertStringContainsString('titleField.scrollHeight > maxHeight', $scriptSource);
    }

    public function testColumnBuilderMatchesTheViewOrderAndAllowsOnlyDownwardRestrictions(): void
    {
        $fieldSource = (string) \file_get_contents($this->root . '/site/src/Field/MenulistbuilderField.php');
        $expectedOrder = [
            'COM_CONTENTBUILDERNG_MENU_NEW_FIELD',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DISPLAY',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_SEARCH',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_LINK',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_DETAIL',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EDIT',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_EXPORT',
            'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PUBLISHED',
            'COM_CONTENTBUILDERNG_MENU_NEW_FIXED_FILTER',
        ];
        $offset = 0;
        foreach ($expectedOrder as $languageKey) {
            $position = \strpos($fieldSource, "tableHeading('" . $languageKey . "'", $offset);
            self::assertNotFalse($position, $languageKey . ' is missing or out of order.');
            $offset = $position + 1;
        }

        foreach (['detail', 'edit', 'export', 'published'] as $capability) {
            self::assertStringContainsString("capabilityCheckbox('" . $capability . "'", $fieldSource);
            self::assertStringContainsString('data-can-' . $capability, $fieldSource);
        }
        self::assertStringNotContainsString('COM_CONTENTBUILDERNG_MENU_NEW_FILTER_ONLY', $fieldSource);
        self::assertStringContainsString('cb-menu-table-heading', $fieldSource);
        self::assertStringNotContainsString('icon-info-circle', $fieldSource);
        self::assertStringContainsString('cb-menu-filter-input', $fieldSource);
        self::assertStringNotContainsString('statusCell(', $fieldSource);
    }

    public function testMenuControlsExposeInheritedSecurityValuesAndLockedCapabilities(): void
    {
        $fieldSource = (string) \file_get_contents($this->root . '/site/src/Field/MenulistbuilderField.php');
        $defaultsSource = (string) \file_get_contents($this->root . '/site/src/Helper/MenuViewDefaultsHelper.php');
        $scriptSource = (string) \file_get_contents($this->root . '/media/js/menu-list-options.js');
        $styleSource = (string) \file_get_contents($this->root . '/media/css/menu-options.css');

        self::assertStringContainsString("'COM_CONTENTBUILDERNG_MENU_NEW_VIEW_PERMISSIONS_VALUE'", $fieldSource);
        self::assertStringNotContainsString("'cb_permission_' . \$key", $fieldSource);
        self::assertStringContainsString("Text::_('COM_CONTENTBUILDERNG_MENU_NEW_ADDITIONAL_DISPLAY_DESC')", $fieldSource);
        self::assertStringContainsString("hasTip\" tabindex=\"0\" title=\"", $fieldSource);
        self::assertStringContainsString("'edit_button'", $defaultsSource);
        self::assertStringContainsString("'show_state_filter'", $defaultsSource);
        self::assertStringContainsString("'editListButton'", $fieldSource);
        self::assertStringContainsString("'show_state_filter', true", $fieldSource);
        self::assertStringNotContainsString('viewPermissionsFormat', $scriptSource);
        self::assertStringContainsString("value === 'disabled'", $scriptSource);
        self::assertStringContainsString('cb-form-select-inherited-success', $scriptSource);
        self::assertStringContainsString('cb-form-select-inherited-danger', $scriptSource);
        self::assertStringContainsString('setCapabilityState', $scriptSource);
        self::assertStringContainsString('.cb-menu-capability-cell.is-inherited', $styleSource);
        self::assertStringContainsString('.cb-form-select-inherited-success', $styleSource);
        self::assertStringContainsString('.cb-form-select-inherited-danger', $styleSource);
        self::assertStringContainsString('width: min(300px, 100%) !important;', $styleSource);
        self::assertStringNotContainsString("->where(\$db->quoteName('published') . ' = 1')", $defaultsSource);
    }

    private function loadMetadata(string $file): \SimpleXMLElement
    {
        $metadata = \simplexml_load_file($this->root . '/site/tmpl/list/' . $file);
        self::assertInstanceOf(\SimpleXMLElement::class, $metadata);

        return $metadata;
    }

    /**
     * @return list<array{name: string, type: string, default: string}>
     */
    private function fieldContract(\SimpleXMLElement $metadata): array
    {
        $fields = $metadata->xpath('/metadata/layout/config/fields/fieldset/field');
        self::assertIsArray($fields);

        return \array_values(\array_map(
            static fn (\SimpleXMLElement $field): array => [
                'name' => (string) $field['name'],
                'type' => (string) $field['type'],
                'default' => (string) $field['default'],
            ],
            $fields
        ));
    }
}
