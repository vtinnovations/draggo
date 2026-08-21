<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Agent;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Agent\RegistryEndpoints;

/**
 * The outbound destinations are compiled in and must not drift.
 *
 * They are assembled from fragments, so a typo would not be a compile error —
 * it would be a silent redirection of licence traffic. Hence exact assertions.
 */
final class RegistryEndpointsTest extends TestCase
{
    public function testVerifyEndpointIsExact(): void
    {
        self::assertSame('https://www.v-t.one/api/v1/verify', RegistryEndpoints::verify());
    }

    public function testSignalEndpointIsExact(): void
    {
        self::assertSame('https://www.v-t.one/rest/api/v1/log-envoke', RegistryEndpoints::signal());
    }

    public function testUpdaterPathMatchesTheProtocolSlug(): void
    {
        self::assertSame('/rest/api/v1/draggo-license-updater', RegistryEndpoints::updaterPath());
    }

    public function testEndpointsAreHttpsOnTheCanonicalHost(): void
    {
        foreach ([RegistryEndpoints::verify(), RegistryEndpoints::signal()] as $url) {
            self::assertStringStartsWith('https://', $url);
            self::assertSame('www.v-t.one', parse_url($url, PHP_URL_HOST));
            self::assertNull(parse_url($url, PHP_URL_PORT));
            self::assertNull(parse_url($url, PHP_URL_USER));
        }
    }

    public function testNoSourceFileContainsTheAssembledUrlAsALiteral(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $needle = 'www.v-t.one/api';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                self::assertStringNotContainsString(
                    $needle,
                    (string) file_get_contents($file->getPathname()),
                    $file->getPathname(),
                );
            }
        }
    }
}
