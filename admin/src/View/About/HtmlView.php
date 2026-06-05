<?php
/**
 * @package     ContentBuilder NG
 * @author      Xavier DANO
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/

namespace CB\Component\Contentbuilderng\Administrator\View\About;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    protected string $componentVersion = '';
    protected string $componentCreationDate = '';
    protected string $componentAuthor = '';
    protected string $componentCopyright = '';
    protected string $componentLicense = '';
    protected string $componentBuildType = '';
    protected string $componentBuildTimestamp = '';
    protected array $phpLibraries = [];
    protected array $javascriptLibraries = [];
    protected array $auditReport = [];
    protected array $logReport = [];
    protected array $packedPayloadReport = [];
    protected array $repairWorkflow = [];

    public function display($tpl = null)
    {
        /** @var AdministratorApplication $app */
        $app = Factory::getApplication();

        if ($this->getLayout() === 'help') {
            parent::display($tpl);
            return;
        }

        // 1️⃣ Récupération du WebAssetManager
        /** @var HtmlDocument $document */
        $document = $this->getDocument();
        $wa = $document->getWebAssetManager();

        // 2️⃣ Enregistrement + chargement du CSS
        $wa->registerAndUseStyle(
            'com_contentbuilderng.admin',
            'COM_CONTENTBUILDERNG/admin.css',
            [],
            ['media' => 'all']
        );

        // Icon addition.
        $wa->addInlineStyle(
            '.icon-logo_left{
                background-image:url(' . Uri::root(true) . '/media/com_contentbuilderng/images/logo_left.png);
                background-size:contain;
                background-repeat:no-repeat;
                background-position:center;
                display:inline-block;
                width:48px;
                height:48px;
                vertical-align:middle;
            }'
        );

        ToolbarHelper::title(
            $this->getLayout() === 'packedpayload'
                ? Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW_TITLE')
                : Text::_('COM_CONTENTBUILDERNG') . ' / ' . Text::_('COM_CONTENTBUILDERNG_ABOUT'),
            'logo_left'
        );

        /** @var Toolbar $toolbar */
        $toolbar = $document->getToolbar('toolbar');
        $maintenanceDropdown = $toolbar->dropdownButton('about-maintenance-group');
        $maintenanceDropdown->text(Text::_('COM_CONTENTBUILDERNG_TOOLBAR_ACTIONS'));
        $maintenanceDropdown->toggleSplit(false);
        $maintenanceDropdown->icon('fa fa-wrench');
        $maintenanceDropdown->buttonClass('btn btn-action');
        $maintenanceDropdown->listCheck(false);

        $maintenanceChildToolbar = $maintenanceDropdown->getChildToolbar();
        $maintenanceChildToolbar->standardButton('about_audit')
            ->task('about.runAudit')
            ->text('COM_CONTENTBUILDERNG_ABOUT_AUDIT')
            ->icon('fa fa-search')
            ->listCheck(false);

        $maintenanceChildToolbar->standardButton('about_migrate_packed_data')
            ->task('about.startRepairWorkflow')
            ->text('COM_CONTENTBUILDERNG_ABOUT_MIGRATE_PACKED_DATA')
            ->icon('fa fa-refresh')
            ->listCheck(false);

        $maintenanceChildToolbar->standardButton('about_export_configuration')
            ->task('configtransfer.export')
            ->text('COM_CONTENTBUILDERNG_ABOUT_EXPORT_CONFIGURATION')
            ->icon('fa fa-download')
            ->listCheck(false);

        $maintenanceChildToolbar->standardButton('about_import_configuration')
            ->task('configtransfer.import')
            ->text('COM_CONTENTBUILDERNG_ABOUT_IMPORT_CONFIGURATION')
            ->icon('fa fa-upload')
            ->listCheck(false);

        $toolbar->standardButton('about_show_log')
            ->task('about.showLog')
            ->text('COM_CONTENTBUILDERNG_ABOUT_SHOW_LOG')
            ->icon('fa fa-file-text-o')
            ->listCheck(false);

        ToolbarHelper::preferences('com_contentbuilderng');
        
        ToolbarHelper::help(
            'COM_CONTENTBUILDERNG_HELP_ABOUT_TITLE',
            false,
            Uri::base() . 'index.php?option=com_contentbuilderng&view=about&layout=help&tmpl=component'
        );

        $versionInformation = $this->getVersionInformation();
        $this->componentVersion = (string) ($versionInformation['version'] ?? '');
        $this->componentCreationDate = (string) ($versionInformation['creationDate'] ?? '');
        $this->componentAuthor = (string) ($versionInformation['author'] ?? '');
        $this->componentCopyright = (string) ($versionInformation['copyright'] ?? '');
        $this->componentLicense = (string) ($versionInformation['license'] ?? '');
        $this->componentBuildType = (string) ($versionInformation['buildType'] ?? '');
        $this->componentBuildTimestamp = (string) ($versionInformation['buildTimestamp'] ?? '');
        $this->phpLibraries = $this->getInstalledPhpLibraries();
        $this->javascriptLibraries = $this->getInstalledJavascriptLibraries();
        $auditReport = $app->getUserState('com_contentbuilderng.about.audit', []);
        $this->auditReport = is_array($auditReport) ? $auditReport : [];
        $app->setUserState('com_contentbuilderng.about.audit', []);
        $logReport = $app->getUserState('com_contentbuilderng.about.log', []);
        $this->logReport = is_array($logReport) ? $logReport : [];
        $app->setUserState('com_contentbuilderng.about.log', []);
        $this->packedPayloadReport = $this->getLayout() === 'packedpayload'
            ? $this->getPackedPayloadReport($app)
            : [];
        $repairWorkflow = $app->getUserState('com_contentbuilderng.about.repair_workflow', []);
        $this->repairWorkflow = is_array($repairWorkflow) ? $repairWorkflow : [];

        // 3️⃣ Affichage du layout
        parent::display($tpl);
    }

    private function getVersionInformation(): array
    {
        $versionInformation = [
            'version' => '',
            'creationDate' => '',
            'author' => '',
            'copyright' => '',
            'license' => '',
            'buildType' => '',
            'buildTimestamp' => '',
        ];

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_contentbuilderng'));

            $db->setQuery($query);
            $manifestCache = (string) $db->loadResult();

            if ($manifestCache !== '') {
                $manifestData = json_decode($manifestCache, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($manifestData)) {
                    $versionInformation['version'] = (string) ($manifestData['version'] ?? '');
                    $versionInformation['creationDate'] = (string) ($manifestData['creationDate'] ?? '');
                    $versionInformation['author'] = (string) ($manifestData['author'] ?? '');
                    $versionInformation['copyright'] = (string) ($manifestData['copyright'] ?? '');
                    $versionInformation['license'] = (string) ($manifestData['license'] ?? '');
                    $versionInformation['buildType'] = (string) ($manifestData['buildType'] ?? '');
                    $versionInformation['buildTimestamp'] = (string) ($manifestData['buildTimestamp'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            // Ignore and fallback to manifest XML.
        }

        $needsManifestFallback = false;

        foreach (['version', 'creationDate', 'author', 'copyright', 'license', 'buildType', 'buildTimestamp'] as $key) {
            if (trim((string) ($versionInformation[$key] ?? '')) === '') {
                $needsManifestFallback = true;
                break;
            }
        }

        if (!$needsManifestFallback) {
            return $versionInformation;
        }

        $manifestPath = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng/com_contentbuilderng.xml';

        if (!is_file($manifestPath)) {
            return $versionInformation;
        }

        $manifest = simplexml_load_file($manifestPath);

        if ($manifest instanceof \SimpleXMLElement) {
            $manifestValues = [
                'version' => (string) ($manifest->version ?? ''),
                'creationDate' => (string) ($manifest->creationDate ?? ''),
                'author' => (string) ($manifest->author ?? ''),
                'copyright' => (string) ($manifest->copyright ?? ''),
                'license' => (string) ($manifest->license ?? ''),
                'buildType' => (string) ($manifest->buildType ?? ''),
                'buildTimestamp' => (string) ($manifest->buildTimestamp ?? ''),
            ];

            foreach ($manifestValues as $key => $value) {
                if (trim((string) ($versionInformation[$key] ?? '')) === '' && trim($value) !== '') {
                    $versionInformation[$key] = $value;
                }
            }
        }

        return $versionInformation;
    }

    private function getPackedPayloadReport(AdministratorApplication $app): array
    {
        $source = trim((string) $app->getInput()->get('packed_source', '', 'cmd'));
        $recordId = $app->getInput()->getInt('id', 0);

        if (!in_array($source, ['elements', 'forms'], true) || $recordId <= 0) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            if ($source === 'elements') {
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('e.id'),
                        $db->quoteName('e.label'),
                        $db->quoteName('e.reference_id'),
                        $db->quoteName('e.options', 'raw_value'),
                    ])
                    ->from($db->quoteName('#__contentbuilderng_elements', 'e'))
                    ->where($db->quoteName('e.id') . ' = ' . $recordId);

                $table = '#__contentbuilderng_elements';
                $column = 'options';
            } else {
                $query = $db->getQuery(true)
                    ->select([
                        $db->quoteName('f.id'),
                        $db->quoteName('f.name'),
                        $db->quoteName('f.title'),
                        $db->quoteName('f.config', 'raw_value'),
                    ])
                    ->from($db->quoteName('#__contentbuilderng_forms', 'f'))
                    ->where($db->quoteName('f.id') . ' = ' . $recordId);

                $table = '#__contentbuilderng_forms';
                $column = 'config';
            }

            $db->setQuery($query);
            $row = $db->loadAssoc();

            if (!is_array($row) || $row === []) {
                return [
                    'status' => 'error',
                    'message' => Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PACKED_DATA_RAW_NOT_FOUND'),
                ];
            }

            $recordLabel = trim((string) ($row['label'] ?? ''));
            if ($recordLabel === '') {
                $recordLabel = trim((string) ($row['reference_id'] ?? ''));
            }
            if ($recordLabel === '') {
                $recordLabel = trim((string) ($row['name'] ?? ''));
            }
            if ($recordLabel === '') {
                $recordLabel = trim((string) ($row['title'] ?? ''));
            }
            if ($recordLabel === '') {
                $recordLabel = '#' . $recordId;
            }

            return [
                'status' => 'ok',
                'table' => $table,
                'column' => $column,
                'record_id' => $recordId,
                'record_label' => $recordLabel,
                'raw_value' => (string) ($row['raw_value'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getInstalledPhpLibraries(): array
    {
        $componentRoot = JPATH_ADMINISTRATOR . '/components/com_contentbuilderng';
        $libraries = $this->readInstalledLibrariesFromVendor($componentRoot);

        if ($libraries === []) {
            $libraries = $this->readInstalledLibrariesFromComposerLock($componentRoot);
        }

        $this->mergeDirectRequirements($libraries, $componentRoot);

        usort(
            $libraries,
            static fn(array $a, array $b): int => strcmp($a['name'], $b['name'])
        );

        return $libraries;
    }

    private function readInstalledLibrariesFromVendor(string $componentRoot): array
    {
        $libraries = [];
        $installedPhp = $componentRoot . '/vendor/composer/installed.php';

        if (is_file($installedPhp)) {
            $installedData = include $installedPhp;

            if (is_array($installedData)) {
                $rootPackageName = (string) ($installedData['root']['name'] ?? '');
                $versions = $installedData['versions'] ?? [];

                if (is_array($versions)) {
                    foreach ($versions as $packageName => $packageData) {
                        if (!is_array($packageData)) {
                            continue;
                        }

                        if ($packageName === '__root__' || $packageName === $rootPackageName) {
                            continue;
                        }

                        if (!empty($packageData['dev_requirement'])) {
                            continue;
                        }

                        $libraries[] = [
                            'name' => (string) $packageName,
                            'version' => (string) ($packageData['pretty_version'] ?? $packageData['version'] ?? ''),
                            'is_dev' => (bool) ($packageData['dev_requirement'] ?? false),
                        ];
                    }
                }
            }
        }

        $installedJson = $componentRoot . '/vendor/composer/installed.json';

        if ($libraries === [] && is_file($installedJson)) {
            $jsonData = file_get_contents($installedJson);

            if (is_string($jsonData) && $jsonData !== '') {
                $installed = json_decode($jsonData, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($installed)) {
                    $packages = $installed['packages'] ?? $installed;

                    if (is_array($packages)) {
                        foreach ($packages as $package) {
                            if (!is_array($package)) {
                                continue;
                            }

                            $packageName = (string) ($package['name'] ?? '');

                            if ($packageName === '') {
                                continue;
                            }

                            if (!empty($package['dev_requirement'])) {
                                continue;
                            }

                            $libraries[] = [
                                'name' => $packageName,
                                'version' => (string) ($package['version'] ?? ''),
                                'is_dev' => false,
                            ];
                        }
                    }
                }
            }
        }

        return $libraries;
    }

    private function readInstalledLibrariesFromComposerLock(string $componentRoot): array
    {
        $composerLock = $componentRoot . '/composer.lock';

        if (!is_file($composerLock)) {
            return [];
        }

        $jsonData = file_get_contents($composerLock);

        if (!is_string($jsonData) || $jsonData === '') {
            return [];
        }

        $lockData = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($lockData)) {
            return [];
        }

        $libraries = [];
        $runtimePackages = $lockData['packages'] ?? [];

        if (is_array($runtimePackages)) {
            foreach ($runtimePackages as $package) {
                if (!is_array($package)) {
                    continue;
                }

                $packageName = (string) ($package['name'] ?? '');

                if ($packageName === '') {
                    continue;
                }

                $libraries[] = [
                    'name' => $packageName,
                    'version' => (string) ($package['version'] ?? ''),
                    'is_dev' => false,
                ];
            }
        }

        return $libraries;
    }

    private function mergeDirectRequirements(array &$libraries, string $componentRoot): void
    {
        $composerJson = $componentRoot . '/composer.json';

        if (!is_file($composerJson)) {
            return;
        }

        $jsonData = file_get_contents($composerJson);

        if (!is_string($jsonData) || $jsonData === '') {
            return;
        }

        $composerData = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($composerData)) {
            return;
        }

        $indexed = [];

        foreach ($libraries as $index => $library) {
            $name = (string) ($library['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $indexed[$name] = $index;
        }

        $this->mergeRequirementSet($libraries, $indexed, $composerData['require'] ?? [], false);
    }

    private function mergeRequirementSet(array &$libraries, array &$indexed, mixed $requirements, bool $isDev): void
    {
        if (!is_array($requirements)) {
            return;
        }

        foreach ($requirements as $packageName => $constraint) {
            $name = (string) $packageName;

            // Skip platform requirements like "php" and "ext-*".
            if ($name === '' || !str_contains($name, '/')) {
                continue;
            }

            $constraintValue = (string) $constraint;

            if (isset($indexed[$name])) {
                $existingIndex = $indexed[$name];

                if (($libraries[$existingIndex]['version'] ?? '') === '' && $constraintValue !== '') {
                    $libraries[$existingIndex]['version'] = $constraintValue;
                }

                continue;
            }

            $libraries[] = [
                'name' => $name,
                'version' => $constraintValue,
                'is_dev' => $isDev,
            ];
            $indexed[$name] = \count($libraries) - 1;
        }
    }

    private function getInstalledJavascriptLibraries(): array
    {
        $assetFile = JPATH_ROOT . '/media/com_contentbuilderng/joomla.asset.json';

        if (!is_file($assetFile)) {
            return [];
        }

        $jsonData = file_get_contents($assetFile);

        if (!is_string($jsonData) || $jsonData === '') {
            return [];
        }

        $assetData = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($assetData)) {
            return [];
        }

        $assets = $assetData['assets'] ?? [];

        if (!is_array($assets)) {
            return [];
        }

        $libraries = [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');
            $uri = (string) ($asset['uri'] ?? '');

            if (!str_contains(strtolower($name . ' ' . $uri), 'coloris')) {
                continue;
            }

            $type = strtolower((string) ($asset['type'] ?? ''));

            if ($type !== 'script' && $type !== 'style') {
                continue;
            }

            $key = 'coloris';

            if (!isset($libraries[$key])) {
                $libraries[$key] = [
                    'name' => 'Coloris',
                    'version' => $this->extractVersionFromUri($uri),
                    'assets' => [],
                    'source' => $this->extractHostFromUri($uri),
                ];
            }

            if ($libraries[$key]['version'] === '' && $uri !== '') {
                $libraries[$key]['version'] = $this->extractVersionFromUri($uri);
            }

            if (($libraries[$key]['source'] ?? '') === '' && $uri !== '') {
                $libraries[$key]['source'] = $this->extractHostFromUri($uri);
            }

            $assetLabel = $type === 'script' ? 'JS' : 'CSS';

            if (!in_array($assetLabel, $libraries[$key]['assets'], true)) {
                $libraries[$key]['assets'][] = $assetLabel;
            }
        }

        foreach ($libraries as &$library) {
            sort($library['assets']);
            $library['assets'] = implode(' + ', $library['assets']);
            if (($library['version'] ?? '') === '') {
                $library['version'] = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            }
            if (($library['source'] ?? '') === '') {
                $library['source'] = Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE');
            }
        }
        unset($library);

        return array_values($libraries);
    }

    private function extractVersionFromUri(string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        if (preg_match('/@([0-9]+(?:\.[0-9]+){1,3})/', $uri, $matches)) {
            return (string) $matches[1];
        }

        return '';
    }

    private function extractHostFromUri(string $uri): string
    {
        if ($uri === '') {
            return '';
        }

        $host = parse_url($uri, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }
}
