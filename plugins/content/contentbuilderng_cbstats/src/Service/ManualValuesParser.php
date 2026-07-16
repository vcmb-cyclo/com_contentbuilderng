<?php

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

final class ManualValuesParser
{
    /**
     * @return array<string, int|float>
     */
    public static function parse(string $input): array
    {
        if (trim($input) === '') {
            throw new ManualValuesException('');
        }

        $entries = self::splitUnescaped($input, ';');

        if (end($entries) === '') {
            array_pop($entries);
        }

        $values = [];

        foreach ($entries as $entry) {
            $parts = self::splitUnescaped($entry, '=', 2);
            $label = trim(self::unescape((string) ($parts[0] ?? '')));
            $rawValue = trim((string) ($parts[1] ?? ''));

            if ($label === '' || count($parts) !== 2 || !preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/D', $rawValue)) {
                throw new ManualValuesException(trim($entry));
            }

            $value = (float) $rawValue;

            if (!is_finite($value)) {
                throw new ManualValuesException(trim($entry));
            }

            $value = floor($value) === $value && $value <= PHP_INT_MAX && $value >= PHP_INT_MIN
                ? (int) $value
                : $value;
            $total = ($values[$label] ?? 0) + $value;

            if (!is_finite((float) $total)) {
                throw new ManualValuesException(trim($entry));
            }

            $values[$label] = floor((float) $total) === (float) $total
                && $total <= PHP_INT_MAX && $total >= PHP_INT_MIN ? (int) $total : (float) $total;
        }

        if ($values === []) {
            throw new ManualValuesException('');
        }

        return $values;
    }

    /** @return list<string> */
    private static function splitUnescaped(string $input, string $delimiter, int $limit = PHP_INT_MAX): array
    {
        $parts = [''];
        $escaped = false;

        foreach (preg_split('//u', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $index = count($parts) - 1;

            if (!$escaped && $character === '\\') {
                $escaped = true;
                $parts[$index] .= $character;
                continue;
            }

            if (!$escaped && $character === $delimiter && count($parts) < $limit) {
                $parts[] = '';
                continue;
            }

            $parts[$index] .= $character;
            $escaped = false;
        }

        return $parts;
    }

    private static function unescape(string $label): string
    {
        return preg_replace_callback('/\\\\([;=\\\\])/', static fn(array $match): string => $match[1], $label) ?? $label;
    }
}
