<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace {
    if (!\defined('_JEXEC')) {
        \define('_JEXEC', 1);
    }
}

namespace Joomla\CMS\Application {
    if (!\class_exists(CMSApplication::class, false)) {
        abstract class CMSApplication
        {
        }
    }
}

namespace Joomla\Database {
    if (!\interface_exists(DatabaseInterface::class, false)) {
        interface DatabaseInterface
        {
            public function getPrefix(): string;
            public function getTableColumns(string $table, bool $type = true): array;
            public function quoteName(string $name): string;
            public function setQuery(string $query): void;
            public function execute(): void;
        }
    }

    if (!\interface_exists(QueryInterface::class, false)) {
        interface QueryInterface
        {
        }
    }
}

namespace Joomla\Filesystem {
    if (!\class_exists(Folder::class, false)) {
        class Folder
        {
            public static function create(string $path): bool
            {
                return true;
            }
        }
    }
}

namespace CB\Component\Contentbuilderng\Administrator\Helper {
    if (!\class_exists(Logger::class, false)) {
        class Logger
        {
            public static function info(string $message, array $context = []): void {}
            public static function warning(string $message, array $context = []): void {}
            public static function error(string $message, array $context = []): void {}
        }
    }
}

namespace CB\Component\Contentbuilderng\Tests\Stubs {
    final class Input
    {
        /** @var array<string,mixed> */
        private array $values = [];

        public function getInt(string $key, int $default = 0): int
        {
            return isset($this->values[$key]) ? (int) $this->values[$key] : $default;
        }

        public function getBool(string $key, bool $default = false): bool
        {
            return isset($this->values[$key]) ? (bool) $this->values[$key] : $default;
        }

        public function getString(string $key, string $default = ''): string
        {
            return isset($this->values[$key]) ? (string) $this->values[$key] : $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->values[$key] = $value;
        }
    }

    final class Session
    {
        /** @var array<string,mixed> */
        private array $values = [];

        public function getId(): string
        {
            return 'unit-session';
        }

        public function get(string $key, mixed $default = null)
        {
            return $this->values[$key] ?? $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->values[$key] = $value;
        }

        public function remove(string $key): void
        {
            unset($this->values[$key]);
        }
    }

    final class Container
    {
        /** @var array<string,mixed> */
        private array $services = [];

        /**
         * @param array<string,mixed> $services
         */
        public function __construct(array $services = [])
        {
            $this->services = $services;
        }

        public function get(string $id): mixed
        {
            return $this->services[$id] ?? null;
        }

        public function set(string $id, mixed $service): void
        {
            $this->services[$id] = $service;
        }
    }

    final class Database
    {
        private string $query = '';

        public function setQuery(string $query): void
        {
            $this->query = $query;
        }

        /**
         * @return array<int,array{id:int,parent_id:int}>
         */
        public function loadAssocList(): array
        {
            if ($this->query === 'Select id, parent_id From #__usergroups') {
                return [
                    ['id' => 1, 'parent_id' => 0],
                    ['id' => 9, 'parent_id' => 1],
                ];
            }

            return [];
        }
    }

    final class Identity
    {
        public int $id = 42;
        public string $username = 'unit_user';
        public string $name = 'Unit User';

        public function get(string $key, $default = null)
        {
            return match ($key) {
                'id' => $this->id,
                'username' => $this->username,
                'name' => $this->name,
                default => $default,
            };
        }
    }

    class Application extends \Joomla\CMS\Application\CMSApplication
    {
        public Input $input;
        private Session $session;
        private Identity $identity;
        /** @var array<int,array{0:string,1:string}> */
        public array $messages = [];

        public function __construct()
        {
            $this->input = new Input();
            $this->session = new Session();
            $this->identity = new Identity();
        }

        public function getIdentity(): Identity
        {
            return $this->identity;
        }

        public function setIdentity(int $id, string $username = 'unit_user', string $name = 'Unit User'): void
        {
            $this->identity->id = $id;
            $this->identity->username = $username;
            $this->identity->name = $name;
        }

        public function getSession(): Session
        {
            return $this->session;
        }

        public function enqueueMessage($msg, $type = 'message'): void
        {
            $this->messages[] = [(string) $msg, (string) $type];
        }

        public function get($key, $default = null)
        {
            return $default;
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

namespace Joomla\CMS\Access {
    if (!\class_exists(Access::class, false)) {
        class Access
        {
            /** @var array<int,array<int,int>> */
            public static array $groupsByUser = [];

            public static function getGroupsByUser(int $userId, bool $recursive = true): array
            {
                return self::$groupsByUser[$userId] ?? [];
            }
        }
    }
}

namespace Joomla\CMS\Access\Exception {
    if (!\class_exists(NotAllowed::class, false)) {
        class NotAllowed extends \RuntimeException
        {
        }
    }
}

namespace Joomla\CMS\Language {
    if (!\class_exists(Text::class, false)) {
        class Text
        {
            public static function _(string $key): string
            {
                return $key;
            }
        }
    }
}

namespace Joomla\CMS {
    if (!\class_exists(Factory::class, false)) {
        class Factory
        {
            private static ?\CB\Component\Contentbuilderng\Tests\Stubs\Application $application = null;
            private static ?\CB\Component\Contentbuilderng\Tests\Stubs\Container $container = null;

            public static function getApplication(): \CB\Component\Contentbuilderng\Tests\Stubs\Application
            {
                if (self::$application === null) {
                    self::$application = new \CB\Component\Contentbuilderng\Tests\Stubs\Application();
                }

                return self::$application;
            }

            public static function setApplication(\CB\Component\Contentbuilderng\Tests\Stubs\Application $application): void
            {
                self::$application = $application;
            }

            public static function getContainer(): \CB\Component\Contentbuilderng\Tests\Stubs\Container
            {
                if (self::$container === null) {
                    self::$container = new \CB\Component\Contentbuilderng\Tests\Stubs\Container([
                        \Joomla\Database\DatabaseInterface::class => new \CB\Component\Contentbuilderng\Tests\Stubs\Database(),
                    ]);
                }

                return self::$container;
            }

            public static function setContainer(\CB\Component\Contentbuilderng\Tests\Stubs\Container $container): void
            {
                self::$container = $container;
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
    require_once \dirname(__DIR__) . '/src/Model/StorageModel.php';
    require_once \dirname(__DIR__) . '/src/Model/VerifyModel.php';
    require_once \dirname(__DIR__) . '/src/Service/ApiPermissionRequirementService.php';
    require_once \dirname(__DIR__) . '/src/Service/PermissionService.php';
    require_once \dirname(__DIR__) . '/src/Helper/FormDisplayColumnsHelper.php';
    require_once \dirname(__DIR__) . '/src/Service/ConfigExportService.php';
    require_once \dirname(__DIR__) . '/src/Service/ConfigImportService.php';
}
