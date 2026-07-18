<?php

/**
 * @package     ContentBuilderNG
 * @author      XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use LogicException;

/**
 * Static bridge to the running application and database connection.
 *
 * Primarily initialised by ContentbuilderngComponent::boot(); consumers that
 * run before the component boots (the system/content plugins during
 * onAfterInitialise/onAfterRoute, installer steps) fall back lazily to the
 * global Factory — this helper and services/provider.php are the only
 * sanctioned Factory bridges of the component.
 */
final class RuntimeContextHelper
{
    private static ?CMSApplicationInterface $app = null;
    private static ?DatabaseInterface $db = null;

    public static function initialize(CMSApplicationInterface $app, DatabaseInterface $db): void
    {
        self::$app = $app;
        self::$db = $db;
    }

    public static function getApplication(): CMSApplicationInterface
    {
        if (self::$app === null) {
            try {
                self::$app = Factory::getApplication();
            } catch (\Throwable $exception) {
                throw new LogicException('Runtime application context has not been initialized.', 0, $exception);
            }
        }

        return self::$app;
    }

    public static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            try {
                self::$db = Factory::getContainer()->get(DatabaseInterface::class);
            } catch (\Throwable $exception) {
                throw new LogicException('Runtime database context has not been initialized.', 0, $exception);
            }
        }

        return self::$db;
    }
}
