<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Security;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\DependencyInjection\DraggoExtension;
use Vtinnovations\Draggo\Security\TrustAnchors;
use Vtinnovations\Draggo\Tests\Support\RecordFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The pinned key ring.
 *
 * The most important test here is the first one: it proves the SHIPPED
 * fragments reassemble into the approved v-t.one public key and match its
 * published fingerprint. If a fragment is ever corrupted in an edit or a merge,
 * that test fails loudly instead of the ring quietly emptying itself and every
 * activation failing in production with an opaque error.
 */
final class TrustAnchorsTest extends TestCase
{
    /** Published by V&T for key vtone-2026a. */
    private const PRODUCTION_KEY_ID = 'vtone-2026a';
    private const PRODUCTION_FINGERPRINT = 'edcd614e70c59ce0';

    /**
     * @return array<string, array{material: list<string>, fingerprint: string, algorithm: string, purposes: list<string>, from: int, until: int|null}>
     */
    private function shippedAnchors(): array
    {
        $container = new ContainerBuilder();
        (new DraggoExtension())->load([], $container);

        /** @var array<string, array{material: list<string>, fingerprint: string, algorithm: string, purposes: list<string>, from: int, until: int|null}> $anchors */
        $anchors = $container->getParameter('draggo.registry.anchors');

        return $anchors;
    }

    public function testShippedRingIsNotEmptyAndCarriesTheApprovedKey(): void
    {
        $ring = new TrustAnchors($this->shippedAnchors());

        self::assertFalse($ring->isEmpty(), 'A production build must never ship an empty key ring.');
        self::assertSame([self::PRODUCTION_KEY_ID], $ring->keyIds());
    }

    public function testShippedFragmentsReassembleIntoTheApprovedPublicKey(): void
    {
        $ring = new TrustAnchors($this->shippedAnchors());
        $key = $ring->resolve(self::PRODUCTION_KEY_ID, TrustAnchors::PURPOSE_ENVELOPE);

        self::assertNotNull($key);
        self::assertSame(32, \strlen($key), 'Ed25519 raw public keys are 32 bytes.');
        self::assertStringStartsWith(self::PRODUCTION_FINGERPRINT, hash('sha256', $key));
    }

    public function testApprovedKeyIsUsableForAllThreeSignatureDomains(): void
    {
        $ring = new TrustAnchors($this->shippedAnchors());

        foreach ([TrustAnchors::PURPOSE_RECORD, TrustAnchors::PURPOSE_ENVELOPE, TrustAnchors::PURPOSE_REQUEST] as $purpose) {
            self::assertNotNull($ring->resolve(self::PRODUCTION_KEY_ID, $purpose), $purpose);
        }
    }

    public function testNoSingleFragmentContainsTheWholeKey(): void
    {
        $material = $this->shippedAnchors()[self::PRODUCTION_KEY_ID]['material'];
        $whole = 'qllgm+66FUVBFJ3O68ICFG8b37dR+9jMfr1+4/pSygE=';

        self::assertGreaterThan(1, \count($material));

        foreach ($material as $fragment) {
            self::assertStringNotContainsString($fragment, $whole, 'Fragments must not be plain substrings of the key.');
        }
    }

    // ── fail-closed behaviour ───────────────────────────────────────

    public function testEmptyRingReportsItselfEmpty(): void
    {
        self::assertTrue((new TrustAnchors([]))->isEmpty());
    }

    public function testPlaceholderAndMalformedMaterialIsRejected(): void
    {
        $cases = [
            'blank material' => [],
            'placeholder' => ['CHANGEME'],
            'not base64' => ['****'],
            'wrong length' => [strrev(base64_encode('too-short'))],
        ];

        foreach ($cases as $label => $material) {
            $ring = new TrustAnchors([
                'k' => ['algorithm' => 'ed25519', 'material' => $material, 'fingerprint' => 'aa', 'purposes' => ['record'], 'from' => 0, 'until' => null],
            ]);

            self::assertTrue($ring->isEmpty(), $label);
        }
    }

    public function testFingerprintMismatchDropsTheKey(): void
    {
        $factory = new RecordFactory();

        $ring = new TrustAnchors([
            'k' => [
                'algorithm' => 'ed25519',
                'material' => RecordFactory::fragments(base64_encode($factory->publicKey)),
                'fingerprint' => 'deadbeefdeadbeef',
                'purposes' => ['record'],
                'from' => 0,
                'until' => null,
            ],
        ]);

        self::assertTrue($ring->isEmpty(), 'A key whose fingerprint does not match must not be trusted.');
    }

    public function testUnsupportedAlgorithmIsRejected(): void
    {
        $factory = new RecordFactory();

        $ring = new TrustAnchors([
            'k' => [
                'algorithm' => 'rsa-sha256',
                'material' => RecordFactory::fragments(base64_encode($factory->publicKey)),
                'fingerprint' => substr(hash('sha256', $factory->publicKey), 0, 16),
                'purposes' => ['record'],
                'from' => 0,
                'until' => null,
            ],
        ]);

        self::assertTrue($ring->isEmpty());
    }

    public function testUnknownKeyIdDoesNotResolve(): void
    {
        $ring = (new RecordFactory('known'))->anchors();

        self::assertNull($ring->resolve('vtone-9999z', TrustAnchors::PURPOSE_ENVELOPE));
    }

    public function testPurposeIsEnforced(): void
    {
        $factory = new RecordFactory();

        $ring = new TrustAnchors([
            'k' => [
                'algorithm' => 'ed25519',
                'material' => RecordFactory::fragments(base64_encode($factory->publicKey)),
                'fingerprint' => substr(hash('sha256', $factory->publicKey), 0, 16),
                'purposes' => ['record'],
                'from' => 0,
                'until' => null,
            ],
        ]);

        self::assertNotNull($ring->resolve('k', TrustAnchors::PURPOSE_RECORD));
        self::assertNull($ring->resolve('k', TrustAnchors::PURPOSE_REQUEST));
    }

    public function testRotationWindowIsHonoured(): void
    {
        $now = time();
        $retired = (new RecordFactory('old'))->anchors(0, $now - 10);
        $future = (new RecordFactory('new'))->anchors($now + 3600, null);

        self::assertNull($retired->resolve('old', TrustAnchors::PURPOSE_RECORD), 'Retired key must not verify.');
        self::assertSame([], $retired->candidates(TrustAnchors::PURPOSE_RECORD));
        self::assertNull($future->resolve('new', TrustAnchors::PURPOSE_RECORD), 'Not-yet-active key must not verify.');
    }
}
