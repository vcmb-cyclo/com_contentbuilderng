<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

final class StorageColumnTypeHelper
{
    public const DEFAULT_TYPE = 'text';
    private const TYPES = ['text', 'varchar', 'int', 'decimal', 'date', 'datetime', 'boolean'];

    /**
     * @return array<string,string>
     */
    public static function options(): array
    {
        return [
            'text' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_TEXT'),
            'varchar' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_VARCHAR'),
            'int' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_INT'),
            'decimal' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_DECIMAL'),
            'date' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_DATE'),
            'datetime' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_DATETIME'),
            'boolean' => Text::_('COM_CONTENTBUILDERNG_STORAGE_SQL_TYPE_BOOLEAN'),
        ];
    }

    public static function normalize(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, self::TYPES, true) ? $type : self::DEFAULT_TYPE;
    }

    public static function label(?string $type): string
    {
        $type = self::normalize($type);
        $options = self::options();

        return (string) ($options[$type] ?? $type);
    }

    public static function sqlDefinition(?string $type): string
    {
        return match (self::normalize($type)) {
            'varchar' => 'VARCHAR(255) NULL',
            'int' => 'INT NULL',
            'decimal' => 'DECIMAL(15,4) NULL',
            'date' => 'DATE NULL',
            'datetime' => 'DATETIME NULL',
            'boolean' => 'TINYINT(1) NULL',
            default => 'TEXT NULL',
        };
    }

    public static function physicalTypeMatches(?string $expectedType, mixed $columnDefinition): bool
    {
        $physicalType = self::extractPhysicalType($columnDefinition);

        if ($physicalType === '') {
            return false;
        }

        return match (self::normalize($expectedType)) {
            'varchar' => str_starts_with($physicalType, 'varchar'),
            'int' => $physicalType === 'int' || str_starts_with($physicalType, 'int('),
            'decimal' => str_starts_with($physicalType, 'decimal'),
            'date' => $physicalType === 'date',
            'datetime' => $physicalType === 'datetime',
            'boolean' => str_starts_with($physicalType, 'tinyint(1)') || $physicalType === 'boolean' || $physicalType === 'bool',
            default => str_starts_with($physicalType, 'text') || str_starts_with($physicalType, 'mediumtext') || str_starts_with($physicalType, 'longtext'),
        };
    }

    public static function extractPhysicalType(mixed $columnDefinition): string
    {
        if (is_object($columnDefinition)) {
            $columnDefinition = get_object_vars($columnDefinition);
        }

        if (is_array($columnDefinition)) {
            $rawType = (string) ($columnDefinition['Type'] ?? $columnDefinition['type'] ?? '');
        } else {
            $rawType = (string) $columnDefinition;
        }

        $rawType = strtolower(trim($rawType));

        if ($rawType === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $rawType);

        return (string) ($parts[0] ?? $rawType);
    }
}
