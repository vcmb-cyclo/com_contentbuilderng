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
use Joomla\Database\DatabaseInterface;
use LogicException;

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
            throw new LogicException('Runtime application context has not been initialized.');
        }

        return self::$app;
    }

    public static function getDatabase(): DatabaseInterface
    {
        if (self::$db === null) {
            throw new LogicException('Runtime database context has not been initialized.');
        }

        return self::$db;
    }
}
