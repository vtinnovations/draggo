<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Structural hardening.
 *
 * Obfuscation cannot make shipped PHP unreadable, and pretending otherwise
 * would be dishonest. What this enforces is narrower and achievable: there is
 * no single folder, namespace, class or service registration that hands
 * someone the whole activation subsystem, and no single switch whose removal
 * unlocks the product.
 *
 * These assertions are cheap to satisfy on purpose. They exist so a future
 * refactor that quietly recreates `src/Licensing/` fails the build.
 */
final class SourceLayoutTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../src';

    /**
     * @return list<string>
     */
    private static function files(): array
    {
        $out = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT)) as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $out[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        return $out;
    }

    public function testNoDirectoryAdvertisesTheSubsystem(): void
    {
        foreach (self::files() as $path) {
            self::assertDoesNotMatchRegularExpression(
                '#/(Licensing|License|Licence|Protection|Integrity|AntiTamper|DRM|VtOne|VTone)/#',
                $path,
                sprintf('%s lives in a directory that announces the subsystem.', $path),
            );
        }
    }

    public function testNoClassUsesARevealingName(): void
    {
        foreach (self::files() as $path) {
            $name = basename($path, '.php');

            self::assertDoesNotMatchRegularExpression(
                '/^(License|Licence)(Manager|Validator|Service|Repository|Guard|UpdaterController|Verifier|Paths)$/',
                $name,
                sprintf('%s uses a predictable cross-product class name.', $path),
            );
            self::assertNotSame('TamperDetector', $name);
            self::assertNotSame('ChecksumGuard', $name);
            self::assertNotSame('ExpectedMd5', $name);
        }
    }

    /** The eight sensitive responsibilities must not collapse into one file. */
    #[\PHPUnit\Framework\Attributes\DataProvider('responsibilities')]
    public function testEachResponsibilityLivesInItsOwnFile(string $needle, string $expectedFile): void
    {
        $owners = [];

        foreach (self::files() as $path) {
            if (str_contains((string) file_get_contents($path), $needle)) {
                $owners[] = basename($path);
            }
        }

        self::assertContains($expectedFile, $owners, sprintf('"%s" is not where it was expected.', $needle));
    }

    public static function responsibilities(): array
    {
        return [
            'fixed endpoints' => ['https://', 'RegistryEndpoints.php'],
            'key ring' => ['fingerprint', 'TrustAnchors.php'],
            'exact-byte digest' => ['matchesDigest', 'SealedPayload.php'],
            'updater authentication' => ['requestMessage', 'InboundExchange.php'],
            'domain policy' => ['normalise', 'HostInventory.php'],
            'persistence' => ['rename', 'ActivationStore.php'],
            'replay' => ['nonceUsed', 'ExchangeJournal.php'],
            'entitlement' => ['PRO_CAPABILITIES', 'EditionProfile.php'],
        ];
    }

    public function testSensitiveResponsibilitiesAreSpreadAcrossAtLeastFourSeams(): void
    {
        $seams = [];

        foreach (self::responsibilities() as [, $file]) {
            foreach (self::files() as $path) {
                if (basename($path) === $file) {
                    $seams[basename(\dirname($path))] = true;
                }
            }
        }

        self::assertGreaterThanOrEqual(
            4,
            \count($seams),
            'The eight sensitive responsibilities are too concentrated: ' . implode(', ', array_keys($seams)),
        );
    }

    public function testNoSingleDirectoryOwnsMostOfTheSensitiveFlow(): void
    {
        $perSeam = [];

        foreach (self::responsibilities() as [, $file]) {
            foreach (self::files() as $path) {
                if (basename($path) === $file) {
                    $seam = basename(\dirname($path));
                    $perSeam[$seam] = ($perSeam[$seam] ?? 0) + 1;
                }
            }
        }

        foreach ($perSeam as $seam => $count) {
            self::assertLessThanOrEqual(
                3,
                $count,
                sprintf('src/%s holds %d of the 8 sensitive responsibilities.', $seam, $count),
            );
        }
    }

    public function testTheWholeImplementationTouchesManyArchitecturalSeams(): void
    {
        $seams = [];

        foreach (self::files() as $path) {
            $source = (string) file_get_contents($path);

            if (preg_match('/(EditionResolver|EditionProfile|ActivationStore|TrustAnchors|SealedPayload|RegistryClient|RegistryEndpoints|UsageSignal|ExchangeJournal|InboundExchange|HostInventory)/', $source)) {
                $seams[trim(str_replace(realpath(self::ROOT), '', \dirname(realpath($path))), '/')] = true;
            }
        }

        self::assertGreaterThanOrEqual(
            6,
            \count($seams),
            'Activation logic should reach through the bundle, not sit in a removable corner: ' . implode(', ', array_keys($seams)),
        );
    }

    public function testNoSingleFileHoldsTheWholeFlow(): void
    {
        // Method DEFINITIONS unique to each responsibility, not generic PHP
        // idioms like rename() or prose like "fingerprint" — those are reused
        // independently by unrelated code (e.g. every atomic writer calls
        // rename()) and would falsely flag legitimate reuse. A file merely
        // CALLING into another seam's method (orchestration, e.g.
        // EditionResolver composing SealedPayload + TrustAnchors) is fine;
        // what would be a red flag is one file DEFINING several of these.
        $markers = [
            'function matchesDigest(',
            'function requestMessage(',
            'function resolve(',
            'function commit(',
            'function nonceUsed(',
        ];

        foreach (self::files() as $path) {
            $source = (string) file_get_contents($path);
            $hits = 0;

            foreach ($markers as $marker) {
                if (str_contains($source, $marker)) {
                    ++$hits;
                }
            }

            self::assertLessThan(3, $hits, sprintf('%s defines too much of the flow itself.', $path));
        }
    }

    public function testEntitlementIsCheckedAtManyBoundariesNotOne(): void
    {
        $boundaries = 0;

        foreach (self::files() as $path) {
            $source = (string) file_get_contents($path);

            if (preg_match('/->(allows|allowsElement|allowsStructure|assertFeature|assertElementAllowed)\(/', $source)) {
                ++$boundaries;
            }
        }

        self::assertGreaterThanOrEqual(
            8,
            $boundaries,
            'Entitlement must be enforced near each protected operation, not behind one gate.',
        );
    }

    public function testThereIsNoDevelopmentBypassSwitch(): void
    {
        foreach (self::files() as $path) {
            $source = (string) file_get_contents($path);

            self::assertStringNotContainsString('DRAGGO_LICENSE_BYPASS', $source);
            self::assertDoesNotMatchRegularExpression(
                '/getenv\(\s*[\'"][A-Z_]*(LICENSE|LICENCE|BYPASS)[A-Z_]*[\'"]\s*\)/',
                $source,
                sprintf('%s reintroduces an environment bypass.', $path),
            );
        }
    }

    public function testGatesFailClosedRatherThanTreatingAMissingServiceAsPermission(): void
    {
        foreach (self::files() as $path) {
            $source = (string) file_get_contents($path);

            // The old shape: "no gate injected => allowed".
            self::assertDoesNotMatchRegularExpression(
                '/\$this->\w*[eE]dition\w*\s*===?\s*null\s*\|\|/',
                $source,
                sprintf('%s treats a missing entitlement service as permission.', $path),
            );
            self::assertDoesNotMatchRegularExpression(
                '/\?->\w*allows\w*\([^)]*\)\s*\?\?\s*true/',
                $source,
                sprintf('%s defaults to allowed when the profile is absent.', $path),
            );
        }
    }
}
