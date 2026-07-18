<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Helper;

use CB\Component\Contentbuilderng\Administrator\Helper\PackedDataHelper;
use PHPUnit\Framework\TestCase;

require_once \dirname(__DIR__, 4) . '/admin/src/Helper/PackedDataHelper.php';

final class PackedDataHelperTest extends TestCase
{
    public function testDecodePackedDataAcceptsModernJsonPayload(): void
    {
        $payload = base64_encode('j:{"enabled":true,"label":"ok"}');

        self::assertSame(
            ['enabled' => true, 'label' => 'ok'],
            PackedDataHelper::decodePackedData($payload, null, true)
        );
    }

    public function testDecodePackedDataRejectsLegacySerializedPayload(): void
    {
        $legacyPayload = base64_encode('a:1:{s:5:"label";s:6:"legacy";}');

        self::assertSame(
            'fallback',
            PackedDataHelper::decodePackedData($legacyPayload, 'fallback', true)
        );
    }

    public function testDecodePackedDataRejectsUnprefixedJsonPayload(): void
    {
        $legacyJsonPayload = base64_encode('{"enabled":true}');

        self::assertNull(PackedDataHelper::decodePackedData($legacyJsonPayload, null, true));
    }
}
