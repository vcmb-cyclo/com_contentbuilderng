<?php
/**
 * @package     ContentBuilder NG Stats
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use CB\Component\Contentbuilderng\Site\Service\StatsService;
use CB\Component\Contentbuilderng\Administrator\Service\PermissionService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

class plgContentContentbuilderng_stats extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    public function onContentPrepare(EventInterface $event): void
    {
        $article = $event->getArgument('subject');

        if (!is_object($article) || !isset($article->text) || stripos((string) $article->text, '{CBStats') === false) {
            return;
        }

        $article->text = preg_replace_callback(
            '/\{CBStats\b([^}]*)\}/i',
            fn(array $match): string => $this->renderStatsTag((string) ($match[1] ?? '')),
            (string) $article->text
        );
    }

    private function renderStatsTag(string $rawAttributes): string
    {
        $attributes = $this->parseAttributes($rawAttributes);
        $formId = (int) (($attributes['id'] ?? '') !== '' ? $attributes['id'] : $this->extractId($rawAttributes));
        $debug = stripos($rawAttributes, 'debug') !== false || $this->isEnabled((string) ($attributes['debug'] ?? '0'));
        $output = strtolower(trim((string) ($attributes['output'] ?? 'total')));
        $field = trim((string) ($attributes['field'] ?? ''));
        $filterField = trim((string) ($attributes['filter[field]'] ?? ''));
        $filterValue = trim((string) ($attributes['filter[value]'] ?? ''));
        $value = trim((string) ($attributes['value'] ?? ''));

        if ($filterField === '' && $field !== '' && $value !== '') {
            $filterField = $field;
            $filterValue = $value;
        }

        try {
            if (!class_exists(StatsService::class)) {
                $servicePath = JPATH_ROOT . '/components/com_contentbuilderng/src/Service/StatsService.php';

                if (is_file($servicePath)) {
                    require_once $servicePath;
                }
            }

            if ($formId < 1) {
                throw new \RuntimeException(
                    'Missing id. Raw: [' . $rawAttributes . '] Normalized: [' . $this->normalizeAttributes($rawAttributes) . ']'
                );
            }

            if (!$this->canViewStats($formId)) {
                throw new \RuntimeException('STATS permission denied for current user.', 403);
            }

            $payload = (new StatsService())->getStatsPayload($formId, [
                'field' => $field,
                'filter' => [
                    'field' => $filterField,
                    'value' => $filterValue,
                ],
            ]);
            $total = $payload['records']['total'] ?? null;

            if ($debug && $total === null) {
                return 'CBStats debug: missing records.total in ' . htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
            }

            if ($debug && in_array($output, ['count', 'total'], true)) {
                return 'CBStats DEBUG: ViewID=' . $formId . '; total=' . (int) ($total ?? 0) . '.';
            }

            return match ($output) {
                'table' => $this->renderTable($payload),
                'count', 'total' => (string) (int) ($total ?? 0),
                default => (string) (int) ($total ?? 0),
            };
        } catch (\Throwable $exception) {
            if ((int) $exception->getCode() === 403) {
                return $debug
                    ? 'CBStats DEBUG: field not allowed for API/Stats.'
                    : 'Statistics unavailable';
            }

            return $debug
                ? 'CBStats error: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
                : '0';
        }
    }

    private function canViewStats(int $formId): bool
    {
        try {
            $app = Factory::getApplication();
            $frontend = $app->isClient('site');
            $permissions = new PermissionService();

            if ($frontend) {
                $permissions->setPermissions($formId, 0, '_fe');

                if ($permissions->authorizeFe('stats')) {
                    return true;
                }
            }

            $permissions->setPermissions($formId);

            return $permissions->authorize('stats');
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseAttributes(string $rawAttributes): array
    {
        $attributes = [];
        $rawAttributes = $this->normalizeAttributes($rawAttributes);

        preg_match_all('/([A-Za-z0-9_\-\[\]]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s]+))/u', $rawAttributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = strtolower((string) $match[1]);
            $value = '';

            foreach ([3, 4, 5] as $index) {
                if (isset($match[$index]) && (string) $match[$index] !== '') {
                    $value = (string) $match[$index];
                    break;
                }
            }

            if ($key !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attributes;
    }

    private function extractId(string $rawAttributes): int
    {
        $rawAttributes = $this->normalizeAttributes($rawAttributes);

        if (preg_match('/\bid\s*[:=]\s*["\']?([0-9]+)/iu', $rawAttributes, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function normalizeAttributes(string $rawAttributes): string
    {
        $rawAttributes = str_replace('&nbsp;', ' ', $rawAttributes);
        $rawAttributes = html_entity_decode($rawAttributes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawAttributes = strip_tags($rawAttributes);
        $rawAttributes = str_replace("\u{00A0}", ' ', $rawAttributes);

        return preg_replace('/\s+/u', ' ', $rawAttributes) ?? $rawAttributes;
    }

    private function isEnabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function renderTable(array $payload): string
    {
        $field = (array) ($payload['field'] ?? []);
        $values = (array) ($field['values'] ?? []);

        if ($values === []) {
            return '<span class="cbstats cbstats-empty">0</span>';
        }

        $label = (string) ($field['label'] ?? $field['requested'] ?? Text::_('PLG_CONTENT_CONTENTBUILDERNG_STATS_VALUE'));
        $html = '<table class="table table-sm cbstats-table">'
            . '<thead><tr>'
            . '<th scope="col">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th scope="col">' . htmlspecialchars(Text::_('PLG_CONTENT_CONTENTBUILDERNG_STATS_TOTAL'), ENT_QUOTES, 'UTF-8') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($values as $value => $total) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td>' . (int) $total . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table>';
    }
}
