<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\ApiFieldPermissionService;
use CB\Component\Contentbuilderng\Tests\Stubs\Query;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/ApiFieldPermissionService.php';

final class ApiFieldPermissionServiceTest extends TestCase
{
    public function testNonPositiveFormIdHasNoAllowedReferences(): void
    {
        $db = new ApiFieldPermissionDatabase(['title']);

        self::assertSame([], (new ApiFieldPermissionService($db))->getAllowedReferenceMap(0));
        self::assertSame(0, $db->queryCount);
    }

    public function testBuildsTrimmedUniqueReferenceMap(): void
    {
        $db = new ApiFieldPermissionDatabase([' title ', '', 'email', 'title']);

        self::assertSame(
            ['title' => true, 'email' => true],
            (new ApiFieldPermissionService($db))->getAllowedReferenceMap(7)
        );
        self::assertSame(1, $db->queryCount);
        self::assertStringContainsString('#__contentbuilderng_elements', $db->lastQuery);
    }

    public function testChecksTrimmedReferenceAgainstAllowedMap(): void
    {
        $service = new ApiFieldPermissionService(new ApiFieldPermissionDatabase(['title']));

        self::assertTrue($service->isReferenceAllowed(7, ' title '));
        self::assertFalse($service->isReferenceAllowed(7, 'unknown'));
        self::assertFalse($service->isReferenceAllowed(7, '  '));
    }
}

final class ApiFieldPermissionDatabase implements DatabaseInterface
{
    public int $queryCount = 0;
    public string $lastQuery = '';

    /**
     * @param list<mixed> $references
     */
    public function __construct(private readonly array $references)
    {
    }

    public function getQuery(bool $new = false): Query
    {
        return new Query();
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

    public function setQuery(QueryInterface|string $query): void
    {
        $this->lastQuery = (string) $query;
        $this->queryCount++;
    }

    public function execute(): void
    {
    }

    /**
     * @return list<mixed>
     */
    public function loadColumn(): array
    {
        return $this->references;
    }
}
