<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;

/**
 * Loading a plugin file must never change PHP's global error handling:
 * a file-level set_error_handler() silences every warning of the site.
 */
final class PluginGlobalErrorHandlerTest extends TestCase
{
    private const FORBIDDEN_FILE_LEVEL_CALLS = ['set_error_handler', 'register_shutdown_function'];

    public function testNoPluginRegistersGlobalHandlersAtFileLevel(): void
    {
        $pluginsRoot = \dirname(__DIR__, 4) . '/plugins';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pluginsRoot, \FilesystemIterator::SKIP_DOTS));
        $scanned = 0;

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$scanned;
            $relativePath = substr($file->getPathname(), \strlen($pluginsRoot) + 1);

            foreach ($this->findFileLevelCalls((string) file_get_contents($file->getPathname())) as [$function, $line]) {
                self::fail(sprintf('%s:%d calls %s() at file level.', $relativePath, $line, $function));
            }
        }

        self::assertGreaterThan(0, $scanned, 'No plugin PHP files were discovered');
    }

    /**
     * @return list<array{string, int}>
     */
    private function findFileLevelCalls(string $source): array
    {
        $calls = [];
        $depth = 0;
        $tokens = token_get_all($source);

        foreach ($tokens as $index => $token) {
            if ($token === '{' || (\is_array($token) && \in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                ++$depth;
                continue;
            }

            if ($token === '}') {
                --$depth;
                continue;
            }

            if ($depth !== 0 || !\is_array($token) || !\in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $function = strtolower(ltrim($token[1], '\\'));
            if (!\in_array($function, self::FORBIDDEN_FILE_LEVEL_CALLS, true)) {
                continue;
            }

            for ($next = $index + 1; isset($tokens[$next]) && \is_array($tokens[$next]) && $tokens[$next][0] === T_WHITESPACE; ++$next) {
            }

            if (($tokens[$next] ?? null) === '(') {
                $calls[] = [$function, $token[2]];
            }
        }

        return $calls;
    }
}
