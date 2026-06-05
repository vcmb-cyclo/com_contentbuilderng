<?php

/**
 * ContentBuilder NG Logs.
 *
 * Log file manager.
 *
 * @package     ContentBuilder NG
 * @subpackage  Site.Helper
 * @since       6.0.0
 * @author      Xavier DANO
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


declare(strict_types=1);

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\Folder;

final class Logger
{
    private const LOG_FILE = 'com_contentbuilderng.log';
    private const MAX_ROTATED_FILES = 10;
    private static bool $registered = false;

    private static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::applyJoomlaTimezone();
        self::rotateIfNeeded();

        self::$registered = true;
    }

    private static function applyJoomlaTimezone(): void
    {
        $app = Factory::getApplication();
        $offset = is_object($app) && method_exists($app, 'get') ? (string) $app->get('offset', 'UTC') : 'UTC';

        try {
            $timezone = new \DateTimeZone($offset !== '' ? $offset : 'UTC');
            date_default_timezone_set($timezone->getName());
        } catch (\Throwable) {
            date_default_timezone_set('UTC');
        }
    }

    private static function resolveLogDirectory(): string
    {
        $app = Factory::getApplication();
        $logPath = '';

        if (is_object($app) && method_exists($app, 'get')) {
            $logPath = trim((string) $app->get('log_path', ''));
        }

        if ($logPath === '') {
            $logPath = JPATH_ROOT . '/logs';
        }

        if (!is_dir($logPath)) {
            Folder::create($logPath);
        }

        return rtrim($logPath, '/\\');
    }

    private static function rotateIfNeeded(): void
    {
        $directory = self::resolveLogDirectory();
        $activeLog = $directory . '/' . self::LOG_FILE;

        if (!is_file($activeLog)) {
            return;
        }

        $fileTimestamp = @filemtime($activeLog);
        if ($fileTimestamp === false) {
            return;
        }

        $timezone = new \DateTimeZone((string) date_default_timezone_get());
        $today = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $fileDate = (new \DateTimeImmutable('@' . $fileTimestamp))->setTimezone($timezone)->format('Y-m-d');

        if ($fileDate === $today) {
            return;
        }

        $archiveBaseName = 'com_contentbuilderng-' . $fileDate . '.log';
        $archivePath = $directory . '/' . $archiveBaseName;
        $archiveIndex = 1;

        while (is_file($archivePath)) {
            $archivePath = $directory . '/com_contentbuilderng-' . $fileDate . '-' . $archiveIndex . '.log';
            $archiveIndex++;
        }

        @rename($activeLog, $archivePath);
        self::cleanupRotatedLogs($directory);
    }

    private static function cleanupRotatedLogs(string $directory): void
    {
        $rotatedFiles = glob($directory . '/com_contentbuilderng-*.log') ?: [];

        if (count($rotatedFiles) <= self::MAX_ROTATED_FILES) {
            return;
        }

        usort(
            $rotatedFiles,
            static fn(string $left, string $right): int => (@filemtime($right) ?: 0) <=> (@filemtime($left) ?: 0)
        );

        foreach (array_slice($rotatedFiles, self::MAX_ROTATED_FILES) as $staleFile) {
            @unlink($staleFile);
        }
    }

    private static function category(): string
    {
        $app = Factory::getApplication();

        return $app->isClient('administrator') ? 'cb.admin' : 'cb.site';
    }

    private static function callerAt(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        for ($i = 0; $i < count($trace); $i++) {
            $frame = $trace[$i];

            // On trouve la frame Logger::<level>()
            if (($frame['class'] ?? null) !== self::class) {
                continue;
            }

            $fn = $frame['function'] ?? '';
            if (!in_array($fn, ['debug', 'info', 'warning', 'error', 'exception'], true)) {
                continue;
            }

            // file/line du point d'appel (là où Logger::info(...) est écrit)
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            // La frame suivante est l'appelant réel (StorageModel::store)
            $caller = $trace[$i + 1] ?? [];

            $callerClass = $caller['class'] ?? null;
            $callerFunc  = $caller['function'] ?? null;

            $shortClass = $callerClass
                ? substr($callerClass, strrpos($callerClass, '\\') + 1)
                : null;

            $fileBase = $file ? pathinfo($file, PATHINFO_FILENAME) : null;

            // Exemple voulu: StorageModel::store (StorageModel:411)
            $where = $shortClass ?? $fileBase ?? 'unknown';
            $what  = $callerFunc ? ($where . '::' . $callerFunc) : $where;

            if ($fileBase && $line) {
                return $what . ' (' . $fileBase . ':' . (int) $line . ')';
            }

            return $what;
        }

        return null;
    }



    /**
     * Contexte JSON = uniquement ce que tu veux “métier”
     * (pas at, pas priorité, pas date, etc.)
     */
    private static function baseContext(): array
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();

        return [
            'client' => $app->isClient('administrator') ? 'admin' : 'site',
            'view'   => $input->getCmd('view', ''),
            'task'   => $input->getCmd('task', ''),
            'userId' => (int) Factory::getApplication()->getIdentity()->id
        ];
    }

    private static function format(string $message, array $context = []): string
    {
        $at = self::callerAt();
        if ($at) {
            $message = "$at\t$message";
        }

        $merged = self::baseContext();

        foreach ($context as $k => $v) {
            $merged[$k] = $v;
        }

        // Si tu veux : ne pas afficher de JSON si le contexte est vide
        if (!$merged) {
            return $message;
        }

        $json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json ? ($message . ' | ' . $json) : $message;
    }

    /** Debug seulement si debug Joomla activé */
    public static function debug(string $message, array $context = []): void
    {
        if (!Factory::getApplication()->get('debug')) {
            return;
        }

        self::register();
        self::write(self::format($message, $context), Log::DEBUG, self::category());
    }

    public static function info(string $message, array $context = []): void
    {
        self::register();
        self::write(self::format($message, $context), Log::INFO, self::category());
    }

    public static function warning(string $message, array $context = []): void
    {
        self::register();
        self::write(self::format($message, $context), Log::WARNING, self::category());
    }

    public static function error(string $message, array $context = []): void
    {
        self::register();
        self::write(self::format($message, $context), Log::ERROR, self::category());
    }

    public static function exception(\Throwable $e, array $context = []): void
    {
        $context += [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ];

        self::error('Exception', $context);
    }

    private static function write(string $message, int $priority, string $category): void
    {
        $directory = self::resolveLogDirectory();
        $file = $directory . '/' . self::LOG_FILE;
        $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
        $priorityName = self::priorityToString($priority);
        $line = sprintf(
            "%s %s %s\t%s\t%s\n",
            $now->format('Y-m-d'),
            $now->format('H:i:s'),
            $priorityName,
            $category,
            $message
        );

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function priorityToString(int $priority): string
    {
        return match ($priority) {
            Log::EMERGENCY => 'EMERGENCY',
            Log::ALERT => 'ALERT',
            Log::CRITICAL => 'CRITICAL',
            Log::ERROR => 'ERROR',
            Log::WARNING => 'WARNING',
            Log::NOTICE => 'NOTICE',
            Log::INFO => 'INFO',
            Log::DEBUG => 'DEBUG',
            default => 'INFO',
        };
    }
}
