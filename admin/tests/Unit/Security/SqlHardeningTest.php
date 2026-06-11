<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

final class SqlHardeningTest extends TestCase
{
    public function testFrontendSqlDoesNotReintroduceKnownUnsafeInterpolationPatterns(): void
    {
        $root = \dirname(__DIR__, 4);
        $files = [
            'plugins/content/contentbuilderng_download/contentbuilderng_download.php',
            'site/src/Model/EditModel.php',
            'admin/src/Model/StorageModel.php',
            'admin/src/types/com_contentbuilderng.php',
        ];
        $forbiddenFragments = [
            "resource_id = '\" . \$file_id",
            "Values ('\" . \$session->getId()",
            'ALTER TABLE `#__{$dataTableName}`',
            '"DROP TABLE `#__" . $storage->name',
            '"ALTER TABLE `#__" . $this->target_table',
            '"Select Count(*) From #__" . $this->target_table',
            '"Truncate Table #__" . $this->target_table',
        ];

        foreach ($files as $relativePath) {
            $source = \file_get_contents($root . '/' . $relativePath);
            self::assertIsString($source, 'Unable to read ' . $relativePath);

            foreach ($forbiddenFragments as $fragment) {
                self::assertStringNotContainsString(
                    $fragment,
                    $source,
                    'Unsafe SQL interpolation pattern found in ' . $relativePath
                );
            }
        }
    }
}
