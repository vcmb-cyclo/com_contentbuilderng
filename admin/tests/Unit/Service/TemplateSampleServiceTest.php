<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\TemplateSampleService;
use CB\Component\Contentbuilderng\Tests\Stubs\Application;
use CB\Component\Contentbuilderng\Tests\Stubs\Query;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 3) . '/src/Service/TemplateSampleService.php';

final class TemplateSampleServiceTest extends TestCase
{
    public function testRejectsInvalidEmailSampleInputs(): void
    {
        $service = new TemplateSampleService(new Application(), new TemplateSampleDatabase([]));

        self::assertNull($service->createEmailSample(0, new \stdClass()));
        self::assertNull($service->createEmailSample(1, null));
    }

    public function testBuildsPlainEmailSampleAndSkipsHiddenFields(): void
    {
        $service = new TemplateSampleService(
            new Application(),
            new TemplateSampleDatabase([
                ['id' => 1, 'type' => 'text'],
                ['id' => 2, 'type' => 'hidden'],
            ])
        );
        $form = new class {
            public function getElementNames(): array
            {
                return ['title-ref' => 'Title', 'token-ref' => 'Token'];
            }
        };

        $sample = $service->createEmailSample(7, $form);

        self::assertStringContainsString('{hide-if-empty Title}', $sample);
        self::assertStringContainsString('{Title:label}: {Title:value}', $sample);
        self::assertStringNotContainsString('Token', $sample);
    }

    public function testBuildsHtmlEmailSample(): void
    {
        $service = new TemplateSampleService(
            new Application(),
            new TemplateSampleDatabase([['id' => 1, 'type' => 'text']])
        );
        $form = new class {
            public function getElementNames(): array
            {
                return ['title-ref' => 'Title'];
            }
        };

        $sample = $service->createEmailSample(7, $form, true);

        self::assertStringStartsWith('<table border="0" width="100%"><tbody>', $sample);
        self::assertStringContainsString('<label>{Title:label}</label>', $sample);
        self::assertStringEndsWith("</tbody></table>\n", $sample);
    }
}

final class TemplateSampleDatabase implements DatabaseInterface
{
    /**
     * @param list<array{id:int,type:string}> $rows
     */
    public function __construct(private array $rows)
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

    public function loadAssoc(): ?array
    {
        return array_shift($this->rows);
    }
}
