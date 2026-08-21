<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Support;

/**
 * Recursively removes a per-test temp directory tree created under
 * sys_get_temp_dir(), so repeated test runs don't leave state directories
 * behind.
 */
final class TempDir
{
    public static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? self::remove($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
