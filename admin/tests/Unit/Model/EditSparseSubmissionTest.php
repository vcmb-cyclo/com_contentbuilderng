<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;

final class EditSparseSubmissionTest extends TestCase
{
    public function testFieldsAbsentFromPostAreExcludedFromSavedValues(): void
    {
        $model = file_get_contents(
            dirname(__DIR__, 4) . '/site/src/Model/EditModel.php'
        );

        self::assertIsString($model);
        self::assertStringContainsString(
            "if (!\$this->app->getInput()->post->exists('cb_' . \$id)) {\n                                    continue;",
            $model
        );
        self::assertStringContainsString('$values[$id] = $value;', $model);
    }
}
