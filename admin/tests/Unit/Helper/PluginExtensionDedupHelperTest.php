<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Administrator\Helper\PluginExtensionDedupHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Helper/PluginExtensionDedupHelper.php';

final class PluginExtensionDedupHelperTest extends TestCase
{
    private const DUPLICATE_ROWS = [
        [
            'extension_id' => 10,
            'folder' => 'contentbuilderng_themes',
            'element' => 'contentbuilderng_thoth',
            'enabled' => 1,
            'manifest_cache' => '{}',
        ],
        [
            'extension_id' => 11,
            'folder' => 'contentbuilder_themes',
            'element' => 'contentbuilder_thoth',
            'enabled' => 1,
            'manifest_cache' => '{}',
        ],
    ];

    public function testAuditsLegacyAndCanonicalPluginDuplicates(): void
    {
        $db = new PluginExtensionAuditDatabase([
            [
                'extension_id' => 10,
                'folder' => 'contentbuilderng_themes',
                'element' => 'contentbuilderng_thoth',
                'enabled' => 0,
                'manifest_cache' => '{}',
            ],
            [
                'extension_id' => 11,
                'folder' => 'contentbuilder_themes',
                'element' => 'contentbuilder_thoth',
                'enabled' => 1,
                'manifest_cache' => '{}',
            ],
            [
                'extension_id' => 12,
                'folder' => 'contentbuilder_ng_themes',
                'element' => 'contentbuilder_ng_thoth',
                'enabled' => 1,
                'manifest_cache' => '',
            ],
            [
                'extension_id' => 20,
                'folder' => 'contentbuilderng_validation',
                'element' => 'contentbuilderng_email',
                'enabled' => 1,
                'manifest_cache' => '{}',
            ],
            ['extension_id' => 0, 'folder' => 'invalid', 'element' => 'invalid'],
            ['extension_id' => 21, 'folder' => '', 'element' => 'invalid'],
            'invalid row',
        ]);

        $summary = PluginExtensionDedupHelper::audit($db);

        self::assertSame(4, $summary['scanned']);
        self::assertSame(1, $summary['groups_count']);
        self::assertSame(2, $summary['rows_to_remove']);
        self::assertSame([], $summary['warnings']);

        $group = $summary['groups'][0];
        self::assertSame('contentbuilderng_themes', $group['canonical_folder']);
        self::assertSame('contentbuilderng_thoth', $group['canonical_element']);
        self::assertSame(10, $group['keep_id']);
        self::assertSame([11, 12], $group['duplicate_ids']);
        self::assertSame(10, $group['rows'][0]['extension_id']);
        self::assertSame(1, $group['rows'][0]['is_canonical']);
    }

    public function testAuditReturnsWarningWhenDatabaseReadFails(): void
    {
        $summary = PluginExtensionDedupHelper::audit(new PluginExtensionAuditDatabase([], true));

        self::assertSame(0, $summary['scanned']);
        self::assertSame([], $summary['groups']);
        self::assertStringContainsString('Database unavailable', $summary['warnings'][0]);
    }

    public function testRepairHasNothingToDoWithoutDuplicateGroups(): void
    {
        $summary = PluginExtensionDedupHelper::repair(new PluginExtensionAuditDatabase([]));

        self::assertSame(0, $summary['scanned']);
        self::assertSame(0, $summary['issues']);
        self::assertSame(0, $summary['repaired']);
        self::assertSame([], $summary['groups']);
    }

    public function testRepairsDuplicatePluginRows(): void
    {
        $db = new PluginExtensionRepairDatabase(self::DUPLICATE_ROWS, 1);

        $summary = PluginExtensionDedupHelper::repair($db);

        self::assertSame(1, $summary['scanned']);
        self::assertSame(1, $summary['issues']);
        self::assertSame(1, $summary['repaired']);
        self::assertSame(0, $summary['errors']);
        self::assertSame(1, $summary['rows_removed']);
        self::assertSame('repaired', $summary['groups'][0]['status']);
        self::assertSame([11], $summary['groups'][0]['removed_ids']);
        self::assertSame(4, $db->executeCount);
    }

    public function testReportsRepairDatabaseFailure(): void
    {
        $summary = PluginExtensionDedupHelper::repair(
            new PluginExtensionRepairDatabase(self::DUPLICATE_ROWS, 0, true)
        );

        self::assertSame(0, $summary['repaired']);
        self::assertSame(1, $summary['errors']);
        self::assertSame('error', $summary['groups'][0]['status']);
        self::assertStringContainsString('Write failed', $summary['groups'][0]['error']);
    }
}

final class PluginExtensionAuditDatabase implements DatabaseInterface
{
    /**
     * @param array<int,mixed> $rows
     */
    public function __construct(
        private readonly array $rows,
        private readonly bool $fail = false
    ) {
    }

    public function getQuery(bool $new = false): PluginExtensionAuditQuery
    {
        if ($this->fail) {
            throw new \RuntimeException('Database unavailable');
        }

        return new PluginExtensionAuditQuery();
    }

    public function getPrefix(): string
    {
        return 'joom_';
    }

    public function getTableColumns(string $table, bool $type = true): array
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

    public function setQuery(QueryInterface|string $query): void
    {
    }

    public function execute(): void
    {
    }

    /**
     * @return array<int,mixed>
     */
    public function loadAssocList(): array
    {
        return $this->rows;
    }
}

final class PluginExtensionAuditQuery implements QueryInterface
{
    public function select(array|string $columns): self
    {
        return $this;
    }

    public function from(string $table): self
    {
        return $this;
    }

    public function where(string $condition): self
    {
        return $this;
    }

    public function order(string $ordering): self
    {
        return $this;
    }

    public function update(string $table): self
    {
        return $this;
    }

    public function set(string $assignment): self
    {
        return $this;
    }

    public function delete(string $table): self
    {
        return $this;
    }
}

final class PluginExtensionRepairDatabase implements DatabaseInterface
{
    public int $executeCount = 0;
    private int $setQueryCount = 0;

    /**
     * @param array<int,mixed> $rows
     */
    public function __construct(
        private readonly array $rows,
        private readonly int $affectedRows,
        private readonly bool $failWrites = false
    ) {
    }

    public function getQuery(bool $new = false): PluginExtensionAuditQuery
    {
        return new PluginExtensionAuditQuery();
    }

    public function getPrefix(): string
    {
        return 'joom_';
    }

    public function getTableColumns(string $table, bool $type = true): array
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

    public function setQuery(QueryInterface|string $query): self
    {
        $this->setQueryCount++;

        if ($this->failWrites && $this->setQueryCount > 1) {
            throw new \RuntimeException('Write failed');
        }

        return $this;
    }

    public function execute(): void
    {
        $this->executeCount++;
    }

    /**
     * @return array<int,mixed>
     */
    public function loadAssocList(): array
    {
        return $this->rows;
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }
}
