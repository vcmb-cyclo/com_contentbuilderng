<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use CB\Component\Contentbuilderng\Site\Helper\DebugPermissionHelper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once \dirname(__DIR__, 4) . '/site/src/Helper/DebugPermissionHelper.php';

/**
 * Regression guard for a real production incident: all three frontend debug-mode
 * templates called DebugPermissionHelper::resolvePermissions() with only 3
 * arguments after the method gained a required $app parameter, fataling with
 * "Too few arguments" as soon as debug mode was enabled (fixed in bf92ec55).
 *
 * Compares the method's actual required parameter count (via reflection, so it
 * stays correct if the signature changes again) against the argument count each
 * known call site passes, instead of unit-testing a single call site.
 */
final class DebugPermissionHelperCallSitesTest extends TestCase
{
    private const CALL_SITES = [
        'site/tmpl/list/default.php',
        'site/tmpl/edit/default.php',
        'site/tmpl/details/default.php',
    ];

    public function testEveryCallSitePassesTheExactRequiredArgumentCount(): void
    {
        $root = \dirname(__DIR__, 4);
        $expected = (new ReflectionMethod(DebugPermissionHelper::class, 'resolvePermissions'))
            ->getNumberOfParameters();

        foreach (self::CALL_SITES as $relativePath) {
            $source = (string) \file_get_contents($root . '/' . $relativePath);

            self::assertMatchesRegularExpression(
                '/DebugPermissionHelper::resolvePermissions\(/',
                $source,
                "{$relativePath} no longer calls DebugPermissionHelper::resolvePermissions() — update CALL_SITES if it moved."
            );

            $argumentCount = $this->countCallArguments($source, 'DebugPermissionHelper::resolvePermissions');

            self::assertSame(
                $expected,
                $argumentCount,
                "{$relativePath} passes {$argumentCount} argument(s) to resolvePermissions(), "
                    . "but the method now requires {$expected}."
            );
        }
    }

    /**
     * Counts top-level (bracket-depth-aware) comma-separated arguments passed
     * to the first call of $functionName found in $source.
     */
    private function countCallArguments(string $source, string $functionName): int
    {
        $start = \strpos($source, $functionName . '(');
        self::assertNotFalse($start, "Could not locate a call to {$functionName}().");

        $cursor = $start + \strlen($functionName) + 1;
        $depth = 1;
        $argumentCount = 1;
        $length = \strlen($source);

        for (; $cursor < $length && $depth > 0; $cursor++) {
            $char = $source[$cursor];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === ',' && $depth === 1) {
                $argumentCount++;
            }
        }

        // Trailing comma before the closing paren does not add an argument.
        $callBody = \substr($source, $start + \strlen($functionName) + 1, $cursor - ($start + \strlen($functionName) + 1) - 1);
        if (\trim($callBody) === '') {
            return 0;
        }

        return $argumentCount;
    }
}
