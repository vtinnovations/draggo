<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\Draggo\Agent\RegistryEndpoints;
use Vtinnovations\Draggo\Controller\Api\ServiceHookController;
use Vtinnovations\Draggo\Draggo;
use Vtinnovations\Draggo\History\ExchangeJournal;
use Vtinnovations\Draggo\Security\InboundExchange;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Settings\EditionResolver;
use Vtinnovations\Draggo\Settings\HostInventory;
use Vtinnovations\Draggo\Tests\Support\RecordFactory;
use Vtinnovations\Draggo\Tests\Support\TempDir;

/**
 * The public updater endpoint.
 *
 * This route is reachable by anyone on the internet, so most of these tests are
 * about what it REFUSES. The signature is the only thing granting access: no
 * session, no CSRF token, and deliberately no trust in Origin, Referer or
 * source IP.
 */
final class ServiceHookControllerTest extends TestCase
{
    private string $projectDir;
    private RecordFactory $factory;
    private ActivationStore $store;
    private ServiceHookController $controller;

    protected function setUp(): void
    {
        if (!SealedPayload::cryptoAvailable()) {
            $this->markTestSkipped('ext-sodium is not available in this environment.');
        }

        $this->projectDir = sys_get_temp_dir() . '/draggo-hook-' . bin2hex(random_bytes(6));
        $this->factory = new RecordFactory();
        $this->store = new ActivationStore($this->projectDir);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['example.com']);

        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));

        $hosts = new HostInventory($connection, $stack);
        $anchors = $this->factory->anchors();
        $resolver = new EditionResolver($this->store, $anchors, $hosts);

        $this->controller = new ServiceHookController(
            new InboundExchange($anchors),
            $resolver,
            $this->store,
            new ExchangeJournal($this->store),
            $hosts,
            new NullLogger(),
        );
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->projectDir);
    }

    /**
     * Build a properly signed push. Overrides let a test break exactly one
     * thing.
     *
     * @param array<string, mixed> $bodyOverrides
     * @param array<string, string> $headerOverrides
     */
    private function push(array $bodyOverrides = [], array $headerOverrides = [], int $version = 9): Request
    {
        $pair = $this->factory->record(['license_version' => $version]);

        $body = array_merge([
            'action' => 'license_update',
            'project' => Draggo::PROJECT,
            'project_slug' => Draggo::SLUG,
            'product_id' => Draggo::PRODUCT_ID,
            'domain' => 'example.com',
            'request_id' => 'req-' . bin2hex(random_bytes(4)),
            'timestamp' => time(),
            'nonce' => 'nonce-' . bin2hex(random_bytes(4)),
            'license_payload_b64' => base64_encode($pair['bytes']),
            'integrity' => $pair['seal'],
        ], $bodyOverrides);

        $raw = (string) json_encode($body);
        $path = RegistryEndpoints::updaterPath();

        $headers = array_merge([
            'HTTP_X_VT_REQUEST_ID' => (string) $body['request_id'],
            'HTTP_X_VT_TIMESTAMP' => (string) $body['timestamp'],
            'HTTP_X_VT_NONCE' => (string) $body['nonce'],
            'HTTP_X_VT_KEY_ID' => $this->factory->keyId,
            'HTTP_X_VT_SIGNATURE' => $this->factory->sign(SealedPayload::requestMessage(
                'POST',
                $path,
                (string) $body['request_id'],
                (int) $body['timestamp'],
                (string) $body['nonce'],
                $raw,
            )),
            'CONTENT_TYPE' => 'application/json',
        ], $headerOverrides);

        return Request::create($path, 'POST', [], [], [], $headers, $raw);
    }

    // ── request shape ───────────────────────────────────────────────

    public function testGetIsMethodNotAllowedRatherThanNotFound(): void
    {
        $response = ($this->controller)(Request::create(RegistryEndpoints::updaterPath(), 'GET'));

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertSame('POST', $response->headers->get('Allow'));
    }

    public function testWrongMediaTypeIsRefused(): void
    {
        $request = $this->push();
        $request->headers->set('Content-Type', 'text/plain');

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, ($this->controller)($request)->getStatusCode());
    }

    public function testOversizedBodyIsRefusedBeforeParsing(): void
    {
        $request = Request::create(
            RegistryEndpoints::updaterPath(),
            'POST',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            str_repeat('x', 300000),
        );

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, ($this->controller)($request)->getStatusCode());
    }

    // ── authentication ──────────────────────────────────────────────

    public function testUnsignedRequestIsRefusedGenerically(): void
    {
        $response = ($this->controller)($this->push([], ['HTTP_X_VT_SIGNATURE' => '']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('', $response->getContent(), 'No verification detail may leak.');
    }

    public function testTamperedBodyBreaksTheRequestSignature(): void
    {
        $request = $this->push();
        $tampered = str_replace('"domain":"example.com"', '"domain":"evil.com"', (string) $request->getContent());

        $forged = Request::create(
            RegistryEndpoints::updaterPath(),
            'POST',
            [], [], [],
            array_merge($request->server->all(), ['CONTENT_TYPE' => 'application/json']),
            $tampered,
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, ($this->controller)($forged)->getStatusCode());
    }

    public function testStaleTimestampIsRefused(): void
    {
        $response = ($this->controller)($this->push(['timestamp' => time() - 4000]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testHeaderAndBodyMetadataMustAgree(): void
    {
        $response = ($this->controller)($this->push([], ['HTTP_X_VT_REQUEST_ID' => 'a-different-id']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testUnknownKeyIdIsRefused(): void
    {
        $response = ($this->controller)($this->push([], ['HTTP_X_VT_KEY_ID' => 'vtone-not-pinned']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testPushForAnotherProductIsRefused(): void
    {
        $response = ($this->controller)($this->push(['product_id' => 'vt-guardian']));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    // ── applying an update ──────────────────────────────────────────

    public function testValidPushIsAppliedAtomically(): void
    {
        $response = ($this->controller)($this->push());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame('updated', $payload['status']);
        self::assertSame(9, $payload['license_version']);
        self::assertNotNull($this->store->read());
    }

    public function testExactRetryIsIdempotent(): void
    {
        $request = $this->push();
        $first = ($this->controller)($request);

        // Rebuild an identical request: same id, same nonce, same bytes.
        $replay = Request::create(
            RegistryEndpoints::updaterPath(),
            'POST',
            [], [], [],
            array_merge($request->server->all(), ['CONTENT_TYPE' => 'application/json']),
            (string) $request->getContent(),
        );
        $second = ($this->controller)($replay);

        self::assertSame('updated', json_decode((string) $first->getContent(), true)['status']);
        self::assertSame(Response::HTTP_OK, $second->getStatusCode());
        self::assertSame('already_processed', json_decode((string) $second->getContent(), true)['status']);
    }

    public function testOlderVersionCannotRollTheInstallationBack(): void
    {
        ($this->controller)($this->push([], [], 9));
        $response = ($this->controller)($this->push([], [], 4));

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());

        $stored = json_decode($this->store->read()['bytes'], true);
        self::assertSame(9, $stored['license_version'], 'The newer record must survive.');
    }

    public function testPushForAnInstallationWithNoHostInCommonIsRefused(): void
    {
        // Perfectly valid record — for somebody else's site.
        $pair = $this->factory->record([
            'license_domain' => 'other.example',
            'license_domains' => ['other.example'],
            'license_version' => 11,
        ]);

        $response = ($this->controller)($this->push([
            'domain' => 'other.example',
            'license_payload_b64' => base64_encode($pair['bytes']),
            'integrity' => $pair['seal'],
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertNull($this->store->read(), 'Nothing may be stored for a host we do not own.');
    }

    public function testOneExactIntersectionIsEnoughEvenWhenTheOperationNamesAnotherHost(): void
    {
        // The push is performed against staging.example.com, but this
        // installation configures example.com — which is also in the signed
        // set. One exact intersection authorises the instance.
        $pair = $this->factory->record([
            'license_domain' => 'staging.example.com',
            'license_domains' => ['example.com', 'staging.example.com'],
            'license_version' => 12,
        ]);

        $response = ($this->controller)($this->push([
            'domain' => 'staging.example.com',
            'license_payload_b64' => base64_encode($pair['bytes']),
            'integrity' => $pair['seal'],
        ]));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('updated', json_decode((string) $response->getContent(), true)['status']);
    }

    public function testBodyDomainMustMatchTheSignedOperationHost(): void
    {
        $response = ($this->controller)($this->push(['domain' => 'staging.example.com']));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
