<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Model;

use CB\Component\Contentbuilderng\Administrator\Model\StorageModel;
use PHPUnit\Framework\TestCase;

final class StorageModelTest extends TestCase
{
    private StorageModel $model;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(StorageModel::class);
        $this->model = $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @dataProvider normalizeFieldIdentifierProvider
     */
    public function testNormalizeFieldIdentifier(string $input, string $expected): void
    {
        $result = $this->invokePrivateMethod('normalizeFieldIdentifier', $input);

        self::assertSame($expected, $result);
    }

    public static function normalizeFieldIdentifierProvider(): array
    {
        return [
            'french accent' => ['Prénom', 'Prenom'],
            'latin1 french accent' => ["Pr\xE9nom", 'Prenom'],
            'ligature and symbol' => ['cœur & âme', 'coeur_ame'],
            'german sharp s' => ['Straße', 'Strasse'],
            'turkish dotted i' => ['İsim', 'Isim'],
            'leading digit' => ['123 titre', 'field_123_titre'],
            'spaces and separators' => ['  hello---world  ', 'hello_world'],
            'only symbols' => ['***', ''],
        ];
    }

    private function invokePrivateMethod(string $method, ...$args)
    {
        $reflection = new \ReflectionClass($this->model);
        $target = $reflection->getMethod($method);
        $target->setAccessible(true);

        return $target->invoke($this->model, ...$args);
    }
}
