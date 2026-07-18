<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Mvc;

use PHPUnit\Framework\TestCase;

final class InheritedMethodVisibilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \dirname(__DIR__, 4);
    }

    public function testMvcClassesDoNotNarrowPublicDispatcherVisibility(): void
    {
        foreach ($this->mvcFiles() as $relativePath) {
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:private|protected)\s+function\s+getDispatcher\s*\(/',
                $this->read($relativePath),
                $relativePath
            );
        }
    }

    public function testModelsDoNotNarrowProtectedDatabaseVisibility(): void
    {
        foreach ($this->modelFiles() as $relativePath) {
            self::assertDoesNotMatchRegularExpression(
                '/\bprivate\s+function\s+getDatabase\s*\(/',
                $this->read($relativePath),
                $relativePath
            );
        }
    }

    /**
     * @return list<string>
     */
    private function mvcFiles(): array
    {
        $files = [];

        foreach (['admin', 'site'] as $client) {
            foreach (['Controller', 'Model', 'View'] as $layer) {
                $files = array_merge(
                    $files,
                    glob($this->root . '/' . $client . '/src/' . $layer . '/*.php') ?: [],
                    glob($this->root . '/' . $client . '/src/' . $layer . '/*/*.php') ?: []
                );
            }
        }

        return $this->relativePaths($files, 'No MVC class files were discovered');
    }

    /**
     * @return list<string>
     */
    private function modelFiles(): array
    {
        $files = array_merge(
            glob($this->root . '/admin/src/Model/*.php') ?: [],
            glob($this->root . '/admin/src/Model/*/*.php') ?: [],
            glob($this->root . '/site/src/Model/*.php') ?: [],
            glob($this->root . '/site/src/Model/*/*.php') ?: []
        );

        return $this->relativePaths($files, 'No model class files were discovered');
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function relativePaths(array $files, string $emptyMessage): array
    {
        $files = array_values(array_unique($files));
        sort($files);

        self::assertNotEmpty($files, $emptyMessage);

        return array_map(
            fn(string $path): string => substr($path, strlen($this->root) + 1),
            $files
        );
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents($this->root . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
