<?php

declare(strict_types=1);

namespace {
    if (!\defined('_JEXEC')) {
        \define('_JEXEC', 1);
    }
}

namespace CB\Component\Contentbuilderng\Tests\Stubs {
    final class Identity
    {
        public function get(string $key, $default = null)
        {
            return match ($key) {
                'id' => 42,
                'username' => 'unit_user',
                'name' => 'Unit User',
                default => $default,
            };
        }
    }

    final class Application
    {
        public function getIdentity(): Identity
        {
            return new Identity();
        }
    }

    final class Date
    {
        public function toSql(): string
        {
            return '2026-02-17 12:00:00';
        }

        public function format(string $format): string
        {
            return match ($format) {
                'H:i:s' => '12:00:00',
                'Y-m-d H:i:s' => '2026-02-17 12:00:00',
                default => '2026-02-17 12:00:00',
            };
        }
    }
}

namespace Joomla\CMS\Log {
    if (!\class_exists(Log::class, false)) {
        class Log
        {
            public const WARNING = 4;
            public static array $entries = [];

            public static function add($message, $priority = 0, $category = ''): void
            {
                self::$entries[] = [$message, $priority, $category];
            }
        }
    }
}

namespace Joomla\CMS {
    if (!\class_exists(Factory::class, false)) {
        class Factory
        {
            public static function getApplication(): \CB\Component\Contentbuilderng\Tests\Stubs\Application
            {
                return new \CB\Component\Contentbuilderng\Tests\Stubs\Application();
            }

            public static function getDate(): \CB\Component\Contentbuilderng\Tests\Stubs\Date
            {
                return new \CB\Component\Contentbuilderng\Tests\Stubs\Date();
            }
        }
    }
}

namespace Joomla\CMS\Uri {
    if (!\class_exists(Uri::class, false)) {
        class Uri
        {
            public static function isInternal(string $url): bool
            {
                $url = \trim($url);
                if ($url === '' || \str_contains($url, "\n") || \str_contains($url, "\r")) {
                    return false;
                }

                if (\preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
                    return false;
                }

                return \str_starts_with($url, 'index.php') || \str_starts_with($url, '/');
            }
        }
    }
}

namespace Joomla\CMS\MVC\Model {
    if (!\class_exists(BaseDatabaseModel::class, false)) {
        class BaseDatabaseModel
        {
        }
    }

    if (!\class_exists(AdminModel::class, false)) {
        class AdminModel extends BaseDatabaseModel
        {
            /** @var string|array<int,string>|null */
            protected $error = null;

            public function setError($error): void
            {
                $this->error = $error;
            }

            public function getError()
            {
                return $this->error;
            }
        }
    }
}

namespace {
    require_once \dirname(__DIR__) . '/src/Helper/ContentbuilderLegacyHelper.php';
    require_once \dirname(__DIR__) . '/src/Model/StorageModel.php';
    require_once \dirname(__DIR__) . '/src/Model/VerifyModel.php';
}
