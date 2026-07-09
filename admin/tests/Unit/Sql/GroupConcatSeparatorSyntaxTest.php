<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Sql;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for a real production incident: GROUP_CONCAT's SEPARATOR
 * clause requires a string literal in MySQL/MariaDB grammar, not a function
 * call. `SEPARATOR CHAR(31)` is invalid and breaks with a 1064 syntax error;
 * the fix must be a quoted literal (e.g. via $db->quote(chr(31))).
 *
 * This scans the actual source tree instead of unit-testing one call site,
 * so any future raw SQL fragment making the same mistake fails CI without
 * needing a live MariaDB connection.
 */
final class GroupConcatSeparatorSyntaxTest extends TestCase
{
    private const SCAN_DIRECTORIES = [
        'admin/src',
        'site/src',
        'plugins',
    ];

    public function testNoSeparatorClauseIsFollowedByABareFunctionCall(): void
    {
        $root = \dirname(__DIR__, 4);
        $offenders = [];

        foreach (self::SCAN_DIRECTORIES as $relativeDirectory) {
            $directory = $root . '/' . $relativeDirectory;

            if (!\is_dir($directory)) {
                continue;
            }

            $offenders = [...$offenders, ...$this->scanDirectory($directory, $root)];
        }

        self::assertSame(
            [],
            $offenders,
            "Invalid GROUP_CONCAT SEPARATOR syntax found (SEPARATOR must be a string literal, not a function call):\n"
                . implode("\n", $offenders)
        );
    }

    /**
     * @return list<string>
     */
    private function scanDirectory(string $directory, string $root): array
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) \file_get_contents($file->getPathname());
            $lines = \explode("\n", $contents);

            foreach ($lines as $lineNumber => $line) {
                if (\preg_match('/SEPARATOR\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/', $line)) {
                    $relativePath = \ltrim(\str_replace($root, '', $file->getPathname()), '/\\');
                    $offenders[] = $relativePath . ':' . ($lineNumber + 1) . ' — ' . \trim($line);
                }
            }
        }

        return $offenders;
    }
}
