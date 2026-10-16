<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\SchemaService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/SchemaService.php';

final class SchemaServiceTest extends TestCase
{
    private function makeService(SchemaServiceFakeDatabase $db, array &$logLines): SchemaService
    {
        return new SchemaService(
            static fn(): DatabaseInterface => $db,
            static function (string $message, int $priority) use (&$logLines): void {
                $logLines[] = $message;
            },
            static fn(callable $callback, mixed $fallback = null): mixed => $callback()
        );
    }

    /**
     * @param array<string,mixed> $columns
     * @return array{executed: list<string>, logs: list<string>}
     */
    private function runStoragesAuditColumnMigration(array $columns, string $failingStatementPrefix): array
    {
        $db = new SchemaServiceAuditColumnsFakeDatabase($columns, $failingStatementPrefix);
        $logLines = [];
        $service = new SchemaService(
            static fn(): DatabaseInterface => $db,
            static function (string $message, int $priority) use (&$logLines): void {
                $logLines[] = $message;
            },
            static fn(callable $callback, mixed $fallback = null): mixed => $callback()
        );

        (new \ReflectionMethod(SchemaService::class, 'migrateStoragesAuditColumns'))->invoke($service);

        return ['executed' => $db->executed, 'logs' => $logLines];
    }

    public function testKeepsLegacyAuditColumnWhenRenamingItFails(): void
    {
        $result = $this->runStoragesAuditColumnMigration(
            ['id' => 'int', 'last_update' => 'datetime', 'created' => 'datetime', 'created_by' => 'varchar', 'modified_by' => 'varchar'],
            'ALTER TABLE `#__contentbuilderng_storages` CHANGE `last_update`'
        );

        self::assertSame([], array_filter($result['executed'], static fn(string $sql): bool => str_contains($sql, 'DROP COLUMN `last_update`')));
        self::assertStringContainsString('Failed renaming storage audit column last_update to modified', implode("\n", $result['logs']));
    }

    public function testKeepsLegacyAuditColumnWhenCopyingItsDataFails(): void
    {
        $result = $this->runStoragesAuditColumnMigration(
            ['id' => 'int', 'last_update' => 'datetime', 'created' => 'datetime', 'modified' => 'datetime', 'created_by' => 'varchar', 'modified_by' => 'varchar'],
            'UPDATE `#__contentbuilderng_storages` SET `modified` = `last_update`'
        );

        self::assertSame([], array_filter($result['executed'], static fn(string $sql): bool => str_contains($sql, 'DROP COLUMN `last_update`')));
        self::assertStringContainsString('Failed copying data from last_update to modified', implode("\n", $result['logs']));
    }

    public function testDropsLegacyAuditColumnOnceItsDataIsCopied(): void
    {
        $result = $this->runStoragesAuditColumnMigration(
            ['id' => 'int', 'last_update' => 'datetime', 'created' => 'datetime', 'modified' => 'datetime', 'created_by' => 'varchar', 'modified_by' => 'varchar'],
            ''
        );

        self::assertCount(1, array_filter($result['executed'], static fn(string $sql): bool => str_contains($sql, 'DROP COLUMN `last_update`')));
    }

    public function testKeepsOldestRowByIdAndAddsMissingUniqueIndex(): void
    {
        $db = new SchemaServiceFakeDatabase();
        $db->rows['#__contentbuilderng_elements'] = [
            ['id' => 10, 'form_id' => 5, 'reference_id' => 3],
            ['id' => 11, 'form_id' => 5, 'reference_id' => 3],
            ['id' => 12, 'form_id' => 5, 'reference_id' => 3],
        ];

        $logLines = [];
        $this->makeService($db, $logLines)->ensureUniqueConstraints();

        self::assertSame(
            [['id' => 10, 'form_id' => 5, 'reference_id' => 3]],
            array_values($db->rows['#__contentbuilderng_elements']),
            'Only the row with the smallest id should survive.'
        );
        self::assertSame(1, $db->alterTableCallCount('#__contentbuilderng_elements'));
        self::assertSame([], $logLines);
    }

    public function testUsesDateColumnForRatingCacheWhichHasNoIdColumn(): void
    {
        $db = new SchemaServiceFakeDatabase();
        $db->rows['#__contentbuilderng_rating_cache'] = [
            ['record_id' => 7, 'form_id' => 2, 'ip' => '1.2.3.4', 'date' => '2026-07-29 10:00:00'],
            ['record_id' => 7, 'form_id' => 2, 'ip' => '1.2.3.4', 'date' => '2026-07-29 09:00:00'],
            ['record_id' => 7, 'form_id' => 2, 'ip' => '1.2.3.4', 'date' => '2026-07-29 11:00:00'],
        ];

        $logLines = [];
        $this->makeService($db, $logLines)->ensureUniqueConstraints();

        self::assertCount(1, $db->rows['#__contentbuilderng_rating_cache']);
        self::assertSame(
            '2026-07-29 09:00:00',
            $db->rows['#__contentbuilderng_rating_cache'][array_key_first($db->rows['#__contentbuilderng_rating_cache'])]['date'],
            'The earliest date should survive when the table has no id column to break ties.'
        );
    }

    public function testIsIdempotentAndDoesNotAlterTableWhenTheIndexAlreadyExists(): void
    {
        $db = new SchemaServiceFakeDatabase();
        $db->rows['#__contentbuilderng_records'] = [
            ['id' => 1, 'type' => 'com_breezingformsng', 'reference_id' => 4, 'record_id' => 99],
        ];
        $db->existingIndexes['#__contentbuilderng_records'] = [
            ['Key_name' => 'idx_type_reference_record', 'Seq_in_index' => 1, 'Column_name' => 'type', 'Non_unique' => 0],
            ['Key_name' => 'idx_type_reference_record', 'Seq_in_index' => 2, 'Column_name' => 'reference_id', 'Non_unique' => 0],
            ['Key_name' => 'idx_type_reference_record', 'Seq_in_index' => 3, 'Column_name' => 'record_id', 'Non_unique' => 0],
        ];

        $logLines = [];
        $this->makeService($db, $logLines)->ensureUniqueConstraints();

        self::assertSame(0, $db->alterTableCallCount('#__contentbuilderng_records'));
    }

    public function testLogsAWarningAndContinuesWithOtherTablesWhenOneTableFails(): void
    {
        $db = new SchemaServiceFakeDatabase();
        $db->failShowIndexFor['#__contentbuilderng_records'] = true;
        $db->rows['#__contentbuilderng_elements'] = [
            ['id' => 20, 'form_id' => 1, 'reference_id' => 1],
            ['id' => 21, 'form_id' => 1, 'reference_id' => 1],
        ];

        $logLines = [];
        $this->makeService($db, $logLines)->ensureUniqueConstraints();

        self::assertNotEmpty($logLines);
        self::assertStringContainsString('idx_type_reference_record', $logLines[0]);
        self::assertSame(
            [['id' => 20, 'form_id' => 1, 'reference_id' => 1]],
            array_values($db->rows['#__contentbuilderng_elements']),
            'A failure on one table must not prevent the others from being deduplicated.'
        );
    }
}

final class SchemaServiceFakeDatabase implements DatabaseInterface
{
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $rows = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $existingIndexes = [];

    /** @var array<string, bool> */
    public array $failShowIndexFor = [];

    /** @var array<string, int> */
    private array $alterCalls = [];

    private mixed $currentQuery = null;
    private int $currentLimit = 0;

    public function alterTableCallCount(string $table): int
    {
        return $this->alterCalls[$table] ?? 0;
    }

    public function getQuery(bool $new = false): SchemaServiceFakeQuery
    {
        return new SchemaServiceFakeQuery();
    }

    public function getPrefix(): string
    {
        return 'joom_';
    }

    public function getTableColumns(string $table, bool $type = true): array
    {
        return [];
    }

    public function getTableList(): array
    {
        return [];
    }

    public function quoteName(array|string $name, array|string|null $as = null): array|string
    {
        if (is_array($name)) {
            return array_map(fn(string $item): string => (string) $this->quoteName($item), $name);
        }

        return '`' . $name . '`';
    }

    public function quote(mixed $value): string
    {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    public function setQuery(QueryInterface|string $query, int $offset = 0, int $limit = 0): self
    {
        $this->currentQuery = $query;
        $this->currentLimit = $limit;

        return $this;
    }

    public function execute(): void
    {
        if (is_string($this->currentQuery) && str_starts_with($this->currentQuery, 'ALTER TABLE')) {
            preg_match('/^ALTER TABLE `([^`]+)`/', $this->currentQuery, $matches);
            $table = $matches[1] ?? '';
            $this->alterCalls[$table] = ($this->alterCalls[$table] ?? 0) + 1;
            $this->existingIndexes[$table][] = ['Key_name' => 'added', 'Seq_in_index' => 1, 'Column_name' => 'added', 'Non_unique' => 0];

            return;
        }

        if ($this->currentQuery instanceof SchemaServiceFakeQuery && $this->currentQuery->isDelete) {
            $this->applyDelete($this->currentQuery, $this->currentLimit);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function loadAssocList(): array
    {
        if (is_string($this->currentQuery) && str_starts_with($this->currentQuery, 'SHOW INDEX FROM')) {
            $table = $this->extractBacktickedValue($this->currentQuery, 'SHOW INDEX FROM');

            if (!empty($this->failShowIndexFor[$table])) {
                throw new \RuntimeException('Table ' . $table . ' does not exist');
            }

            return $this->existingIndexes[$table] ?? [];
        }

        if ($this->currentQuery instanceof SchemaServiceFakeQuery && $this->currentQuery->isGroupedSelect) {
            return $this->computeDuplicateGroups($this->currentQuery);
        }

        return [];
    }

    private function extractBacktickedValue(string $sql, string $prefix): string
    {
        $rest = trim(substr($sql, strlen($prefix)));

        return trim($rest, '`');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function computeDuplicateGroups(SchemaServiceFakeQuery $query): array
    {
        $table = $query->from;
        $columns = $query->groupColumns;
        $buckets = [];

        foreach ($this->rows[$table] ?? [] as $row) {
            $key = implode('|', array_map(static fn(string $c): string => (string) ($row[$c] ?? ''), $columns));
            $buckets[$key][] = $row;
        }

        $groups = [];
        foreach ($buckets as $bucketRows) {
            if (count($bucketRows) < 2) {
                continue;
            }

            $groupRow = $bucketRows[0];
            $groupRow['duplicate_count'] = count($bucketRows);
            $groups[] = $groupRow;
        }

        return $groups;
    }

    private function applyDelete(SchemaServiceFakeQuery $query, int $limit): void
    {
        $table = $query->from;
        $rows = $this->rows[$table] ?? [];

        $matching = [];
        $remaining = [];
        foreach ($rows as $index => $row) {
            if ($this->rowMatchesConditions($row, $query->whereConditions)) {
                $matching[$index] = $row;
            } else {
                $remaining[$index] = $row;
            }
        }

        [$orderColumn, $direction] = $query->orderBy ?? [null, 'ASC'];
        if ($orderColumn !== null) {
            uasort(
                $matching,
                static function (array $a, array $b) use ($orderColumn, $direction): int {
                    $result = ($a[$orderColumn] ?? null) <=> ($b[$orderColumn] ?? null);

                    return $direction === 'DESC' ? -$result : $result;
                }
            );
        }

        $toDelete = array_slice($matching, 0, $limit, true);
        $keep = array_diff_key($matching, $toDelete);

        $this->rows[$table] = array_values($remaining + $keep);
    }

    /**
     * @param array<string,mixed> $row
     * @param string[] $conditions
     */
    private function rowMatchesConditions(array $row, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (preg_match('/^`(\w+)`\s+IS NULL$/', $condition, $matches) === 1) {
                if (($row[$matches[1]] ?? null) !== null) {
                    return false;
                }

                continue;
            }

            if (preg_match('/^`(\w+)`\s*=\s*\'(.*)\'$/', $condition, $matches) === 1) {
                if ((string) ($row[$matches[1]] ?? '') !== $matches[2]) {
                    return false;
                }

                continue;
            }
        }

        return true;
    }
}

final class SchemaServiceFakeQuery implements QueryInterface
{
    public string $from = '';
    public bool $isDelete = false;
    public bool $isGroupedSelect = false;
    /** @var string[] */
    public array $groupColumns = [];
    /** @var string[] */
    public array $whereConditions = [];
    /** @var array{0:string,1:string}|null */
    public ?array $orderBy = null;

    public function select(array|string $columns): self
    {
        $this->isGroupedSelect = true;

        return $this;
    }

    public function from(string $table): self
    {
        $this->from = trim($table, '`');

        return $this;
    }

    public function group(array|string $columns): self
    {
        $this->groupColumns = array_map(
            static fn(string $c): string => trim($c, '`'),
            (array) $columns
        );

        return $this;
    }

    public function having(string $condition): self
    {
        return $this;
    }

    public function delete(string $table): self
    {
        $this->isDelete = true;
        $this->from = trim($table, '`');

        return $this;
    }

    public function where(array|string $conditions): self
    {
        $this->whereConditions = array_merge($this->whereConditions, (array) $conditions);

        return $this;
    }

    public function order(array|string $columns): self
    {
        foreach ((array) $columns as $column) {
            if (preg_match('/^`(\w+)`\s+(ASC|DESC)$/', trim($column), $matches) === 1) {
                $this->orderBy = [$matches[1], $matches[2]];
            }
        }

        return $this;
    }
}

final class SchemaServiceAuditColumnsFakeDatabase implements DatabaseInterface
{
    /** @var list<string> */
    public array $executed = [];

    private string $currentQuery = '';

    /**
     * @param array<string,mixed> $columns
     */
    public function __construct(private array $columns, private readonly string $failingStatementPrefix)
    {
    }

    public function getPrefix(): string
    {
        return 'joom_';
    }

    public function getTableColumns(string $table, bool $type = true): array
    {
        return $this->columns;
    }

    public function getTableList(): array
    {
        return [];
    }

    public function getQuery(bool $new = false): QueryInterface
    {
        return new class implements QueryInterface {
            /**
             * @param array<int,mixed> $arguments
             */
            public function __call(string $method, array $arguments): self
            {
                return $this;
            }

            public function __toString(): string
            {
                return 'builder query';
            }
        };
    }

    public function quote(mixed $value): string
    {
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function loadAssocList(): array
    {
        return [];
    }

    public function quoteName(array|string $name, array|string|null $as = null): array|string
    {
        return is_array($name) ? array_map(static fn(string $item): string => '`' . $item . '`', $name) : '`' . $name . '`';
    }

    public function setQuery(QueryInterface|string $query, int $offset = 0, int $limit = 0): self
    {
        $this->currentQuery = (string) $query;

        return $this;
    }

    public function execute(): void
    {
        if ($this->failingStatementPrefix !== '' && str_starts_with($this->currentQuery, $this->failingStatementPrefix)) {
            throw new \RuntimeException('Simulated failure');
        }

        $this->executed[] = $this->currentQuery;

        if (preg_match('/ DROP COLUMN `([^`]+)`$/', $this->currentQuery, $matches)) {
            unset($this->columns[$matches[1]]);
        }
    }
}
