<?php

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

final class TagSyntaxService
{
    public const TAG_PATTERN = '/\{CBStats\b([^}]*)\}/i';

    /** @return array<string, string> */
    public static function parseAttributes(string $rawAttributes): array
    {
        $attributes = [];
        $rawAttributes = self::normalizeMarkup($rawAttributes);

        preg_match_all(
            '/([A-Za-z0-9_\-\[\]]+)\s*=\s*("((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\'|([^\s]+))/u',
            $rawAttributes,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = strtolower((string) $match[1]);
            $value = '';

            foreach ([3, 4, 5] as $index) {
                if (isset($match[$index]) && (string) $match[$index] !== '') {
                    $value = preg_replace('/\\\\(["\'])/', '$1', (string) $match[$index]) ?? (string) $match[$index];
                    break;
                }
            }

            if ($key !== '') {
                $attributes[$key] = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attributes;
    }

    public static function normalizeKeyword(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @param array<string, string> $attributes
     * @return array{field: string, value: string}
     */
    public static function resolveFilter(array $attributes): array
    {
        $field = trim((string) ($attributes['field'] ?? ''));
        $filterField = trim((string) ($attributes['filter[field]'] ?? ''));
        $filterValue = trim((string) ($attributes['filter[value]'] ?? ''));
        $value = trim((string) ($attributes['value'] ?? ''));

        if ($filterField === '' && $field !== '' && $value !== '') {
            $filterField = $field;
            $filterValue = $value;
        }

        return ['field' => $filterField, 'value' => $filterValue];
    }

    public static function normalizeMarkup(string $rawAttributes): string
    {
        $rawAttributes = str_replace('&nbsp;', ' ', $rawAttributes);
        $rawAttributes = strip_tags($rawAttributes);
        $rawAttributes = html_entity_decode($rawAttributes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rawAttributes = str_replace("\u{00A0}", ' ', $rawAttributes);

        return preg_replace('/\s+/u', ' ', $rawAttributes) ?? $rawAttributes;
    }
}
