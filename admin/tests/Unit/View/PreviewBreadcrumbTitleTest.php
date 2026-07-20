<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PreviewBreadcrumbTitleTest extends TestCase
{
    #[DataProvider('templateProvider')]
    public function testPreviewBreadcrumbUsesFormNameInsteadOfMenuPageTitle(string $template): void
    {
        $source = file_get_contents(\dirname(__DIR__, 4) . '/' . $template);

        self::assertIsString($source);
        self::assertStringContainsString('$isAdminPreview', $source);
        self::assertStringContainsString('$this->form_name', $source);
        self::assertMatchesRegularExpression(
            '/\$isAdminPreview\s*&&\s*\$previewFormName\s*!==\s*\'\'/s',
            $source
        );
    }

    public static function templateProvider(): array
    {
        return [
            'edit' => ['site/tmpl/edit/default.php'],
            'details' => ['site/tmpl/details/default.php'],
        ];
    }
}
