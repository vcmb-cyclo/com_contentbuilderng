<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Date\Date;

/**
 * Session state for the "Assistant" (Storage creation wizard): Storage ->
 * Fields (CSV import or manual) -> Formulaire -> Menu. Mirrors the
 * getUserState()/setUserState() pattern already used by
 * RepairWorkflowService, but for a simple linear flow instead of an
 * audit/repair checklist.
 */
class StorageWizardService
{
    public const STATE_KEY = 'com_contentbuilderng.storagewizard';

    public const STEP_STORAGE = 'storage';
    public const STEP_FIELDS = 'fields';
    public const STEP_FORM = 'form';
    public const STEP_MENU = 'menu';
    public const STEP_DONE = 'done';

    public const STEPS = [
        self::STEP_STORAGE,
        self::STEP_FIELDS,
        self::STEP_FORM,
        self::STEP_MENU,
        self::STEP_DONE,
    ];

    public function __construct(private readonly AdministratorApplication $app)
    {
    }

    public function createState(): array
    {
        return [
            'current_step' => self::STEP_STORAGE,
            'storage_id' => 0,
            'form_id' => 0,
            'menu_item_id' => 0,
            'started_at' => (new Date())->toSql(),
        ];
    }

    public function getState(): array
    {
        $state = $this->app->getUserState(self::STATE_KEY, []);

        if (!is_array($state) || $state === [] || !isset($state['current_step'])) {
            return $this->createState();
        }

        return $state;
    }

    public function saveState(array $state): void
    {
        $this->app->setUserState(self::STATE_KEY, $state);
    }

    public function reset(): void
    {
        $this->app->setUserState(self::STATE_KEY, $this->createState());
    }

    public function advanceTo(array $state, string $step): array
    {
        $state['current_step'] = in_array($step, self::STEPS, true) ? $step : self::STEP_STORAGE;

        return $state;
    }

    public function stepIndex(string $step): int
    {
        $index = array_search($step, self::STEPS, true);

        return $index === false ? 0 : (int) $index;
    }
}
