<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Site;

use CB\Component\Contentbuilderng\Site\Service\ExportFilenameService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/site/src/Service/ExportFilenameService.php';

final class ExportFilenameServiceTest extends TestCase
{
    private const DEFAULT_TIMESTAMP = '2026-09-10_0835';

    public function testDefaultModePreservesTheExistingFilename(): void
    {
        self::assertSame(
            'CB_export_CB Vue_2026-09-10_0835.xlsx',
            ExportFilenameService::build(
                'CB Vue',
                'default',
                'Ignored',
                self::DEFAULT_TIMESTAMP
            )
        );
    }

    public function testCustomModeBuildsTheRequestedFilename(): void
    {
        self::assertSame(
            'Participants_BRM_200_2026-09-10_0835.xlsx',
            ExportFilenameService::build(
                'CB Vue',
                'custom',
                'Participants BRM 200',
                self::DEFAULT_TIMESTAMP
            )
        );
    }

    public function testCustomModeNormalizesASeparatedHourLikeTheHistoricalExport(): void
    {
        self::assertSame(
            'Export_List_View_2026-09-11_1315.xlsx',
            ExportFilenameService::build(
                'CB Vue',
                'custom',
                'Export List View',
                '2026-09-11_13-15'
            )
        );
    }

    #[DataProvider('unsafeNames')]
    public function testUnsafeOrEmptyCustomNamesAreSanitizedOrFallBack(string $input, string $expected): void
    {
        self::assertSame(
            $expected,
            ExportFilenameService::build(
                'CB Vue',
                'custom',
                $input,
                self::DEFAULT_TIMESTAMP
            )
        );
    }

    public static function unsafeNames(): array
    {
        return [
            'forbidden characters' => [
                'Rapport / \\ : * ? " < > | BRM',
                'Rapport_BRM_2026-09-10_0835.xlsx',
            ],
            'only forbidden characters' => [
                '/\\:*?"<>|',
                'CB_export_CB Vue_2026-09-10_0835.xlsx',
            ],
            'empty name' => ['', 'CB_export_CB Vue_2026-09-10_0835.xlsx'],
            'path traversal' => ['../..', 'CB_export_CB Vue_2026-09-10_0835.xlsx'],
            'arbitrary extension' => ['participants.exe', 'participants_exe_2026-09-10_0835.xlsx'],
        ];
    }

    public function testRealExportTemplateUsesTheServiceAndKeepsXlsxAsTheOnlyExtension(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__, 4) . '/site/tmpl/export/default.php');
        $list = (string) file_get_contents(dirname(__DIR__, 4) . '/site/tmpl/list/default.php');

        self::assertStringContainsString('ExportFilenameService::build(', $template);
        self::assertStringContainsString("filename*=UTF-8\\'\\'", $template);
        self::assertStringContainsString("'cb_export_filename_mode' =>", $list);
        self::assertStringContainsString("'cb_export_filename' =>", $list);
        self::assertStringNotContainsString(".xls';", $template);
    }
}
