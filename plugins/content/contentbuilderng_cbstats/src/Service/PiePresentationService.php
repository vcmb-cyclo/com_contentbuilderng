<?php

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

final class PiePresentationService
{
    /**
     * @param list<array{label: string, value: int}> $fieldStats
     * @return array{
     *     total: int,
     *     items: list<array{label: string, value: int, percentage: float, percentageLabel: string, color: string}>
     * }
     */
    public static function prepare(array $fieldStats, string $locale): array
    {
        $total = array_sum(array_column($fieldStats, 'value'));
        $colors = self::getColors(count($fieldStats));
        $items = [];

        foreach ($fieldStats as $index => $item) {
            $percentage = $total > 0 ? round($item['value'] * 100 / $total, 1) : 0.0;
            $items[] = $item + [
                'percentage' => $percentage,
                'percentageLabel' => self::formatPercentage($percentage, $locale),
                'color' => $colors[$index],
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @return list<string>
     */
    private static function getColors(int $count): array
    {
        $palette = [
            '#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#0891b2',
            '#be185d', '#4d7c0f', '#c2410c', '#4338ca', '#0f766e', '#a21caf',
        ];
        $colors = [];

        for ($index = 0; $index < $count; $index++) {
            $hue = rtrim(rtrim(number_format(fmod($index * 137.508, 360.0), 3, '.', ''), '0'), '.');
            $colors[] = $palette[$index] ?? 'hsl(' . $hue . ' 68% 43%)';
        }

        return $colors;
    }

    private static function formatPercentage(float $percentage, string $locale): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 1);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1);
        $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
        $formatted = $formatter->format($percentage, \NumberFormatter::TYPE_DOUBLE);

        if ($formatted === false) {
            throw new \RuntimeException('Unable to format CBStats percentage for locale ' . $locale);
        }

        return $formatted;
    }
}
