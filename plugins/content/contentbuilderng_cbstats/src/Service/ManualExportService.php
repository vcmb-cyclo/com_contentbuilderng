<?php

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

final class ManualExportService
{
    public static function isRequested(string $value): bool
    {
        return strtolower(trim($value)) === 'manual';
    }

    /**
     * @param list<array{label: string, value: int|float}> $items
     */
    public static function buildSyntax(
        array $items,
        string $output,
        string $title = '',
        string $background = ''
    ): string {
        $values = [];

        foreach ($items as $item) {
            $values[] = self::escapeLabel($item['label']) . '=' . self::formatNumber($item['value']);
        }

        $syntax = '{CBStats source=manual output=' . $output
            . ' values="' . self::escapeAttribute(implode(';', $values)) . '"';

        if ($title !== '') {
            $syntax .= ' title="' . self::escapeAttribute($title) . '"';
        }

        if ($background !== '') {
            $syntax .= ' background="' . self::escapeAttribute($background) . '"';
        }

        return $syntax . '}';
    }

    public static function escapeLabel(string $label): string
    {
        return str_replace(['\\', ';', '='], ['\\\\', '\\;', '\\='], $label);
    }

    public static function formatNumber(int|float $value): string
    {
        return floor((float) $value) === (float) $value
            ? (string) (int) $value
            : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    private static function escapeAttribute(string $value): string
    {
        return str_replace(['"', '<', '>'], ['\\"', '&lt;', '&gt;'], $value);
    }
}
