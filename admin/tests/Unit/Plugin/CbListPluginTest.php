<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use CB\Component\Contentbuilderng\Site\Service\EmbeddedListActionFilterService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListContextService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListFieldFilterService;
use CB\Component\Contentbuilderng\Site\Service\ContentCardService;
use CB\Plugin\Content\ContentbuilderngList\Service\EmbedOptionsService;
use CB\Plugin\Content\ContentbuilderngList\Service\TagSyntaxService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 4)
    . '/plugins/content/contentbuilderng_cblist/src/Service/TagSyntaxService.php';
require_once \dirname(__DIR__, 4)
    . '/plugins/content/contentbuilderng_cblist/src/Service/EmbedOptionsService.php';
require_once \dirname(__DIR__, 4)
    . '/site/src/Service/EmbeddedListFieldFilterService.php';
require_once \dirname(__DIR__, 4)
    . '/site/src/Service/EmbeddedListActionFilterService.php';
require_once \dirname(__DIR__, 4)
    . '/site/src/Service/EmbeddedListContextService.php';

final class CbListPluginTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../../..';

    public function testTagAttributesAndOptionsAreResolved(): void
    {
        $attributes = TagSyntaxService::parseAttributes(
            ' id=15 fields="Nom|Prenom|Email" labels="title=Liste des inscrits" sort="Nom|Prenom" dir="asc" pagination=25 limit=10 actions="detail|edit|export" layout=cards height=700 loading=lazy'
        );

        self::assertSame(
            [
                'id' => 15,
                'height' => 700,
                'pagination' => 25,
                'limit' => 10,
                'layout' => 'listcard',
                'loading' => 'lazy',
                'fields' => ['Nom', 'Prenom', 'Email'],
                'actions' => ['detail', 'edit', 'export'],
                'sort' => 'Nom|Prenom',
                'sort_direction' => 'asc|asc',
                'title' => 'Liste des inscrits',
                'labels_set' => true,
                'hide_title' => false,
                'output' => 'list',
                'offset' => 0,
                'card' => '',
                'w' => '',
            ],
            EmbedOptionsService::resolve($attributes)
        );
    }

    public function testDefaultsAreStable(): void
    {
        self::assertSame(
            [
                'id' => 7,
                'height' => 240,
                'pagination' => null,
                'limit' => null,
                'layout' => '',
                'loading' => 'lazy',
                'fields' => [],
                'actions' => [],
                'sort' => '',
                'sort_direction' => 'asc',
                'title' => '',
                'labels_set' => false,
                'hide_title' => false,
                'output' => 'list',
                'offset' => 0,
                'card' => '',
                'w' => '',
            ],
            EmbedOptionsService::resolve(['id' => '7'])
        );
    }

    public function testCardWidthsAreStrictAndRequireACard(): void
    {
        foreach (['33', '66', '100'] as $width) {
            self::assertSame($width, EmbedOptionsService::resolve([
                'id' => '15',
                'card' => 'v1',
                'w' => $width,
            ])['w']);
        }

        self::assertSame(
            ['w'],
            array_column(EmbedOptionsService::validationErrors(['id' => '15', 'w' => '66']), 'parameter')
        );
        self::assertSame(
            ['w'],
            array_column(EmbedOptionsService::validationErrors(['id' => '15', 'card' => 'v1', 'w' => '50']), 'parameter')
        );

        $syntax = TagSyntaxService::parse('id=15 card=v1 w="66"');
        self::assertSame(
            ['w_syntax'],
            array_column(EmbedOptionsService::validationErrors($syntax['attributes'], $syntax['quoted']), 'detail')
        );

        self::assertStringContainsString(
            'class="cb-card cb-card-v1 cb-card-w66"',
            ContentCardService::render('Content', 'v1', 'Title', '66')
        );
    }

    public function testHideTitleExplicitlyHidesTheVisibleTitle(): void
    {
        $options = EmbedOptionsService::resolve(
            TagSyntaxService::parseAttributes('id=15 labels="title=Registration list" hide="title"')
        );

        self::assertTrue($options['labels_set']);
        self::assertSame('Registration list', $options['title']);
        self::assertTrue($options['hide_title']);

        $extension = file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cblist/src/Extension/ContentbuilderngList.php'
        );
        self::assertIsString($extension);
        self::assertStringContainsString(
            "\$options['labels_set'] && \$options['title'] !== ''",
            $extension,
            'A hidden visible title must retain an accessible iframe title.'
        );
        self::assertStringContainsString("\$options['labels_set'] || \$options['hide_title']", $extension);
        self::assertStringContainsString("!\$options['hide_title'] && \$options['labels_set']", $extension);

        $template = file_get_contents(self::ROOT . '/site/tmpl/list/default.php');
        self::assertIsString($template);
        self::assertStringContainsString('if ($embeddedListTitleProvided) :', $template);
        self::assertStringContainsString('if ($embeddedListTitle !== \'\') :', $template);
    }

    public function testSharedCardTitlesSupportHeadingAndRemSuffixes(): void
    {
        self::assertStringContainsString(
            '<h4 class="cb-card-header">Departments</h4>',
            ContentCardService::render('Content', 'h1', 'Departments')
        );
        self::assertStringContainsString(
            '<h2 class="cb-card-header">Departments</h2>',
            ContentCardService::render('Content', 'h1', ' Departments | H2 ')
        );
        self::assertStringContainsString(
            '<h4 class="cb-card-header" style="font-size:1.25rem">Departments</h4>',
            ContentCardService::render('Content', 'h1', 'Departments | REM1.25')
        );
        self::assertStringContainsString(
            '<h4 class="cb-card-header">Departments | large</h4>',
            ContentCardService::render('Content', 'h1', 'Departments | large')
        );
        self::assertStringContainsString(
            '<h6 class="cb-card-header">North | Departments</h6>',
            ContentCardService::render('Content', 'h1', 'North | Departments | h6')
        );
    }

    public function testLabelsTreatHideAsOrdinaryTitleAndHideTitleIsCaseInsensitive(): void
    {
        foreach (['hide', 'HIDE', ' Hide '] as $value) {
            $options = EmbedOptionsService::resolve(['id' => '15', 'labels' => 'title=' . $value]);

            self::assertTrue($options['labels_set']);
            self::assertSame(trim($value), $options['title']);
            self::assertFalse($options['hide_title']);
        }

        foreach (['title', 'TITLE', ' Title ', 'title|TITLE'] as $value) {
            self::assertTrue(EmbedOptionsService::resolve(['id' => '15', 'hide' => $value])['hide_title']);
        }
    }

    public function testHideAcceptsOnlyTitleAndRejectsAnExplicitEmptyValue(): void
    {
        foreach (['', 'total', 'title|graph', 'title,total'] as $value) {
            $errors = EmbedOptionsService::validationErrors(['id' => '15', 'hide' => $value]);

            self::assertContains('hide', array_column($errors, 'parameter'));
        }
    }

    public function testLabelsAcceptOnlyOneNonEmptyTitleMapping(): void
    {
        self::assertSame([], EmbedOptionsService::validationErrors([
            'id' => '15',
            'labels' => ' TITLE = Registration list ',
        ]));

        foreach (['title=', 'subtitle=List', 'title=One;title=Two', 'List'] as $labels) {
            $errors = EmbedOptionsService::validationErrors(['id' => '15', 'labels' => $labels]);

            self::assertSame(['labels'], array_column($errors, 'parameter'));
            self::assertSame(['labels'], array_column($errors, 'detail'));
        }
    }

    public function testRemovedTitleOptionPointsToLabelsSyntax(): void
    {
        self::assertSame(
            [[
                'code' => 'removed_option',
                'parameter' => 'title',
                'value' => 'Registration list',
                'detail' => 'labels_title',
            ]],
            EmbedOptionsService::validationErrors(['id' => '15', 'title' => 'Registration list'])
        );
    }

    public function testEveryInvalidParameterIsReportedWithItsValue(): void
    {
        self::assertSame(
            [
                [
                    'code' => 'unknown_option',
                    'parameter' => 'headers',
                    'value' => 'Noms',
                    'detail' => '',
                ],
                [
                    'code' => 'invalid_value',
                    'parameter' => 'loading',
                    'value' => 'automatic',
                    'detail' => 'loading',
                ],
                [
                    'code' => 'unknown_action',
                    'parameter' => 'actions',
                    'value' => 'teleport',
                    'detail' => 'actions',
                ],
                [
                    'code' => 'invalid_value',
                    'parameter' => 'dir',
                    'value' => 'asce',
                    'detail' => 'dir',
                ],
            ],
            EmbedOptionsService::validationErrors([
                'id' => '15',
                'headers' => 'Noms',
                'pagination' => '0',
                'loading' => 'automatic',
                'actions' => 'teleport',
                'dir' => 'asce',
            ])
        );
    }

    public function testSingleSortDirectionAppliesToEverySortField(): void
    {
        self::assertSame(
            'asc|asc|asc',
            EmbedOptionsService::resolve([
                'id' => '15',
                'sort' => 'Field 1|Field 2| Field 3',
                'dir' => 'asc',
            ])['sort_direction']
        );
        self::assertSame(
            'desc|desc|desc',
            EmbedOptionsService::resolve([
                'id' => '15',
                'sort' => 'Field 1|Field 2|Field 3',
                'dir' => 'desc',
            ])['sort_direction']
        );
    }

    public function testTagPatternAllowsClosingBraceInsideQuotedLabels(): void
    {
        $tag = '{CBList id=15 labels="title=Registrations } archived"}';

        self::assertSame(1, preg_match(TagSyntaxService::TAG_PATTERN, $tag, $matches));
        self::assertSame($tag, $matches[0]);
        self::assertSame(
            'title=Registrations } archived',
            TagSyntaxService::parseAttributes((string) $matches[1])['labels']
        );
    }

    public function testNumericOptionsRequireUnquotedValues(): void
    {
        $valid = TagSyntaxService::parse('id=15 height=700 pagination=20 limit=10');
        self::assertSame([], EmbedOptionsService::validationErrors($valid['attributes'], $valid['quoted']));
        $validOffset = TagSyntaxService::parse('id=15 fields=Nom output=value offset=1');
        self::assertSame([], EmbedOptionsService::validationErrors($validOffset['attributes'], $validOffset['quoted']));

        $invalid = TagSyntaxService::parse('id="15" height=\'700\' pagination="20" limit=\'10\'');
        self::assertSame(
            ['id_syntax', 'height_syntax', 'pagination_syntax', 'limit_syntax'],
            array_column(
                EmbedOptionsService::validationErrors($invalid['attributes'], $invalid['quoted']),
                'detail'
            )
        );
        $invalidOffset = TagSyntaxService::parse('id=15 fields=Nom output=value offset="1"');
        self::assertContains(
            'offset_syntax',
            array_column(
                EmbedOptionsService::validationErrors($invalidOffset['attributes'], $invalidOffset['quoted']),
                'detail'
            )
        );
    }

    #[DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRejected(array $attributes): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EmbedOptionsService::resolve($attributes);
    }

    public static function invalidOptionsProvider(): array
    {
        return [
            'missing id' => [[]],
            'non-positive id' => [['id' => '0']],
            'mixed id' => [['id' => '25foo']],
            'height too small' => [['id' => '15', 'height' => '239']],
            'height too large' => [['id' => '15', 'height' => '5001']],
            'limit zero' => [['id' => '15', 'limit' => '0']],
            'limit too large' => [['id' => '15', 'limit' => '5001']],
            'limit mixed' => [['id' => '15', 'limit' => '1A']],
            'pagination negative' => [['id' => '15', 'pagination' => '-1']],
            'offset without value output' => [['id' => '15', 'offset' => '1']],
            'value output without field' => [['id' => '15', 'output' => 'value']],
            'value output with two fields' => [['id' => '15', 'fields' => 'Nom|Email', 'output' => 'value']],
            'value output with presentation option' => [['id' => '15', 'fields' => 'Nom', 'output' => 'value', 'pagination' => '0']],
            'unknown output' => [['id' => '15', 'fields' => 'Nom', 'output' => 'raw']],
            'invalid layout' => [['id' => '15', 'layout' => '../default']],
            'invalid loading' => [['id' => '15', 'loading' => 'automatic']],
            'fields comma separator rejected' => [['id' => '15', 'fields' => 'Name,Email']],
            'fields semicolon separator rejected' => [['id' => '15', 'fields' => 'Name;Email']],
            'invalid field control character' => [['id' => '15', 'fields' => "Name|\x01Email"]],
            'actions comma separator rejected' => [['id' => '15', 'actions' => 'search,export']],
            'actions semicolon separator rejected' => [['id' => '15', 'actions' => 'search;export']],
            'unknown action' => [['id' => '15', 'actions' => 'search|teleport']],
            'none combined with an action' => [['id' => '15', 'actions' => 'none|detail']],
            'sort direction count mismatch' => [[
                'id' => '15',
                'sort' => 'Field 1|Field 2|Field 3',
                'dir' => 'asc|desc',
            ]],
        ];
    }

    public function testValueOutputDefaultsAndPaginationZeroAreResolved(): void
    {
        $value = EmbedOptionsService::resolve([
            'id' => '15',
            'fields' => 'Nom',
            'output' => 'value',
        ]);

        self::assertSame('value', $value['output']);
        self::assertSame('ID', $value['sort']);
        self::assertSame('desc', $value['sort_direction']);
        self::assertSame(0, $value['offset']);
        self::assertSame(0, EmbedOptionsService::resolve(['id' => '15', 'pagination' => '0'])['pagination']);
    }

    public function testEmbeddedFieldFilterOnlyReducesVisibleColumns(): void
    {
        self::assertSame(
            [12, 14, 15],
            EmbeddedListFieldFilterService::filter(
                [11, 12, 13, 14, 15],
                [
                    11 => 'id',
                    12 => 'name',
                    13 => 'phone',
                    14 => 'email',
                    15 => 'town',
                    99 => 'hidden',
                ],
                'name|email|15'
            )
        );
    }

    public function testEmbeddedFieldFilterRejectsUnknownField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown_field:Missing');

        EmbeddedListFieldFilterService::filter(
            [11],
            [11 => 'id'],
            'Missing'
        );
    }

    public function testEmbeddedFieldFilterAcceptsExistingElementsThatAreNotVisible(): void
    {
        self::assertSame(
            [12, 14],
            EmbeddedListFieldFilterService::filter(
                [12, 14],
                [
                    12 => 'Nom',
                    13 => 'Prenom',
                    14 => 'Email',
                    15 => 'Telephone',
                ],
                'Nom|Prenom|Email|Telephone'
            )
        );

        self::assertSame(
            [
                'columns' => [12, 14],
                'unknown' => ['Adresse'],
            ],
            EmbeddedListFieldFilterService::matchFieldSelectors(
                [12, 14],
                [12 => 'Nom', 13 => 'Prenom', 14 => 'Email', 15 => 'Telephone'],
                'Nom|Prenom|Email|Telephone|Adresse'
            )
        );
    }

    public function testSortMatchingStillRejectsElementsThatAreNotVisible(): void
    {
        self::assertSame(
            [
                'columns' => [12],
                'unknown' => ['Prenom'],
            ],
            EmbeddedListFieldFilterService::matchSelectors(
                [12],
                [12 => 'Nom', 13 => 'Prenom'],
                'Nom|Prenom'
            )
        );
    }

    public function testEmbeddedFieldMatchingKeepsOnlyRequestedValidColumnsAndReportsEveryUnknownOne(): void
    {
        self::assertSame(
            [
                'columns' => [12, 14],
                'unknown' => ['Prénom', 'prenom', 'EMAIL', 'Courriels'],
            ],
            EmbeddedListFieldFilterService::matchSelectors(
                [11, 12, 13, 14],
                [11 => 'Id', 12 => 'Prenom', 13 => 'Telephone', 14 => 'Email'],
                'Prenom|Prénom|prenom|Email|EMAIL|Courriels'
            )
        );
    }

    public function testEmbeddedFieldFilterPreservesSelectorOrder(): void
    {
        self::assertSame(
            ['name', 'town'],
            EmbeddedListFieldFilterService::filter(
                ['town', 'email', 'name'],
                ['town' => 'town', 'email' => 'email', 'name' => 'name'],
                'name|town'
            )
        );
    }

    public function testFieldSelectorsRejectCommaAndSemicolon(): void
    {
        foreach (['Name,Email', 'Name;Email'] as $rawSelectors) {
            try {
                EmbeddedListFieldFilterService::parseSelectors($rawSelectors);
                self::fail('Expected InvalidArgumentException for: ' . $rawSelectors);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('fields', $exception->getMessage());
            }
        }
    }

    public function testActionSelectorsAreParsedAndDeduplicated(): void
    {
        self::assertSame(
            ['search', 'detail', 'export'],
            EmbeddedListActionFilterService::parseActions('Search|Detail|EXPORT|detail')
        );
        self::assertSame([], EmbeddedListActionFilterService::parseActions(''));
        self::assertSame(['none'], EmbeddedListActionFilterService::parseActions('NONE'));
    }

    public function testActionSelectorsRejectCommaAndSemicolon(): void
    {
        foreach (['search,export', 'search;export'] as $rawActions) {
            try {
                EmbeddedListActionFilterService::parseActions($rawActions);
                self::fail('Expected InvalidArgumentException for: ' . $rawActions);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('actions', $exception->getMessage());
            }
        }
    }

    public function testKnownActionsCoverTheExhaustiveVocabulary(): void
    {
        self::assertSame(
            [
                'search',
                'state',
                'publish',
                'language',
                'new',
                'edit',
                'delete',
                'export',
                'rating',
                'detail',
                'print',
            ],
            EmbeddedListActionFilterService::ACTIONS
        );

        foreach (EmbeddedListActionFilterService::ACTIONS as $action) {
            self::assertTrue(EmbeddedListActionFilterService::isKnownAction($action));
        }
        self::assertFalse(EmbeddedListActionFilterService::isKnownAction('teleport'));
    }

    public function testIsAllowedIsUnrestrictedWhenAllowListIsEmpty(): void
    {
        self::assertTrue(EmbeddedListActionFilterService::isAllowed('delete', []));
        self::assertTrue(EmbeddedListActionFilterService::isAllowed('anything', []));
    }

    public function testIsAllowedOnlyAllowsListedActions(): void
    {
        $allowed = ['search', 'detail'];

        self::assertTrue(EmbeddedListActionFilterService::isAllowed('search', $allowed));
        self::assertTrue(EmbeddedListActionFilterService::isAllowed('detail', $allowed));
        self::assertFalse(EmbeddedListActionFilterService::isAllowed('delete', $allowed));
        self::assertFalse(EmbeddedListActionFilterService::isAllowed('edit', $allowed));
    }

    public function testNoneDisablesEveryEmbeddedActionAndCannotBeCombined(): void
    {
        self::assertTrue(EmbeddedListActionFilterService::isKnownAction('none'));
        self::assertFalse(EmbeddedListActionFilterService::isAllowed('search', ['none']));
        self::assertFalse(EmbeddedListActionFilterService::isAllowed('detail', ['none']));

        $this->expectException(\InvalidArgumentException::class);
        EmbeddedListActionFilterService::parseActions('none|detail');
    }

    public function testEmptyTopToolbarIsNotRendered(): void
    {
        $template = (string) file_get_contents(self::ROOT . '/site/tmpl/list/default.php');

        self::assertStringContainsString('$hasTopBarContent = $language_allowed', $template);
        self::assertStringContainsString('<?php if ($hasTopBarContent) : ?>', $template);
        self::assertStringContainsString(
            '($this->show_records_per_page && !$embeddedListHidePagination)',
            $template
        );
    }

    public function testDebugStateReportsOnlyTheRequestedActions(): void
    {
        $state = EmbeddedListActionFilterService::debugState(
            ['search', 'detail', 'delete'],
            ['search', 'detail']
        );

        self::assertSame(
            ['search' => true, 'detail' => true, 'delete' => false],
            $state
        );
    }

    public function testDebugStateIsUnrestrictedWhenAllowListIsEmpty(): void
    {
        $state = EmbeddedListActionFilterService::debugState(['search', 'delete'], []);

        self::assertSame(['search' => true, 'delete' => true], $state);
    }

    public function testEmbeddedRequestRequiresExplicitPublicContext(): void
    {
        self::assertTrue(
            EmbeddedListFieldFilterService::isEmbeddedRequest(
                EmbeddedListFieldFilterService::REQUEST_CONTEXT
            )
        );
        self::assertFalse(EmbeddedListFieldFilterService::isEmbeddedRequest('1'));
        self::assertFalse(EmbeddedListFieldFilterService::isEmbeddedRequest(''));
    }

    public function testEmbeddedContextQueryPreservesFieldsAndActions(): void
    {
        self::assertSame(
            '&cblist_embed=content-plugin&cblist_fields=Name%7CEmail&cblist_actions=detail%7Cedit',
            EmbeddedListContextService::buildQuery(
                EmbeddedListFieldFilterService::REQUEST_CONTEXT,
                'Name|Email',
                'detail|edit'
            )
        );
        self::assertSame(
            '',
            EmbeddedListContextService::buildQuery('invalid-context', 'Name', 'detail')
        );
        self::assertSame(
            '&cblist_embed=content-plugin&cblist_hide_pagination=1',
            EmbeddedListContextService::buildQuery(
                EmbeddedListFieldFilterService::REQUEST_CONTEXT,
                '',
                '',
                '',
                '1'
            )
        );
    }

    public function testEmbeddedContextIsPreservedAcrossMutations(): void
    {
        $listController = \file_get_contents(self::ROOT . '/site/src/Controller/ListController.php');
        $editController = \file_get_contents(self::ROOT . '/site/src/Controller/EditController.php');
        $listTemplate = \file_get_contents(self::ROOT . '/site/tmpl/list/default.php');
        $detailsTemplate = \file_get_contents(self::ROOT . '/site/tmpl/details/default.php');
        $editTemplate = \file_get_contents(self::ROOT . '/site/tmpl/edit/default.php');
        $extension = \file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cblist/src/Extension/ContentbuilderngList.php'
        );

        self::assertIsString($listController);
        self::assertIsString($editController);
        self::assertIsString($listTemplate);
        self::assertIsString($detailsTemplate);
        self::assertIsString($editTemplate);
        self::assertIsString($extension);
        self::assertStringContainsString('private function buildEmbeddedListQuery(): string', $listController);
        self::assertStringContainsString('private function buildEmbeddedListQuery(): string', $editController);
        self::assertStringContainsString('name="cblist_actions"', $listTemplate);
        self::assertStringContainsString('name="cblist_limit"', $listTemplate);
        self::assertStringContainsString("getInt('cblist_limit', 0)", $listController);
        self::assertStringContainsString("getInt('cblist_limit', 0)", $editController);
        self::assertStringContainsString('foreach ($this->embedded_list_errors as $embeddedListError)', $listTemplate);
        self::assertStringContainsString(
            'if ($this->embedded_list_errors !== []) { return; }',
            $listTemplate,
            'An invalid embedded CBList must render its errors without rendering the list below them.'
        );
        self::assertStringContainsString('data-cblist-errors-only', $listTemplate);
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $listTemplate);

        $helpService = \file_get_contents(
            self::ROOT . '/site/src/Service/EmbeddedListHelpService.php'
        );
        self::assertIsString($helpService);
        self::assertStringContainsString('task=cblisthelp.display', $helpService);
        self::assertStringNotContainsString("authorise('core.manage', 'com_plugins')", $helpService);
        self::assertStringNotContainsString('administrator/index.php', $helpService);
        self::assertStringContainsString('EmbeddedListHelpService::syntaxUrl()', $extension);

        $publicHelpView = \file_get_contents(
            self::ROOT . '/site/src/View/Cblisthelp/HtmlView.php'
        );
        $publicHelpTemplate = \file_get_contents(
            self::ROOT . '/site/tmpl/cblisthelp/default.php'
        );
        self::assertIsString($publicHelpView);
        self::assertIsString($publicHelpTemplate);
        self::assertStringContainsString('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_TEXT', $publicHelpView);
        self::assertStringContainsString('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_FIELDS_TEXT', $publicHelpView);
        self::assertStringContainsString('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_ACTIONS_TEXT', $publicHelpView);
        self::assertStringContainsString(
            'foreach ($this->sections as $sectionIndex => $section)',
            $publicHelpTemplate
        );

        $embedScript = \file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cblist/media/js/cblist.js'
        );
        self::assertIsString($embedScript);
        self::assertStringContainsString(
            "document.querySelector('[data-cblist-errors-only]')",
            $embedScript,
            'An error-only iframe must shrink to its message instead of keeping the configured minimum height.'
        );
        $heightResetPosition = strpos($embedScript, "frame.style.height = '0px';");
        $heightMeasurementPosition = strpos($embedScript, 'body.scrollHeight');
        self::assertNotFalse(
            $heightResetPosition,
            'The iframe viewport must be reset before measuring a shorter paginated result.'
        );
        self::assertNotFalse($heightMeasurementPosition);
        self::assertLessThan(
            $heightMeasurementPosition,
            $heightResetPosition,
            'The height reset must happen before scrollHeight is measured.'
        );
        self::assertStringContainsString("getString('cblist_fields', '')", $editTemplate);
        self::assertStringContainsString('foreach ($embeddedListParams as $embeddedListName', $detailsTemplate);
        self::assertStringContainsString('foreach ($embeddedListParams as $embeddedListName', $editTemplate);
    }

    public function testLimitCapsPaginationAndExportWithoutReplacingPageSize(): void
    {
        $model = (string) file_get_contents(self::ROOT . '/site/src/Model/ListModel.php');
        $export = (string) file_get_contents(self::ROOT . '/site/src/Model/ExportModel.php');
        $layout = (string) file_get_contents(self::ROOT . '/site/layouts/contentbuilderng/list_pagination.php');
        $template = (string) file_get_contents(self::ROOT . '/site/tmpl/list/default.php');
        $extension = (string) file_get_contents(
            self::ROOT . '/plugins/content/contentbuilderng_cblist/src/Extension/ContentbuilderngList.php'
        );
        $context = (string) file_get_contents(
            self::ROOT . '/site/src/Service/EmbeddedListContextService.php'
        );

        self::assertStringContainsString("\$query['cblist_limit'] = \$options['limit'];", $extension);
        self::assertStringContainsString("min((int) \$this->_total, \$limit)", $model);
        self::assertStringContainsString("\$limit - (int) \$this->getState('list.start')", $model);
        self::assertStringContainsString("getInt('cblist_limit', 0)", $export);
        self::assertStringContainsString("'cblist_limit' => \$limit", $context);
        self::assertStringContainsString('COM_CONTENTBUILDERNG_LIST_PAGINATION_SUMMARY_DISPLAYED', $layout);
        self::assertStringContainsString("array_merge(\$exportQueryParams, \$embeddedListParams", $template);
        self::assertStringNotContainsString("\$query['list'] = ['limit' => \$options['limit']]", $extension);
    }

    public function testPluginIsBundledAndInstalled(): void
    {
        $installer = \file_get_contents(
            self::ROOT . '/admin/src/Service/PluginInstallerService.php'
        );
        $validator = \file_get_contents(self::ROOT . '/scripts/validate-package.sh');

        self::assertIsString($installer);
        self::assertIsString($validator);
        self::assertStringContainsString("'contentbuilderng_cblist'", $installer);
        self::assertStringContainsString(
            'plugins/content/contentbuilderng_cblist/contentbuilderng_cblist.xml',
            $validator
        );
    }

    #[DataProvider('languageProvider')]
    public function testTranslationsAreComplete(string $language): void
    {
        $translations = \parse_ini_file(
            self::ROOT
            . '/plugins/content/contentbuilderng_cblist/language/'
            . $language
            . '/plg_content_contentbuilderng_cblist.ini'
        );
        self::assertIsArray($translations);

        foreach (
            [
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_XML_DESCRIPTION',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_LABEL',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_TEXT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_FIELDS_LABEL',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_FIELDS_TEXT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_VALUE_LABEL',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_VALUE_TEXT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_ACTIONS_LABEL',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_ACTIONS_TEXT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_NONE_LABEL',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_NONE_TEXT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_VALIDATION_INTRO',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_SYNTAX_HELP',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EMPTY_VALUE',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_OPTION',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_REMOVED_OPTION',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_INVALID_OPTION_VALUE',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_ACTION',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_VIEW',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_ID',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_NUMERIC_SYNTAX',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_HEIGHT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_PAGINATION',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LIMIT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OFFSET',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OUTPUT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_SINGLE_FIELD',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OUTPUT_COMPATIBILITY',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OFFSET_MODE',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LAYOUT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LOADING',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_FIELDS',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LABELS',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LABELS_TITLE',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_HIDE',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_ACTIONS',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_SORT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_DIR',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_DIR_COUNT',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_IFRAME_TITLE',
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_OPEN_LIST',
            ] as $key
        ) {
            self::assertArrayHasKey($key, $translations, $language . ': ' . $key);
            self::assertNotSame('', $translations[$key], $language . ': ' . $key);
        }
    }

    #[DataProvider('languageProvider')]
    public function testAllErrorMessagesAreCompleteAndKeepMatchingPlaceholders(string $language): void
    {
        $pluginTranslations = \parse_ini_file(
            self::ROOT
            . '/plugins/content/contentbuilderng_cblist/language/'
            . $language
            . '/plg_content_contentbuilderng_cblist.ini'
        );
        $siteTranslations = \parse_ini_file(
            self::ROOT . '/site/language/' . $language . '/com_contentbuilderng.ini'
        );
        self::assertIsArray($pluginTranslations);
        self::assertIsArray($siteTranslations);

        $expectedPlaceholders = [
            'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_OPTION' => 2,
            'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_REMOVED_OPTION' => 2,
            'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_INVALID_OPTION_VALUE' => 3,
            'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_ACTION' => 1,
            'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_VIEW' => 1,
            'COM_CONTENTBUILDERNG_CBLIST_UNKNOWN_FIELD' => 1,
            'COM_CONTENTBUILDERNG_CBLIST_UNKNOWN_SORT_FIELD' => 1,
        ];

        foreach ($expectedPlaceholders as $key => $expectedCount) {
            $translations = str_starts_with($key, 'PLG_') ? $pluginTranslations : $siteTranslations;
            self::assertArrayHasKey($key, $translations, $language . ': ' . $key);
            preg_match_all('/%(?:\d+\$)?[sd]/', (string) $translations[$key], $matches);
            self::assertCount($expectedCount, $matches[0], $language . ': ' . $key);
        }

        foreach (
            [
                'COM_CONTENTBUILDERNG_CBLIST_VALIDATION_INTRO',
                'COM_CONTENTBUILDERNG_CBLIST_SYNTAX_HELP',
                'COM_CONTENTBUILDERNG_CBLIST_INVALID_FIELDS',
            ] as $key
        ) {
            self::assertArrayHasKey($key, $siteTranslations, $language . ': ' . $key);
            self::assertNotSame('', $siteTranslations[$key], $language . ': ' . $key);
        }

        $allErrorText = implode(' ', array_merge($pluginTranslations, $siteTranslations));
        foreach (
            [
                'configured view ordering is used instead',
                'tri configuré dans la vue est utilisé',
                'konfigurierte Sortierung verwendet',
            ] as $obsoleteWording
        ) {
            self::assertStringNotContainsString($obsoleteWording, $allErrorText, $language);
        }
    }

    #[DataProvider('languageProvider')]
    public function testTranslatedHelpExamplesUseValidSyntax(string $language): void
    {
        $translations = \parse_ini_file(
            self::ROOT
            . '/plugins/content/contentbuilderng_cblist/language/'
            . $language
            . '/plg_content_contentbuilderng_cblist.ini'
        );
        self::assertIsArray($translations);

        $help = implode('', [
            $translations['PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_TEXT'],
            $translations['PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_FIELDS_TEXT'],
            $translations['PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_VALUE_TEXT'],
            $translations['PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_ACTIONS_TEXT'],
            $translations['PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_NONE_TEXT'],
        ]);
        self::assertStringNotContainsString('{CBList id=25', $help, $language);
        self::assertStringContainsString('Thoth', $help, $language . ': missing default theme explanation');

        $actionsHelp = $translations['PLG_CONTENT_CONTENTBUILDERNG_CBLIST_HELP_ACTIONS_TEXT'];
        self::assertStringContainsString('<ul>', $actionsHelp, $language . ': actions must be displayed vertically');
        foreach (EmbeddedListActionFilterService::ACTIONS as $action) {
            self::assertStringContainsString('<code>' . $action . '</code>', $actionsHelp, $language);
        }

        preg_match_all(TagSyntaxService::TAG_PATTERN, $help, $matches);
        self::assertNotEmpty($matches[1], $language . ': no documented CBList example found');
        self::assertDoesNotMatchRegularExpression('/\{CBList[^}]*\stitle=/i', $help, $language);

        foreach ($matches[1] as $rawAttributes) {
            $syntax = TagSyntaxService::parse((string) $rawAttributes);
            self::assertSame(
                15,
                EmbedOptionsService::resolve(
                    $syntax['attributes'],
                    $syntax['quoted']
                )['id'],
                $language . ': invalid documented CBList example'
            );
        }
    }

    public static function languageProvider(): array
    {
        return [
            'English' => ['en-GB'],
            'French' => ['fr-FR'],
            'German' => ['de-DE'],
        ];
    }
}
