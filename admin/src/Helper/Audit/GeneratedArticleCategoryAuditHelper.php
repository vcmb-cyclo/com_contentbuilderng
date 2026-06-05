<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper\Audit;

\defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

final class GeneratedArticleCategoryAuditHelper
{
    /**
     * @return array{0:array<int,array<string,mixed>>,1:array<int,string>}
     */
    public static function inspect(DatabaseInterface $db): array
    {
        $errors = [];

        try {
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('form.id', 'form_id'),
                    $db->quoteName('form.name', 'form_name'),
                    $db->quoteName('form.default_category', 'default_category_id'),
                    $db->quoteName('default_category.id', 'valid_default_category_id'),
                    $db->quoteName('default_category.title', 'default_category_title'),
                    'COUNT(' . $db->quoteName('content.id') . ') AS ' . $db->quoteName('article_count'),
                    'SUM(CASE WHEN ' . $db->quoteName('content.id') . ' IS NOT NULL AND ' . $db->quoteName('content_category.id') . ' IS NULL THEN 1 ELSE 0 END) AS ' . $db->quoteName('invalid_category_count'),
                    'SUM(CASE WHEN ' . $db->quoteName('content.id') . ' IS NOT NULL AND ' . $db->quoteName('content_asset.id') . ' IS NULL THEN 1 ELSE 0 END) AS ' . $db->quoteName('missing_asset_count'),
                    'SUM(CASE WHEN ' . $db->quoteName('content.id') . ' IS NOT NULL AND ' . $db->quoteName('workflow.item_id') . ' IS NULL THEN 1 ELSE 0 END) AS ' . $db->quoteName('missing_workflow_count'),
                ])
                ->from($db->quoteName('#__contentbuilderng_forms', 'form'))
                ->join('LEFT', $db->quoteName('#__categories', 'default_category') . ' ON ('
                    . $db->quoteName('default_category.id') . ' = ' . $db->quoteName('form.default_category')
                    . ' AND ' . $db->quoteName('default_category.extension') . ' = ' . $db->quote('com_content')
                    . ' AND ' . $db->quoteName('default_category.published') . ' IN (0, 1)'
                    . ')')
                ->join('LEFT', $db->quoteName('#__contentbuilderng_articles', 'article') . ' ON ' . $db->quoteName('article.form_id') . ' = ' . $db->quoteName('form.id'))
                ->join('LEFT', $db->quoteName('#__content', 'content') . ' ON ' . $db->quoteName('content.id') . ' = ' . $db->quoteName('article.article_id'))
                ->join('LEFT', $db->quoteName('#__categories', 'content_category') . ' ON ('
                    . $db->quoteName('content_category.id') . ' = ' . $db->quoteName('content.catid')
                    . ' AND ' . $db->quoteName('content_category.extension') . ' = ' . $db->quote('com_content')
                    . ' AND ' . $db->quoteName('content_category.published') . ' IN (0, 1)'
                    . ')')
                ->join('LEFT', $db->quoteName('#__assets', 'content_asset') . ' ON ('
                    . $db->quoteName('content_asset.id') . ' = ' . $db->quoteName('content.asset_id')
                    . ' AND ' . $db->quoteName('content_asset.name') . ' = CONCAT(' . $db->quote('com_content.article.') . ', ' . $db->quoteName('content.id') . ')'
                    . ')')
                ->join('LEFT', $db->quoteName('#__workflow_associations', 'workflow') . ' ON ('
                    . $db->quoteName('workflow.item_id') . ' = ' . $db->quoteName('content.id')
                    . ' AND ' . $db->quoteName('workflow.extension') . ' = ' . $db->quote('com_content.article')
                    . ')')
                ->where($db->quoteName('form.published') . ' = 1')
                ->where($db->quoteName('form.create_articles') . ' = 1')
                ->group([
                    $db->quoteName('form.id'),
                    $db->quoteName('form.name'),
                    $db->quoteName('form.default_category'),
                    $db->quoteName('default_category.id'),
                    $db->quoteName('default_category.title'),
                ])
                ->having(
                    $db->quoteName('valid_default_category_id') . ' IS NULL'
                    . ' OR ' . $db->quoteName('invalid_category_count') . ' > 0'
                    . ' OR ' . $db->quoteName('missing_asset_count') . ' > 0'
                    . ' OR ' . $db->quoteName('missing_workflow_count') . ' > 0'
                )
                ->order($db->quoteName('form.id') . ' ASC');
            $db->setQuery($query);
            $rows = $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [[], ['Could not inspect generated article categories: ' . $e->getMessage()]];
        }

        $issues = [];

        foreach ($rows as $row) {
            $formId = (int) ($row['form_id'] ?? 0);

            if ($formId <= 0) {
                continue;
            }

            $issues[] = [
                'form_id' => $formId,
                'form_name' => (string) ($row['form_name'] ?? ''),
                'default_category_id' => (int) ($row['default_category_id'] ?? 0),
                'default_category_title' => (string) ($row['default_category_title'] ?? ''),
                'default_category_valid' => trim((string) ($row['default_category_title'] ?? '')) !== '',
                'article_count' => (int) ($row['article_count'] ?? 0),
                'invalid_category_count' => (int) ($row['invalid_category_count'] ?? 0),
                'missing_asset_count' => (int) ($row['missing_asset_count'] ?? 0),
                'missing_workflow_count' => (int) ($row['missing_workflow_count'] ?? 0),
                'invalid_article_count' => (int) ($row['invalid_category_count'] ?? 0)
                    + (int) ($row['missing_asset_count'] ?? 0)
                    + (int) ($row['missing_workflow_count'] ?? 0),
                'invalid_articles' => self::loadInvalidArticles($db, $formId, $errors),
            ];
        }

        return [$issues, $errors];
    }

    /**
     * @return array<string,mixed>
     */
    public static function repair(DatabaseInterface $db): array
    {
        [$issues, $warnings] = self::inspect($db);
        $fallbackCategory = self::loadFallbackCategory($db);
        $summary = [
            'scanned' => count($issues),
            'issues' => count($issues),
            'repaired' => 0,
            'unchanged' => 0,
            'errors' => count($warnings),
            'forms_updated' => 0,
            'articles_updated' => 0,
            'assets_updated' => 0,
            'workflows_updated' => 0,
            'forms' => [],
            'warnings' => $warnings,
        ];

        if (!$fallbackCategory) {
            $summary['errors']++;
            $summary['warnings'][] = 'No published or unpublished com_content category is available for generated articles.';
            return $summary;
        }

        foreach ($issues as $issue) {
            $formId = (int) ($issue['form_id'] ?? 0);
            $targetCategoryId = self::resolveTargetCategoryId($db, $issue, (int) $fallbackCategory['id']);
            $entry = [
                'form_id' => $formId,
                'form_name' => (string) ($issue['form_name'] ?? ''),
                'from_category_id' => (int) ($issue['default_category_id'] ?? 0),
                'to_category_id' => $targetCategoryId,
                'articles_updated' => 0,
                'assets_updated' => 0,
                'workflows_updated' => 0,
                'form_updated' => false,
                'status' => 'unchanged',
                'error' => '',
            ];

            if ($formId <= 0 || $targetCategoryId <= 0) {
                $summary['unchanged']++;
                $summary['forms'][] = $entry;
                continue;
            }

            try {
                if (empty($issue['default_category_valid'])) {
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__contentbuilderng_forms'))
                        ->set($db->quoteName('default_category') . ' = ' . $targetCategoryId)
                        ->where($db->quoteName('id') . ' = ' . $formId);
                    $db->setQuery($query);
                    $db->execute();
                    $entry['form_updated'] = true;
                    $summary['forms_updated']++;
                }

                $query = $db->getQuery(true)
                    ->select($db->quoteName('content.id'))
                    ->from($db->quoteName('#__contentbuilderng_articles', 'article'))
                    ->join('INNER', $db->quoteName('#__content', 'content') . ' ON ' . $db->quoteName('content.id') . ' = ' . $db->quoteName('article.article_id'))
                    ->join('LEFT', $db->quoteName('#__categories', 'category') . ' ON ('
                        . $db->quoteName('category.id') . ' = ' . $db->quoteName('content.catid')
                        . ' AND ' . $db->quoteName('category.extension') . ' = ' . $db->quote('com_content')
                        . ' AND ' . $db->quoteName('category.published') . ' IN (0, 1)'
                        . ')')
                    ->join('LEFT', $db->quoteName('#__assets', 'asset') . ' ON ('
                        . $db->quoteName('asset.id') . ' = ' . $db->quoteName('content.asset_id')
                        . ' AND ' . $db->quoteName('asset.name') . ' = CONCAT(' . $db->quote('com_content.article.') . ', ' . $db->quoteName('content.id') . ')'
                        . ')')
                    ->join('LEFT', $db->quoteName('#__workflow_associations', 'workflow') . ' ON ('
                        . $db->quoteName('workflow.item_id') . ' = ' . $db->quoteName('content.id')
                        . ' AND ' . $db->quoteName('workflow.extension') . ' = ' . $db->quote('com_content.article')
                        . ')')
                    ->where($db->quoteName('article.form_id') . ' = ' . $formId)
                    ->where('('
                        . $db->quoteName('category.id') . ' IS NULL'
                        . ' OR ' . $db->quoteName('asset.id') . ' IS NULL'
                        . ' OR ' . $db->quoteName('workflow.item_id') . ' IS NULL'
                        . ')');
                $db->setQuery($query);
                $articleIds = array_map('intval', $db->loadColumn() ?: []);

                if ($articleIds !== []) {
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__content'))
                        ->set($db->quoteName('catid') . ' = ' . $targetCategoryId)
                        ->where($db->quoteName('id') . ' IN (' . implode(',', $articleIds) . ')');
                    $db->setQuery($query);
                    $db->execute();
                    $entry['articles_updated'] = count($articleIds);
                    $summary['articles_updated'] += count($articleIds);
                }

                foreach ($articleIds as $articleId) {
                    if (self::ensureArticleAsset($db, $articleId, $targetCategoryId)) {
                        $entry['assets_updated']++;
                        $summary['assets_updated']++;
                    }

                    if (self::ensureWorkflowAssociation($db, $articleId)) {
                        $entry['workflows_updated']++;
                        $summary['workflows_updated']++;
                    }
                }

                if ($entry['form_updated'] || $entry['articles_updated'] > 0 || $entry['assets_updated'] > 0 || $entry['workflows_updated'] > 0) {
                    $entry['status'] = 'repaired';
                    $summary['repaired']++;
                } else {
                    $summary['unchanged']++;
                }
            } catch (\Throwable $e) {
                $entry['status'] = 'error';
                $entry['error'] = $e->getMessage();
                $summary['errors']++;
            }

            $summary['forms'][] = $entry;
        }

        return $summary;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function loadInvalidArticles(DatabaseInterface $db, int $formId, array &$errors): array
    {
        try {
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('article.article_id'),
                    $db->quoteName('article.record_id'),
                    $db->quoteName('content.catid'),
                    $db->quoteName('content.asset_id'),
                    $db->quoteName('category.title', 'category_title'),
                    $db->quoteName('asset.id', 'valid_asset_id'),
                    $db->quoteName('workflow.stage_id', 'workflow_stage_id'),
                ])
                ->from($db->quoteName('#__contentbuilderng_articles', 'article'))
                ->join('INNER', $db->quoteName('#__content', 'content') . ' ON ' . $db->quoteName('content.id') . ' = ' . $db->quoteName('article.article_id'))
                ->join('LEFT', $db->quoteName('#__categories', 'category') . ' ON ('
                    . $db->quoteName('category.id') . ' = ' . $db->quoteName('content.catid')
                    . ' AND ' . $db->quoteName('category.extension') . ' = ' . $db->quote('com_content')
                    . ' AND ' . $db->quoteName('category.published') . ' IN (0, 1)'
                    . ')')
                ->join('LEFT', $db->quoteName('#__assets', 'asset') . ' ON ('
                    . $db->quoteName('asset.id') . ' = ' . $db->quoteName('content.asset_id')
                    . ' AND ' . $db->quoteName('asset.name') . ' = CONCAT(' . $db->quote('com_content.article.') . ', ' . $db->quoteName('content.id') . ')'
                    . ')')
                ->join('LEFT', $db->quoteName('#__workflow_associations', 'workflow') . ' ON ('
                    . $db->quoteName('workflow.item_id') . ' = ' . $db->quoteName('content.id')
                    . ' AND ' . $db->quoteName('workflow.extension') . ' = ' . $db->quote('com_content.article')
                    . ')')
                ->where($db->quoteName('article.form_id') . ' = ' . $formId)
                ->where('('
                    . $db->quoteName('category.id') . ' IS NULL'
                    . ' OR ' . $db->quoteName('asset.id') . ' IS NULL'
                    . ' OR ' . $db->quoteName('workflow.item_id') . ' IS NULL'
                    . ')')
                ->order($db->quoteName('article.article_id') . ' ASC')
                ->setLimit(25);
            $db->setQuery($query);
            return $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            $errors[] = 'Could not inspect generated article category details for view #' . $formId . ': ' . $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadFallbackCategory(DatabaseInterface $db): ?array
    {
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('title')])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' IN (0, 1)')
            ->order($db->quoteName('lft') . ' ASC, ' . $db->quoteName('id') . ' ASC')
            ->setLimit(1);
        $db->setQuery($query);
        $row = $db->loadAssoc();

        return is_array($row) ? $row : null;
    }

    private static function ensureArticleAsset(DatabaseInterface $db, int $articleId, int $categoryId): bool
    {
        if ($articleId <= 0 || $categoryId <= 0) {
            return false;
        }

        $assetName = 'com_content.article.' . $articleId;
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('name')])
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('id') . ' = (SELECT ' . $db->quoteName('asset_id') . ' FROM ' . $db->quoteName('#__content') . ' WHERE ' . $db->quoteName('id') . ' = ' . $articleId . ')')
            ->where($db->quoteName('name') . ' = ' . $db->quote($assetName));
        $db->setQuery($query);

        if ($db->loadAssoc()) {
            return false;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote($assetName));
        $db->setQuery($query);
        $existingAssetId = (int) $db->loadResult();

        if ($existingAssetId > 0) {
            self::setArticleAssetId($db, $articleId, $existingAssetId);
            return true;
        }

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('level')])
            ->from($db->quoteName('#__assets'))
            ->where($db->quoteName('name') . ' = ' . $db->quote('com_content.category.' . $categoryId));
        $db->setQuery($query);
        $parentAsset = $db->loadAssoc();

        if (!is_array($parentAsset)) {
            return false;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('title'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . $articleId);
        $db->setQuery($query);
        $title = trim((string) $db->loadResult());
        $title = $title !== '' ? $title : $assetName;

        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('rgt') . ')')
            ->from($db->quoteName('#__assets'));
        $db->setQuery($query);
        $left = (int) $db->loadResult() + 1;
        $right = $left + 1;

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__assets'))
            ->columns([
                $db->quoteName('parent_id'),
                $db->quoteName('level'),
                $db->quoteName('lft'),
                $db->quoteName('rgt'),
                $db->quoteName('name'),
                $db->quoteName('title'),
                $db->quoteName('rules'),
            ])
            ->values(implode(',', [
                (int) $parentAsset['id'],
                ((int) $parentAsset['level']) + 1,
                $left,
                $right,
                $db->quote($assetName),
                $db->quote($title),
                $db->quote('{}'),
            ]));
        $db->setQuery($query);
        $db->execute();
        $assetId = (int) $db->insertid();

        if ($assetId <= 0) {
            return false;
        }

        self::setArticleAssetId($db, $articleId, $assetId);
        self::refreshRootAssetRight($db);

        return true;
    }

    private static function setArticleAssetId(DatabaseInterface $db, int $articleId, int $assetId): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('asset_id') . ' = ' . $assetId)
            ->where($db->quoteName('id') . ' = ' . $articleId);
        $db->setQuery($query);
        $db->execute();
    }

    private static function refreshRootAssetRight(DatabaseInterface $db): void
    {
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('rgt') . ') + 1')
            ->from($db->quoteName('#__assets'));
        $db->setQuery($query);
        $right = (int) $db->loadResult();

        if ($right <= 0) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__assets'))
            ->set($db->quoteName('rgt') . ' = ' . $right)
            ->where($db->quoteName('name') . ' = ' . $db->quote('root.1'))
            ->where($db->quoteName('level') . ' = 0');
        $db->setQuery($query);
        $db->execute();
    }

    private static function ensureWorkflowAssociation(DatabaseInterface $db, int $articleId): bool
    {
        if ($articleId <= 0) {
            return false;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('item_id'))
            ->from($db->quoteName('#__workflow_associations'))
            ->where($db->quoteName('item_id') . ' = ' . $articleId)
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content.article'));
        $db->setQuery($query);

        if ($db->loadResult()) {
            return false;
        }

        $stageId = self::loadDefaultWorkflowStageId($db);

        if ($stageId <= 0) {
            return false;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__workflow_associations'))
            ->columns([$db->quoteName('item_id'), $db->quoteName('stage_id'), $db->quoteName('extension')])
            ->values($articleId . ', ' . $stageId . ', ' . $db->quote('com_content.article'));
        $db->setQuery($query);
        $db->execute();

        return true;
    }

    private static function loadDefaultWorkflowStageId(DatabaseInterface $db): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('stage.id'))
            ->from($db->quoteName('#__workflow_stages', 'stage'))
            ->join('INNER', $db->quoteName('#__workflows', 'workflow') . ' ON ' . $db->quoteName('workflow.id') . ' = ' . $db->quoteName('stage.workflow_id'))
            ->where($db->quoteName('workflow.extension') . ' = ' . $db->quote('com_content.article'))
            ->where($db->quoteName('workflow.published') . ' = 1')
            ->where($db->quoteName('stage.published') . ' = 1')
            ->where($db->quoteName('stage.default') . ' = 1')
            ->order($db->quoteName('stage.id') . ' ASC')
            ->setLimit(1);
        $db->setQuery($query);
        $stageId = (int) $db->loadResult();

        if ($stageId > 0) {
            return $stageId;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__workflow_stages'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('id') . ' ASC')
            ->setLimit(1);
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    private static function resolveTargetCategoryId(DatabaseInterface $db, array $issue, int $fallbackCategoryId): int
    {
        if (!empty($issue['default_category_valid'])) {
            return (int) ($issue['default_category_id'] ?? 0);
        }

        return $fallbackCategoryId;
    }
}
