<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Administrator\Helper\PhpTemplateHelper;
use PHPUnit\Framework\TestCase;

/**
 * PhpTemplateHelper::evaluate() is the single consolidated entry point for PHP
 * template evaluation, used by three callers (com_contentbuilderng types, com_breezingforms
 * types, RuntimeUtilityService). Bugs here silently corrupt form output.
 *
 * The method has two code paths (mb_* vs plain string functions) that are
 * structurally identical — the tests below exercise the shared logic and the
 * guard that skips eval() entirely for non-PHP strings.
 */
final class PhpTemplateHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // No-eval fast path: strings that do NOT start with <?php
    // -------------------------------------------------------------------------

    // The most common case: a plain value string stored in a form field.
    // eval() must never be called for these.
    public function testPlainStringIsReturnedUnchanged(): void
    {
        self::assertSame('hello world', PhpTemplateHelper::evaluate('hello world'));
    }

    public function testEmptyStringReturnsEmpty(): void
    {
        self::assertSame('', PhpTemplateHelper::evaluate(''));
    }

    public function testWhitespaceOnlyStringIsReturnedUnchanged(): void
    {
        self::assertSame('   ', PhpTemplateHelper::evaluate('   '));
    }

    // HTML without any PHP tag must pass through intact.
    public function testHtmlStringIsReturnedUnchanged(): void
    {
        $html = '<strong>Name:</strong> John';
        self::assertSame($html, PhpTemplateHelper::evaluate($html));
    }

    // A string containing "<?php" somewhere in the middle (not at the start)
    // must be treated as plain text — the guard checks trim($result).
    public function testStringWithPhpTagNotAtStartIsReturnedUnchanged(): void
    {
        $input = 'prefix <?php return "x"; ?>';
        self::assertSame($input, PhpTemplateHelper::evaluate($input));
    }

    // -------------------------------------------------------------------------
    // eval path: strings that start with <?php
    // -------------------------------------------------------------------------

    // The evaluated PHP block must return its value; that value replaces the block.
    public function testSinglePhpBlockWithReturnIsEvaluated(): void
    {
        self::assertSame('42', PhpTemplateHelper::evaluate('<?php return "42"; ?>'));
    }

    // Leading/trailing whitespace around <?php is trimmed before the check.
    public function testLeadingWhitespaceBeforePhpTagIsIgnored(): void
    {
        self::assertSame('ok', PhpTemplateHelper::evaluate('  <?php return "ok"; ?>'));
    }

    // Text before the first <?php is preserved literally.
    public function testLiteralTextBeforePhpBlockIsPreserved(): void
    {
        // "prefix" is before the first <?php so it becomes the leading literal segment.
        // Note: the guard only checks that the *trimmed* string starts with <?php;
        // here the untrimmed string starts with "prefix " so the fast path applies —
        // this test intentionally documents that boundary.
        $input = 'prefix<?php return "-suffix"; ?>';
        // Does NOT start with <?php after trim → returned as-is.
        self::assertSame($input, PhpTemplateHelper::evaluate($input));
    }

    // Text after the PHP closing tag is preserved as a literal suffix.
    public function testLiteralTextAfterPhpBlockIsPreserved(): void
    {
        self::assertSame('result: done', PhpTemplateHelper::evaluate('<?php return "result: "; ?>done'));
    }

    // Multiple adjacent PHP blocks (each with open and close tags) must all be evaluated and concatenated.
    public function testMultiplePhpBlocksAreConcatenated(): void
    {
        $input = '<?php return "hello"; ?> <?php return "world"; ?>';
        self::assertSame('hello world', PhpTemplateHelper::evaluate($input));
    }

    // Mixed content: literal text interleaved with PHP blocks.
    public function testMixedLiteralsAndPhpBlocksAreConcatenated(): void
    {
        $input = '<?php return "A"; ?>-middle-<?php return "B"; ?>';
        self::assertSame('A-middle-B', PhpTemplateHelper::evaluate($input));
    }

    // A block without a closing tag must consume the rest of the string.
    public function testPhpBlockWithoutClosingTagConsumesRemainder(): void
    {
        self::assertSame('no-close', PhpTemplateHelper::evaluate('<?php return "no-close";'));
    }

    // A block that returns an empty string must contribute nothing to the output.
    public function testPhpBlockReturningEmptyStringContributesNothing(): void
    {
        self::assertSame('prefix-suffix', PhpTemplateHelper::evaluate('<?php return ""; ?>prefix-suffix'));
    }

    // Computed values (not just literals) must be evaluated correctly.
    public function testPhpBlockCanPerformArithmetic(): void
    {
        self::assertSame('10', PhpTemplateHelper::evaluate('<?php return (string)(4 + 6); ?>'));
    }

    // A PHP block may define a variable and return it — common in legacy templates.
    public function testPhpBlockCanUseLocalVariable(): void
    {
        self::assertSame('hello', PhpTemplateHelper::evaluate('<?php $v = "hello"; return $v; ?>'));
    }
}
