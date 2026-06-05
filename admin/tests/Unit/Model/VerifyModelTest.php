<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Model;

use CB\Component\Contentbuilderng\Administrator\Model\VerifyModel;
use PHPUnit\Framework\TestCase;

final class VerifyModelTest extends TestCase
{
    private VerifyModel $model;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(VerifyModel::class);
        $this->model = $reflection->newInstanceWithoutConstructor();
    }

    public function testDecodePackedQueryStringAcceptsBase64AndRawInputs(): void
    {
        $encodedResult = $this->invokePrivateMethod(
            'decodePackedQueryString',
            \base64_encode('a=1&b=2')
        );
        $rawResult = $this->invokePrivateMethod('decodePackedQueryString', 'a=1&b=2');

        self::assertSame(['a' => '1', 'b' => '2'], $encodedResult);
        self::assertSame(['a' => '1', 'b' => '2'], $rawResult);
    }

    public function testDecodeInternalReturnAllowsInternalAndRejectsExternalTargets(): void
    {
        $internal = $this->invokePrivateMethod(
            'decodeInternalReturn',
            \base64_encode('index.php?option=com_contentbuilderng')
        );
        $external = $this->invokePrivateMethod(
            'decodeInternalReturn',
            \base64_encode('https://evil.example/path')
        );

        self::assertSame('index.php?option=com_contentbuilderng', $internal);
        self::assertSame('', $external);
    }

    public function testSafeRedirectTargetUsesFallbackForExternalUrl(): void
    {
        $allowed = $this->invokePrivateMethod('safeRedirectTarget', 'index.php?option=com_contentbuilderng', 'index.php');
        $blocked = $this->invokePrivateMethod('safeRedirectTarget', 'https://evil.example/path', 'index.php');

        self::assertSame('index.php?option=com_contentbuilderng', $allowed);
        self::assertSame('index.php', $blocked);
    }

    private function invokePrivateMethod(string $method, ...$args)
    {
        $reflection = new \ReflectionClass($this->model);
        $target = $reflection->getMethod($method);
        $target->setAccessible(true);

        return $target->invoke($this->model, ...$args);
    }
}
