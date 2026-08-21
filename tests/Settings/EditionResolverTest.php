<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Settings;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Vtinnovations\Draggo\Settings\HostInventory;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Security\TrustAnchors;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionProfile;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Tests\Support\RecordFactory;
use Vtinnovations\Draggo\Tests\Support\TempDir;

/**
 * End-to-end evaluation: stored bytes in, entitlement out.
 *
 * Covers the Pro-only model, the exact-host rules and every way a record can
 * fail to authenticate. The recurring assertion is that anything short of a
 * fully verified, in-date, correctly scoped Pro record leaves the installation
 * INACTIVE — there is no state in between.
 */
final class EditionResolverTest extends TestCase
{
    private string $projectDir;
    private RecordFactory $factory;

    protected function setUp(): void
    {
        if (!SealedPayload::cryptoAvailable()) {
            $this->markTestSkipped('ext-sodium is not available in this environment.');
        }

        $this->projectDir = sys_get_temp_dir() . '/draggo-test-' . bin2hex(random_bytes(6));
        $this->factory = new RecordFactory();
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->projectDir);
    }

    /**
     * @param list<string> $configuredHosts
     */
    private function resolver(array $configuredHosts = ['example.com'], ?TrustAnchors $anchors = null): array
    {
        $store = new ActivationStore($this->projectDir);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($configuredHosts);

        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/contao'));

        $hosts = new HostInventory($connection, $stack);
        $resolver = new EditionResolver($store, $anchors ?? $this->factory->anchors(), $hosts);

        return [$resolver, $store];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function activate(ActivationStore $store, array $overrides = []): void
    {
        $pair = $this->factory->record($overrides);
        self::assertTrue($store->commit($pair['bytes'], $pair['seal']));
    }

    // ── the happy path ──────────────────────────────────────────────

    public function testValidProRecordEntitlesTheInstallation(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store);

        $profile = $resolver->profile();

        self::assertTrue($profile->active);
        self::assertSame(EditionProfile::REASON_ACTIVE, $profile->reason);
        self::assertSame('pro', $profile->package);
        self::assertSame('example.com', $profile->matchedDomain);
        self::assertSame('DRG-TEST-0001', $profile->key());

        foreach ([EditionProfile::CAP_EDITOR, EditionProfile::CAP_LIBRARY, EditionProfile::CAP_GLOBALS, EditionProfile::CAP_AI] as $cap) {
            self::assertTrue($profile->allows($cap), $cap);
        }
    }

    public function testNothingStoredMeansUnlicensed(): void
    {
        [$resolver] = $this->resolver();
        $profile = $resolver->profile();

        self::assertFalse($profile->active);
        self::assertSame(EditionProfile::REASON_NONE, $profile->reason);
        self::assertSame('', $profile->key(), 'No key may be exposed when nothing is activated.');
        self::assertFalse($profile->allows(EditionProfile::CAP_EDITOR));
    }

    // ── Pro-only model ──────────────────────────────────────────────

    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonProPackages')]
    public function testNonProPackagesGrantNothing(string $package): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['license_package' => $package]);

        $profile = $resolver->profile();

        self::assertFalse($profile->active, "Package '$package' must not entitle a Pro-only product.");
        self::assertSame(EditionProfile::REASON_PACKAGE, $profile->reason);
        self::assertFalse($profile->allows(EditionProfile::CAP_EDITOR));
    }

    public static function nonProPackages(): array
    {
        return [['free'], ['trial'], ['demo'], ['starter'], ['']];
    }

    public function testExpiredProGetsNoFallbackEvenWhenFreeIsOffered(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, [
            'license_expires_at' => time() - 60,
            'free_available' => true,
        ]);

        $profile = $resolver->profile();

        self::assertFalse($profile->active, 'Pro-only has no expired-Pro fallback.');
        self::assertSame(EditionProfile::REASON_EXPIRED, $profile->reason);
        self::assertFalse($profile->allows(EditionProfile::CAP_EDITOR));
    }

    public function testNotYetValidRecordIsInactive(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['license_starts_at' => time() + 86400 * 7]);

        self::assertSame(EditionProfile::REASON_PENDING, $resolver->profile()->reason);
    }

    public function testNonLifetimeRecordWithoutExpiryIsRejected(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['license_lifetime' => false, 'license_expires_at' => null]);

        self::assertSame(EditionProfile::REASON_MALFORMED, $resolver->profile()->reason);
    }

    public function testLifetimeRecordWithAnExpiryIsRejected(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['license_lifetime' => true, 'license_expires_at' => time() + 100]);

        self::assertSame(EditionProfile::REASON_MALFORMED, $resolver->profile()->reason);
    }

    public function testStatusOtherThanValidIsRefused(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['validation_status' => 'revoked']);

        self::assertFalse($resolver->profile()->active);
    }

    // ── tamper resistance ───────────────────────────────────────────

    public function testEditingTheStoredRecordBreaksTheDigest(): void
    {
        [$resolver, $store] = $this->resolver();
        $pair = $this->factory->record();

        // Attacker edits one byte and recomputes nothing.
        $tampered = str_replace('"license_package":"pro"', '"license_package":"PRO"', $pair['bytes']);
        self::assertNotSame($pair['bytes'], $tampered);

        $store->commit($tampered, $pair['seal']);

        self::assertSame(EditionProfile::REASON_UNTRUSTED, $resolver->profile()->reason);
    }

    public function testRecomputingTheDigestStillFailsBecauseTheEnvelopeIsSigned(): void
    {
        [$resolver, $store] = $this->resolver();
        $pair = $this->factory->record();

        $tampered = str_replace('"license_version":7', '"license_version":99', $pair['bytes']);
        $seal = $pair['seal'];
        // The attacker "fixes" the digest — but cannot re-sign the envelope.
        $seal['license_md5'] = md5($tampered);

        $store->commit($tampered, $seal);

        self::assertSame(EditionProfile::REASON_UNTRUSTED, $resolver->profile()->reason);
    }

    public function testRecordSignedByAnotherKeyIsRefused(): void
    {
        [$resolver, $store] = $this->resolver();
        $foreign = new RecordFactory($this->factory->keyId);
        $pair = $foreign->record();

        $store->commit($pair['bytes'], $pair['seal']);

        self::assertSame(EditionProfile::REASON_UNTRUSTED, $resolver->profile()->reason);
    }

    public function testEmptyKeyRingRefusesEverythingAndNeverFallsBack(): void
    {
        [$resolver, $store] = $this->resolver(['example.com'], new TrustAnchors([]));
        $pair = $this->factory->record();
        $store->commit($pair['bytes'], $pair['seal']);

        $profile = $resolver->profile();

        self::assertFalse($profile->active);
        self::assertSame(EditionProfile::REASON_UNTRUSTED, $profile->reason);
    }

    public function testRecordForAnotherProductIsRefused(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['project' => 'Guardian', 'project_slug' => 'guardian']);

        self::assertSame(EditionProfile::REASON_MALFORMED, $resolver->profile()->reason);
    }

    // ── exact-host binding ──────────────────────────────────────────

    public function testHostNotConfiguredHereMeansNoEntitlement(): void
    {
        [$resolver, $store] = $this->resolver(['someone-else.com']);
        $this->activate($store);

        self::assertSame(EditionProfile::REASON_DOMAIN, $resolver->profile()->reason);
    }

    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('neighbouringHosts')]
    public function testNeighbouringHostsAreDistinctIdentities(string $configured): void
    {
        [$resolver, $store] = $this->resolver([$configured]);
        $this->activate($store);

        self::assertSame(
            EditionProfile::REASON_DOMAIN,
            $resolver->profile()->reason,
            "'$configured' must not be covered by a licence for example.com.",
        );
    }

    public static function neighbouringHosts(): array
    {
        return [
            'www prefix' => ['www.example.com'],
            'subdomain' => ['shop.example.com'],
            'nested subdomain' => ['a.b.example.com'],
            'parent domain' => ['com'],
            'sibling' => ['example.org'],
            'suffix trap' => ['notexample.com'],
        ];
    }

    public function testAnyOneConfiguredHostMatchingIsEnough(): void
    {
        [$resolver, $store] = $this->resolver(['unrelated.test', 'staging.example.com']);
        $this->activate($store);

        $profile = $resolver->profile();

        self::assertTrue($profile->active);
        self::assertSame('staging.example.com', $profile->matchedDomain);
    }

    public function testSignedHostSetMustBeSortedUniqueAndWildcardFree(): void
    {
        self::assertNull(EditionResolver::hostSet(['b.com', 'a.com']), 'unsorted');
        self::assertNull(EditionResolver::hostSet(['a.com', 'a.com']), 'duplicate');
        self::assertNull(EditionResolver::hostSet(['*.example.com']), 'wildcard');
        self::assertNull(EditionResolver::hostSet(['Example.com']), 'not canonical');
        self::assertNull(EditionResolver::hostSet([]), 'empty');
        self::assertNull(EditionResolver::hostSet('a.com'), 'not a list');
        self::assertSame(['a.com', 'b.com'], EditionResolver::hostSet(['a.com', 'b.com']));
    }

    public function testOperationHostMustBeAMemberOfTheSignedSet(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, [
            'license_domain' => 'example.com',
            'license_domains' => ['other.com', 'staging.example.com'],
        ]);

        self::assertSame(EditionProfile::REASON_MALFORMED, $resolver->profile()->reason);
    }

    public function testAllowanceOf9999IsNotAWildcard(): void
    {
        [$resolver, $store] = $this->resolver(['unlisted.com']);
        $this->activate($store, ['license_max_domains' => 9999]);

        self::assertSame(
            EditionProfile::REASON_DOMAIN,
            $resolver->profile()->reason,
            '9999 reports an instance-bound allowance; it authorises no host outside the signed set.',
        );
    }

    public function testBoundCountAboveAllowanceIsStillAccepted(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, [
            'license_max_domains' => 1,
            'license_domains' => ['example.com', 'staging.example.com'],
        ]);

        self::assertTrue(
            $resolver->profile()->active,
            'Lowering an allowance must not take already-bound installations dark.',
        );
    }

    public function testNonPositiveAllowanceIsRejected(): void
    {
        [$resolver, $store] = $this->resolver();
        $this->activate($store, ['license_max_domains' => 0]);

        self::assertSame(EditionProfile::REASON_MALFORMED, $resolver->profile()->reason);
    }

    public function testLegacyRecordWithoutTheSignedHostSetRequiresARefreshRatherThanBeingSynthesised(): void
    {
        [$resolver, $store] = $this->resolver();
        $document = $this->factory->record()['bytes'];
        $decoded = json_decode($document, true);
        unset($decoded['license_domains'], $decoded['license_max_domains'], $decoded['signature']);

        $pair = $this->factory->seal($decoded);
        $store->commit($pair['bytes'], $pair['seal']);

        $profile = $resolver->profile();

        self::assertFalse($profile->active);
        self::assertSame([], $profile->signedDomains, 'The client must never invent the missing fields.');
    }
}
