<?php

declare(strict_types=1);

namespace Doppar\Insight\Tests\Support;

use Phaseolies\DI\Container;

/**
 * A container that answers the path helpers.
 *
 * Since doppar/framework 4.9, base_path() and storage_path() always go through
 * the container, so a bare Container makes config() and the session fail.
 * This one points both at a throwaway directory under the system temp dir.
 */
class TestContainer extends Container
{
    /**
     * Get the throwaway application root used by the test suite.
     *
     * @return string
     */
    public static function root(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'doppar_insight_tests';

        if (!is_dir($root . '/storage/framework/cache')) {
            @mkdir($root . '/storage/framework/cache', 0777, true);
        }

        return $root;
    }

    /**
     * Get the base path of the application.
     *
     * @param string $path
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return self::join(self::root(), $path);
    }

    /**
     * Get the storage path of the application.
     *
     * @param string $path
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        return self::join(self::root() . DIRECTORY_SEPARATOR . 'storage', $path);
    }

    /**
     * Append a relative path to a base directory.
     *
     * @param string $base
     * @param string $path
     * @return string
     */
    private static function join(string $base, string $path): string
    {
        $path = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . $path;
    }
}
