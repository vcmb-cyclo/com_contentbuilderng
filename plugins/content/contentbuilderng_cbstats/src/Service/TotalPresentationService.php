<?php

declare(strict_types=1);

namespace CB\Plugin\Content\ContentbuilderngStats\Service;

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

final class TotalPresentationService
{
    public static function formatLabel(string $title, string $defaultLabel, string $separator): string
    {
        $label = trim($title);

        if ($label === '') {
            $label = trim($defaultLabel);
        }

        return str_ends_with(rtrim($label), ':') ? rtrim($label) : $label . $separator;
    }

    public static function validateBackground(string $background): string
    {
        $background = trim($background);

        if ($background === '') {
            return '';
        }

        if ($background === 'transparent' || preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?(?:[0-9a-f]{2})?$/i', $background)) {
            return $background;
        }

        if (preg_match('/^rgb(a)?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0(?:\.\d+)?|1(?:\.0+)?))?\s*\)$/i', $background, $matches)) {
            $channelsAreValid = max((int) $matches[2], (int) $matches[3], (int) $matches[4]) <= 255;
            $alphaIsValid = ($matches[1] === '') === !isset($matches[5]);

            return $channelsAreValid && $alphaIsValid ? $background : '';
        }

        $namedColors = [
            'aliceblue', 'black', 'blue', 'currentcolor', 'gray', 'green', 'grey', 'red', 'white', 'yellow',
        ];

        return in_array(strtolower($background), $namedColors, true) ? strtolower($background) : '';
    }
}
