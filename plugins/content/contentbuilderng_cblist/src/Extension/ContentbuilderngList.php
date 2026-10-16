<?php

declare(strict_types=1);

namespace CB\Plugin\Content\ContentbuilderngList\Extension;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Site\Service\EmbeddedListFieldFilterService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListHelpService;
use CB\Component\Contentbuilderng\Site\Service\EmbeddedListValueService;
use CB\Component\Contentbuilderng\Site\Service\ContentCardService;
use CB\Plugin\Content\ContentbuilderngList\Service\EmbedOptionsService;
use CB\Plugin\Content\ContentbuilderngList\Service\TagSyntaxService;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

final class ContentbuilderngList extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    private static int $instance = 0;
    private static bool $assetRegistryLoaded = false;
    /** @var array<int, bool> */
    private static array $viewExistence = [];

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(EventInterface $event): void
    {
        $article = $event->getArgument('subject');

        if (
            !is_object($article)
            || !isset($article->text)
            || stripos((string) $article->text, '{CBList') === false
        ) {
            return;
        }

        $app = Factory::getApplication();
        if (
            !$app instanceof SiteApplication
            || $app->getInput()->getCmd('option') === 'com_contentbuilderng'
            || EmbeddedListFieldFilterService::isEmbeddedRequest($app->getInput()->getCmd('cblist_embed', ''))
        ) {
            return;
        }

        $article->text = preg_replace_callback(
            TagSyntaxService::TAG_PATTERN,
            fn(array $match): string => $this->renderTag($match[1], $app),
            (string) $article->text
        );
    }

    private function renderTag(string $rawAttributes, SiteApplication $app): string
    {
        $syntax = TagSyntaxService::parse($rawAttributes);
        $attributes = $syntax['attributes'];
        $errors = EmbedOptionsService::validationErrors($attributes, $syntax['quoted']);

        if ($errors !== []) {
            return $this->renderValidationErrors($errors, $app);
        }

        $options = EmbedOptionsService::resolve($attributes, $syntax['quoted']);
        if (!$this->viewExists($options['id'])) {
            return $this->renderValidationErrors([[
                'code' => 'unknown_view',
                'parameter' => 'id',
                'value' => (string) $options['id'],
                'detail' => '',
            ]], $app);
        }

        if ($options['output'] === 'value') {
            $result = EmbeddedListValueService::resolve($app, $options);
            if ($result['errors'] !== []) {
                return $this->renderValidationErrors($result['errors'], $app);
            }

            return $result['value'] === null
                ? ''
                : htmlspecialchars(
                    html_entity_decode($result['value'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                    'UTF-8'
                );
        }

        $query = [
            'option' => 'com_contentbuilderng',
            'task' => 'list.display',
            'id' => $options['id'],
            'tmpl' => 'component',
            'cblist_embed' => EmbeddedListFieldFilterService::REQUEST_CONTEXT,
        ];

        if ($options['labels_set'] || $options['hide_title']) {
            $query['cblist_title'] = !$options['hide_title'] && $options['card'] === ''
                ? $options['title']
                : '';
            $query['cblist_title_set'] = 1;
        }

        if ($options['pagination'] === 0) {
            $query['cblist_hide_pagination'] = 1;
        } elseif ($options['pagination'] !== null) {
            $query['list'] = ['limit' => $options['pagination']];
        }
        if ($options['limit'] !== null) {
            $query['cblist_limit'] = $options['limit'];
        }
        if ($options['layout'] !== '') {
            $query['layout'] = $options['layout'];
        }
        if ($options['fields'] !== []) {
            $query['cblist_fields'] = implode('|', $options['fields']);
        }
        if ($options['actions'] !== []) {
            $query['cblist_actions'] = implode('|', $options['actions']);
        }
        if ($options['sort'] !== '') {
            $query['cblist_sort'] = $options['sort'];
            $query['cblist_dir'] = $options['sort_direction'];
        }

        $url = Route::_('index.php?' . http_build_query($query), false);
        $title = $options['labels_set'] && $options['title'] !== ''
            ? $options['title']
            : Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_IFRAME_TITLE', $options['id']);
        $openLabel = Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_OPEN_LIST');
        $instanceId = 'cblist-frame-' . (++self::$instance);

        $this->loadAssets($app, $options['card'] !== '');

        $embed = '<div class="cblist-embed">'
            . '<iframe'
            . ' id="' . $instanceId . '"'
            . ' class="cblist-embed__frame"'
            . ' src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
            . ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"'
            . ' loading="' . $options['loading'] . '"'
            . ' data-cblist-min-height="' . $options['height'] . '"'
            . ' style="height:' . $options['height'] . 'px"'
            . '></iframe>'
            . '<noscript><p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($openLabel, ENT_QUOTES, 'UTF-8')
            . '</a></p></noscript>'
            . '</div>';

        return ContentCardService::render(
            $embed,
            $options['card'],
            !$options['hide_title'] && $options['labels_set'] ? $options['title'] : '',
            $options['w']
        );
    }

    /**
     * @param list<array{code: string, parameter: string, value: string, detail: string}> $errors
     */
    private function renderValidationErrors(array $errors, SiteApplication $app): string
    {
        $items = array_map(
            fn(array $error): string => '<li>'
                . htmlspecialchars($this->validationMessage($error), ENT_QUOTES, 'UTF-8')
                . '</li>',
            $errors
        );

        $helpUrl = EmbeddedListHelpService::syntaxUrl();
        $helpLink = $helpUrl !== ''
            ? '<a class="ms-3" href="' . htmlspecialchars($helpUrl, ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener noreferrer">'
                . '<span class="fa-solid fa-circle-question me-1" aria-hidden="true"></span>'
                . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_SYNTAX_HELP'), ENT_QUOTES, 'UTF-8')
                . '</a>'
            : '';

        return '<div class="alert alert-warning" role="alert">'
            . '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><strong>'
            . '<span class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></span>'
            . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_VALIDATION_INTRO'), ENT_QUOTES, 'UTF-8')
            . '</strong>' . $helpLink . '</div><ul class="mb-0 mt-2">'
            . implode('', $items)
            . '</ul></div>';
    }

    /**
     * @param array{code: string, parameter: string, value: string, detail: string} $error
     */
    private function validationMessage(array $error): string
    {
        $value = $error['value'] !== ''
            ? $error['value']
            : Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EMPTY_VALUE');

        if ($error['code'] === 'unknown_option') {
            return Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_OPTION',
                $error['parameter'],
                $value
            );
        }

        if ($error['code'] === 'removed_option') {
            return Text::sprintf(
                'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_REMOVED_OPTION',
                $error['parameter'],
                Text::_('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LABELS_TITLE')
            );
        }

        if ($error['code'] === 'unknown_action') {
            return Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_ACTION', $value);
        }

        if ($error['code'] === 'unknown_view') {
            return Text::sprintf('PLG_CONTENT_CONTENTBUILDERNG_CBLIST_UNKNOWN_VIEW', $value);
        }

        $detailKey = match ($error['detail']) {
            'id_syntax', 'height_syntax', 'pagination_syntax', 'limit_syntax', 'offset_syntax', 'w_syntax'
                => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_NUMERIC_SYNTAX',
            'id' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_ID',
            'height' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_HEIGHT',
            'pagination' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_PAGINATION',
            'limit' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LIMIT',
            'offset' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OFFSET',
            'output' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OUTPUT',
            'card' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_CARD',
            'w' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_W',
            'w_requires_card' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_W_CARD',
            'fields_single' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_SINGLE_FIELD',
            'output_incompatible' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OUTPUT_COMPATIBILITY',
            'offset_requires_output' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_OFFSET_MODE',
            'layout' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LAYOUT',
            'loading' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LOADING',
            'fields' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_FIELDS',
            'labels' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_LABELS',
            'hide' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_HIDE',
            'actions' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_ACTIONS',
            'sort' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_SORT',
            'dir_count' => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_DIR_COUNT',
            default => 'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_EXPECTED_DIR',
        };

        return Text::sprintf(
            'PLG_CONTENT_CONTENTBUILDERNG_CBLIST_INVALID_OPTION_VALUE',
            $error['parameter'],
            $value,
            Text::_($detailKey)
        );
    }

    private function viewExists(int $id): bool
    {
        if (array_key_exists($id, self::$viewExistence)) {
            return self::$viewExistence[$id];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__contentbuilderng_forms'))
            ->where($db->quoteName('id') . ' = ' . $id);
        $db->setQuery($query);

        return self::$viewExistence[$id] = (int) $db->loadResult() === 1;
    }

    private function loadAssets(SiteApplication $app, bool $withCards = false): void
    {
        $assets = $this->getWebAssetManager($app);
        $assets->usePreset('plg_content_contentbuilderng_cblist.embed');
        if ($withCards) {
            $assets->getRegistry()->addExtensionRegistryFile('com_contentbuilderng');
            $assets->useStyle('com_contentbuilderng.cards');
        }
    }

    private function getWebAssetManager(SiteApplication $app): WebAssetManager
    {
        $assets = $app->getDocument()->getWebAssetManager();

        if (!self::$assetRegistryLoaded) {
            $assets->getRegistry()->addRegistryFile(
                'media/plg_content_contentbuilderng_cblist/joomla.asset.json'
            );
            self::$assetRegistryLoaded = true;
        }

        return $assets;
    }
}
