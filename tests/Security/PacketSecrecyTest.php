<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Packet-log secrecy.
 *
 * The integration specification forbids licence keys, payloads, digests,
 * signatures, nonces and whole packets from ordinary logs and browser
 * responses. Redacting the key out of a packet dump is not enough — the rest of
 * the packet is still sensitive — so this asserts the forbidden values never
 * reach a logger context in the first place.
 *
 * A static scan, deliberately: it fails when someone ADDS such a call, which is
 * when it matters, rather than only when a particular runtime path is executed.
 */
final class PacketSecrecyTest extends TestCase
{
    /** Context keys that must never appear in a logger call. */
    private const FORBIDDEN_CONTEXT = [
        'request_packet', 'response_packet', 'request_body', 'response_body',
        'nonce', 'license_payload_b64', 'license_md5', 'signature',
        'request_sha256', 'response_sha256', 'licence_key_sha256',
        'license_key_sha256', 'licence_key_length', 'license_key_length',
        'license_key', 'licence_key', 'payload', 'body',
    ];

    /** Only these may be logged about an exchange. */
    private const ALLOWED_CONTEXT = [
        'operation', 'result', 'request_id', 'http_status',
        'elapsed_ms', 'applied_version', 'key_id', 'domain',
    ];

    /**
     * @return list<string>
     */
    private static function sourceFiles(): array
    {
        $out = [];
        $root = \dirname(__DIR__, 2) . '/src';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    public function testNoLoggerCallCarriesAForbiddenContextKey(): void
    {
        foreach (self::sourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            // Each ->info(...)/->warning(...)/->error(...) call on a logger.
            preg_match_all(
                '/logger->(?:emergency|alert|critical|error|warning|notice|info|debug|log)\((.*?)\);/s',
                $source,
                $matches,
            );

            foreach ($matches[1] as $call) {
                foreach (self::FORBIDDEN_CONTEXT as $forbidden) {
                    self::assertStringNotContainsString(
                        "'" . $forbidden . "'",
                        $call,
                        sprintf('%s logs forbidden context key "%s".', $path, $forbidden),
                    );
                }
            }
        }
    }

    public function testExchangeLoggingUsesOnlyApprovedContextKeys(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Agent/RegistryClient.php');

        preg_match_all('/logger->\w+\((.*?)\);/s', $source, $matches);
        self::assertNotEmpty($matches[1], 'RegistryClient is expected to log operational metadata.');

        foreach ($matches[1] as $call) {
            preg_match_all("/'([a-z_]+)'\s*=>/", $call, $keys);

            foreach ($keys[1] as $key) {
                self::assertContains($key, self::ALLOWED_CONTEXT, sprintf('Unexpected logged key "%s".', $key));
            }
        }
    }

    public function testTheLicenceKeyIsNeverInterpolatedIntoAMessageOrException(): void
    {
        foreach (self::sourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            foreach (['->key()'] as $accessor) {
                preg_match_all('/(?:sprintf|implode|json_encode)\([^;]*' . preg_quote($accessor, '/') . '/s', $source, $m);
                self::assertSame([], $m[0], sprintf('%s formats the licence key into a string.', $path));
            }
        }
    }

    public function testTheProfileKeyIsNotExposedThroughAnyJsonResponse(): void
    {
        foreach (self::sourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            if (!str_contains($source, 'JsonResponse')) {
                continue;
            }

            self::assertStringNotContainsString(
                '->key()',
                $source,
                sprintf('%s builds a JSON response and touches the licence key.', $path),
            );
        }
    }

    public function testTheSessionClaimStoresNoKeyOrDomain(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Agent/UsageSignal.php');

        // The claim must be written from the slug alone.
        self::assertMatchesRegularExpression(
            '/\$session->set\(self::CLAIM,\s*\$claimed\)/',
            $source,
        );
        self::assertStringNotContainsString('$session->set(self::CLAIM, $key', $source);
    }
}
