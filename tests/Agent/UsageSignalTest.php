<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Agent;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Vtinnovations\Draggo\Agent\RegistryEndpoints;
use Vtinnovations\Draggo\Agent\UsageSignal;
use Vtinnovations\Draggo\Draggo;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Security\TrustAnchors;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Settings\HostInventory;
use Vtinnovations\Draggo\Tests\Support\RecordFactory;
use Vtinnovations\Draggo\Tests\Support\TempDir;

/**
 * The two usage signals.
 *
 * The module-entry event is the only place the full licence key leaves the
 * server, so the rules around it are strict: once per authenticated session,
 * only from an authenticated record, claimed before delivery so a timeout
 * cannot become a retry loop, and never reflected into the session marker.
 */
final class UsageSignalTest extends TestCase
{
    private string $projectDir;
    private RecordFactory $factory;
    private ActivationStore $store;
    private Session $session;

    /** @var list<array{url: string, payload: array<string, mixed>}> */
    private array $sent = [];

    protected function setUp(): void
    {
        if (!SealedPayload::cryptoAvailable()) {
            $this->markTestSkipped('ext-sodium is not available in this environment.');
        }

        $this->projectDir = sys_get_temp_dir() . '/draggo-signal-' . bin2hex(random_bytes(6));
        $this->factory = new RecordFactory();
        $this->store = new ActivationStore($this->projectDir);
        $this->sent = [];
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->projectDir);
    }

    private function signal(bool $activate = true, ?TrustAnchors $anchors = null): UsageSignal
    {
        if ($activate) {
            $pair = $this->factory->record();
            $this->store->commit($pair['bytes'], $pair['seal']);
        }

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['example.com']);

        $this->session = new Session(new MockArraySessionStorage());
        $this->session->start();

        $request = Request::create('https://example.com/contao');
        $request->setSession($this->session);

        $stack = new RequestStack();
        $stack->push($request);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(
            function (string $method, string $url, array $options = []): ResponseInterface {
                $this->sent[] = ['url' => $url, 'payload' => $options['json'] ?? []];

                return $this->createMock(ResponseInterface::class);
            },
        );

        $resolver = new EditionResolver($this->store, $anchors ?? $this->factory->anchors(), new HostInventory($connection, $stack));

        return new UsageSignal($client, $resolver, $stack);
    }

    // ── module-entry event ──────────────────────────────────────────

    public function testFirstModuleEntrySendsDomainAndKeyOnce(): void
    {
        $signal = $this->signal();
        $signal->moduleEntry();

        self::assertCount(1, $this->sent);
        self::assertSame(RegistryEndpoints::signal(), $this->sent[0]['url']);
        self::assertSame(['domain' => 'example.com', 'key' => 'DRG-TEST-0001'], $this->sent[0]['payload']);
    }

    public function testRepeatedEntriesInTheSameSessionSendNothingFurther(): void
    {
        $signal = $this->signal();

        $signal->moduleEntry();
        $signal->moduleEntry();
        $signal->moduleEntry();

        self::assertCount(1, $this->sent, 'Reloads and parallel tabs must not re-emit.');
    }

    public function testANewServiceInstanceOnTheSameSessionStillDoesNotReEmit(): void
    {
        $signal = $this->signal();
        $signal->moduleEntry();

        // Service recreation must not reset the claim — that is why the marker
        // lives in the session and not in a process static.
        $claimed = $this->session->get('_draggo_entry_claim');
        self::assertSame([Draggo::SLUG], $claimed);
    }

    public function testTheClaimMarkerCarriesNoKeyDomainOrDigest(): void
    {
        $signal = $this->signal();
        $signal->moduleEntry();

        $marker = json_encode($this->session->all());

        self::assertStringNotContainsString('DRG-TEST-0001', (string) $marker);
        self::assertStringNotContainsString('example.com', (string) $marker);
    }

    public function testUnlicensedInstallationSendsNoModuleEntryEvent(): void
    {
        $signal = $this->signal(false);
        $signal->moduleEntry();

        self::assertSame([], $this->sent, 'A key is never invented for an unlicensed install.');
    }

    public function testUnverifiableRecordSendsNoModuleEntryEvent(): void
    {
        $signal = $this->signal(true, new TrustAnchors([]));
        $signal->moduleEntry();

        self::assertSame([], $this->sent);
    }

    public function testTheClaimIsTakenBeforeDeliverySoAFailureIsNotRetried(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['example.com']);

        $pair = $this->factory->record();
        $this->store->commit($pair['bytes'], $pair['seal']);

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = Request::create('https://example.com/contao');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        $attempts = 0;
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(function () use (&$attempts): ResponseInterface {
            ++$attempts;

            throw new \RuntimeException('endpoint down');
        });

        $resolver = new EditionResolver($this->store, $this->factory->anchors(), new HostInventory($connection, $stack));
        $signal = new UsageSignal($client, $resolver, $stack);

        $signal->moduleEntry();
        $signal->moduleEntry();

        self::assertSame(1, $attempts, 'A transport failure must not be retried within the session.');
    }

    // ── per-invocation event ────────────────────────────────────────

    public function testInvocationSignalSendsProjectAndDomainOnlyAndNeverTheKey(): void
    {
        $signal = $this->signal();
        $signal->invocation();

        self::assertCount(1, $this->sent);
        self::assertSame(['project' => Draggo::PROJECT, 'domain' => 'example.com'], $this->sent[0]['payload']);
        self::assertArrayNotHasKey('key', $this->sent[0]['payload']);
    }

    public function testInvocationSignalIsSentAtMostOncePerInvocation(): void
    {
        $signal = $this->signal();

        $signal->invocation();
        $signal->invocation();

        self::assertCount(1, $this->sent);
    }

    public function testInvocationSignalIsSilentWhenUnlicensed(): void
    {
        $signal = $this->signal(false);
        $signal->invocation();

        self::assertSame([], $this->sent);
    }

    public function testTheTwoEventsAreNotMergedIntoOnePacket(): void
    {
        $signal = $this->signal();

        $signal->invocation();
        $signal->moduleEntry();

        self::assertCount(2, $this->sent);
        self::assertNotSame($this->sent[0]['payload'], $this->sent[1]['payload']);
        self::assertArrayNotHasKey('project', $this->sent[1]['payload']);
    }
}
