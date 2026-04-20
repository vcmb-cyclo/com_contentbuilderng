<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Service;

use CB\Component\Contentbuilderng\Administrator\Service\ApiPermissionRequirementService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiPermissionRequirementServiceTest extends TestCase
{
    private ApiPermissionRequirementService $service;

    protected function setUp(): void
    {
        $this->service = new ApiPermissionRequirementService();
    }

    /**
     * @return array<string,array{0:string,1:string,2:int,3:list<string>}>
     */
    public static function requiredPermissionProvider(): array
    {
        return [
            'detail GET requires API and View' => ['GET', '', 238, ['api', 'view']],
            'list GET requires API, View, and List Access' => ['GET', '', 0, ['api', 'view', 'listaccess']],
            'stats requires only Stats' => ['GET', 'stats', 0, ['stats']],
            'stats with record id still requires only Stats' => ['GET', 'stats', 238, ['stats']],
            'update PUT requires API and Edit' => ['PUT', '', 238, ['api', 'edit']],
            'update PATCH requires API and Edit' => ['PATCH', '', 238, ['api', 'edit']],
            'update POST requires API and Edit' => ['POST', '', 238, ['api', 'edit']],
            'unique values requires API and List Access' => ['GET', 'get-unique-values', 0, ['api', 'listaccess']],
            'rating requires API and Rating' => ['GET', 'rating', 238, ['api', 'rating']],
            'unknown action requires API before the 404 handling' => ['GET', 'unknown', 0, ['api']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('requiredPermissionProvider')]
    public function testRequiredPermissions(string $method, string $action, int $recordId, array $expected): void
    {
        self::assertSame($expected, $this->service->getRequiredPermissions($method, $action, $recordId));
    }

    public function testMethodAndActionAreNormalized(): void
    {
        self::assertSame(['stats'], $this->service->getRequiredPermissions(' get ', ' stats ', 0));
    }
}
