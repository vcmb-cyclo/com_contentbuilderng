<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Type;

use CB\Component\Contentbuilderng\Administrator\types\contentbuilderng_com_breezingformsng;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once \dirname(__DIR__, 4) . '/admin/src/types/com_breezingformsng.php';

final class BreezingFormsRecordStateTest extends TestCase
{
    /**
     * @return array<string, array{0: list<string>, 1: string, 2: string}>
     */
    public static function recordSchemas(): array
    {
        return [
            'Joomla state column' => [['id', 'form', 'state'], 'r.state = 1', '(r.state = 2)'],
            'former archived flag' => [['id', 'form', 'archived'], 'r.archived = 0', 'r.archived'],
        ];
    }

    /**
     * @param list<string> $columns
     */
    #[DataProvider('recordSchemas')]
    public function testRecordStatusFollowsTheColumnOfTheRecordsTable(array $columns, string $active, string $archived): void
    {
        $class = new ReflectionClass(contentbuilderng_com_breezingformsng::class);
        $type = $class->newInstanceWithoutConstructor();
        $class->getProperty('recordColumns')->setValue($type, array_fill_keys($columns, true));

        self::assertSame($active, $class->getMethod('getActiveRecordCondition')->invoke($type));
        self::assertSame($archived, $class->getMethod('getArchivedRecordSelect')->invoke($type));
    }

    /**
     * @return array<string, array{0: array<string, int>, 1: string}>
     */
    public static function recordRows(): array
    {
        return [
            'published state' => [['state' => 1], '0'],
            'archived state' => [['state' => 2], '1'],
            'trashed state' => [['state' => -2], '0'],
            'archived flag set' => [['archived' => 1], '1'],
            'archived flag unset' => [['archived' => 0], '0'],
        ];
    }

    /**
     * @param array<string, int> $row
     */
    #[DataProvider('recordRows')]
    public function testArchivedSystemFieldValueFollowsTheRecordRow(array $row, string $expected): void
    {
        $class = new ReflectionClass(contentbuilderng_com_breezingformsng::class);
        $type = $class->newInstanceWithoutConstructor();

        self::assertSame(
            $expected,
            $class->getMethod('getSystemFieldValue')->invoke(
                $type,
                $class->getConstant('SYSTEM_FIELD_ARCHIVED'),
                (object) $row
            )
        );
    }

    public function testRecordQueriesDoNotHardCodeTheStatusColumn(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 4) . '/admin/src/types/com_breezingformsng.php');

        self::assertSame(2, substr_count($source, '" . $this->getActiveRecordCondition() . "'));
        self::assertSame(1, substr_count($source, 'r.archived = 0'));
    }
}
