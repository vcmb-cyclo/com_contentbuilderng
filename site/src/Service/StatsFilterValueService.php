<?php

/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Site\Service;

\defined('_JEXEC') or die('Restricted access');

final class StatsFilterValueService
{
    /**
     * @return list<string>
     */
    public function parseAlternatives(string $value): array
    {
        $values = [];

        foreach (explode('|', $value) as $alternative) {
            $alternative = trim($alternative);
            if ($alternative !== '') {
                $values[$alternative] = true;
            }
        }

        return array_keys($values);
    }

    public function hasWildcard(string $value): bool
    {
        return str_contains($value, '*');
    }

    public function toSqlLikePattern(string $value): string
    {
        return str_replace(
            ['\\', '%', '_', '*'],
            ['\\\\', '\\%', '\\_', '%'],
            $value
        );
    }
}
