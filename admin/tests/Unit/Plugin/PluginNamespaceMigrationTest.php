<?php

declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Tests\Unit\Plugin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that every plugin follows the Joomla 6 namespaced structure:
 *   - services/provider.php exists and is not empty
 *   - src/Extension/{Class}.php exists
 *   - src/Extension/{Class}.php declares the correct namespace
 *   - src/Extension/{Class}.php declares a final class
 *   - XML manifest contains a <namespace> tag
 *   - Old root .php entry point is gone
 */
final class PluginNamespaceMigrationTest extends TestCase
{
    private string $pluginsRoot;

    protected function setUp(): void
    {
        $this->pluginsRoot = \dirname(__DIR__, 4) . '/plugins';
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function pluginProvider(): array
    {
        // [group, element, class, namespace]
        return [
            'themes/blank'   => ['contentbuilderng_themes', 'blank',   'Blank',   'CB\Plugin\ContentbuilderngThemes\Blank\Extension'],
            'themes/dark'    => ['contentbuilderng_themes', 'dark',    'Dark',    'CB\Plugin\ContentbuilderngThemes\Dark\Extension'],
            'themes/thoth' => ['contentbuilderng_themes', 'thoth', 'Thoth', 'CB\Plugin\ContentbuilderngThemes\Thoth\Extension'],
            'themes/khepri'  => ['contentbuilderng_themes', 'khepri',  'Khepri',  'CB\Plugin\ContentbuilderngThemes\Khepri\Extension'],

            'validation/email'           => ['contentbuilderng_validation', 'email',           'Email',         'CB\Plugin\ContentbuilderngValidation\Email\Extension'],
            'validation/notempty'        => ['contentbuilderng_validation', 'notempty',        'Notempty',      'CB\Plugin\ContentbuilderngValidation\Notempty\Extension'],
            'validation/equal'           => ['contentbuilderng_validation', 'equal',           'Equal',         'CB\Plugin\ContentbuilderngValidation\Equal\Extension'],
            'validation/date_is_valid'   => ['contentbuilderng_validation', 'date_is_valid',   'DateIsValid',   'CB\Plugin\ContentbuilderngValidation\DateIsValid\Extension'],
            'validation/date_not_before' => ['contentbuilderng_validation', 'date_not_before', 'DateNotBefore', 'CB\Plugin\ContentbuilderngValidation\DateNotBefore\Extension'],

            'listaction/trash'   => ['contentbuilderng_listaction', 'trash',   'Trash',   'CB\Plugin\ContentbuilderngListaction\Trash\Extension'],
            'listaction/untrash' => ['contentbuilderng_listaction', 'untrash', 'Untrash', 'CB\Plugin\ContentbuilderngListaction\Untrash\Extension'],

            'submit/submit_sample' => ['contentbuilderng_submit', 'submit_sample', 'SubmitSample', 'CB\Plugin\ContentbuilderngSubmit\SubmitSample\Extension'],

            'verify/passthrough' => ['contentbuilderng_verify', 'passthrough', 'Passthrough', 'CB\Plugin\ContentbuilderngVerify\Passthrough\Extension'],
            'verify/paypal'      => ['contentbuilderng_verify', 'paypal',      'Paypal',      'CB\Plugin\ContentbuilderngVerify\Paypal\Extension'],

            'content/download'            => ['content', 'contentbuilderng_download',            'ContentbuilderngDownload',           'CB\Plugin\Content\ContentbuilderngDownload\Extension'],
            'content/image_scale'         => ['content', 'contentbuilderng_image_scale',         'ContentbuilderngImageScale',         'CB\Plugin\Content\ContentbuilderngImageScale\Extension'],
            'content/permission_observer' => ['content', 'contentbuilderng_permission_observer', 'ContentbuilderngPermissionObserver', 'CB\Plugin\Content\ContentbuilderngPermissionObserver\Extension'],
            'content/rating'              => ['content', 'contentbuilderng_rating',              'ContentbuilderngRating',             'CB\Plugin\Content\ContentbuilderngRating\Extension'],
            'content/stats'               => ['content', 'contentbuilderng_stats',               'ContentbuilderngStats',              'CB\Plugin\Content\ContentbuilderngStats\Extension'],
            'content/verify'              => ['content', 'contentbuilderng_verify',              'ContentbuilderngVerify',             'CB\Plugin\Content\ContentbuilderngVerify\Extension'],

            'system/contentbuilderng_system' => ['system', 'contentbuilderng_system', 'ContentbuilderngSystem', 'CB\Plugin\System\ContentbuilderngSystem\Extension'],
        ];
    }

    #[DataProvider('pluginProvider')]
    public function testProviderFileExists(string $group, string $element, string $_class, string $_namespace): void
    {
        $path = $this->pluginsRoot . "/{$group}/{$element}/services/provider.php";
        self::assertFileExists($path, "services/provider.php manquant pour {$group}/{$element}");
        self::assertGreaterThan(0, \filesize($path), "services/provider.php vide pour {$group}/{$element}");
    }

    #[DataProvider('pluginProvider')]
    public function testExtensionClassFileExists(string $group, string $element, string $class, string $_namespace): void
    {
        $path = $this->pluginsRoot . "/{$group}/{$element}/src/Extension/{$class}.php";
        self::assertFileExists($path, "src/Extension/{$class}.php manquant pour {$group}/{$element}");
    }

    #[DataProvider('pluginProvider')]
    public function testExtensionClassDeclaresFinalClass(string $group, string $element, string $class, string $_namespace): void
    {
        $path = $this->pluginsRoot . "/{$group}/{$element}/src/Extension/{$class}.php";
        $source = \file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString(
            "final class {$class} ",
            $source,
            "final class {$class} introuvable dans {$group}/{$element}"
        );
    }

    #[DataProvider('pluginProvider')]
    public function testExtensionClassDeclaresCorrectNamespace(string $group, string $element, string $class, string $namespace): void
    {
        $path = $this->pluginsRoot . "/{$group}/{$element}/src/Extension/{$class}.php";
        $source = \file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString(
            "namespace {$namespace};",
            $source,
            "Namespace {$namespace} introuvable dans {$group}/{$element}"
        );
    }

    #[DataProvider('pluginProvider')]
    public function testXmlManifestHasNamespaceTag(string $group, string $element, string $_class, string $_namespace): void
    {
        $xml = $this->pluginsRoot . "/{$group}/{$element}/{$element}.xml";
        $source = \file_get_contents($xml);
        self::assertIsString($source, "Manifest XML introuvable pour {$group}/{$element}");
        self::assertStringContainsString(
            '<namespace path="src">',
            $source,
            "<namespace> manquant dans le manifest de {$group}/{$element}"
        );
        self::assertStringContainsString(
            'services/provider.php',
            $source,
            "services/provider.php manquant dans le manifest de {$group}/{$element}"
        );
    }

    #[DataProvider('pluginProvider')]
    public function testLegacyRootPhpIsGone(string $group, string $element, string $_class, string $_namespace): void
    {
        $legacyPath = $this->pluginsRoot . "/{$group}/{$element}/{$element}.php";
        self::assertFileDoesNotExist(
            $legacyPath,
            "L'ancien {$element}.php racine existe encore pour {$group}/{$element}"
        );
    }
}
