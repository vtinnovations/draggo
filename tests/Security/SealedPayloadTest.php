<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Security;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Tests\Support\RecordFactory;

/**
 * The canonicalisation and signature primitives.
 *
 * These are the rules both sides of the protocol must agree on byte for byte,
 * so they are pinned against literal expected strings rather than against a
 * second implementation.
 */
final class SealedPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        if (!SealedPayload::cryptoAvailable()) {
            $this->markTestSkipped('ext-sodium is not available in this environment.');
        }
    }

    // ── vt-one/canonical-json-v1 ────────────────────────────────────

    public function testCanonicalFormSortsObjectKeysAndDropsSignature(): void
    {
        $doc = SealedPayload::decodeDocument('{"b":1,"signature":"xx","a":2}');

        self::assertSame('{"a":2,"b":1}', SealedPayload::canonicalJson($doc));
    }

    public function testCanonicalFormSortsNestedKeysButPreservesListOrder(): void
    {
        $doc = SealedPayload::decodeDocument('{"o":{"z":1,"a":2},"l":["z","a","m"]}');

        self::assertSame('{"l":["z","a","m"],"o":{"a":2,"z":1}}', SealedPayload::canonicalJson($doc));
    }

    public function testCanonicalFormDropsOnlyTheTopLevelSignature(): void
    {
        $doc = SealedPayload::decodeDocument('{"signature":"top","n":{"signature":"kept"}}');

        self::assertSame('{"n":{"signature":"kept"}}', SealedPayload::canonicalJson($doc));
    }

    public function testCanonicalFormLeavesSlashesAndUnicodeUnescaped(): void
    {
        $doc = SealedPayload::decodeDocument('{"u":"https://v-t.one/ä"}');

        self::assertSame('{"u":"https://v-t.one/ä"}', SealedPayload::canonicalJson($doc));
    }

    public function testCanonicalFormPreservesScalarTypes(): void
    {
        $doc = SealedPayload::decodeDocument('{"f":false,"n":null,"i":0,"s":"0"}');

        self::assertSame('{"f":false,"i":0,"n":null,"s":"0"}', SealedPayload::canonicalJson($doc));
    }

    public function testCanonicalFormRejectsFloatsBecauseTheyDoNotSerialisePortably(): void
    {
        $doc = SealedPayload::decodeDocument('{"x":1.5}');

        self::assertNull(SealedPayload::canonicalJson($doc));
    }

    public function testEmptyObjectAndEmptyListStayDistinct(): void
    {
        self::assertSame('{"a":{},"b":[]}', SealedPayload::canonicalJson(
            (object) ['a' => new \stdClass(), 'b' => []],
        ));
    }

    public function testDecodeRejectsNonObjectPayloads(): void
    {
        self::assertNull(SealedPayload::decodeDocument('[1,2]'));
        self::assertNull(SealedPayload::decodeDocument('not json'));
    }

    // ── vt-one/request-sig-v1 ───────────────────────────────────────

    public function testRequestMessageIsSixNewlineJoinedLines(): void
    {
        $message = SealedPayload::requestMessage('post', '/rest/api/v1/draggo-license-updater', 'req-1', 1784880547, 'nonce-1', '{}');

        self::assertSame(
            "POST\n/rest/api/v1/draggo-license-updater\nreq-1\n1784880547\nnonce-1\n" . hash('sha256', '{}'),
            $message,
        );
    }

    public function testRequestMessageDoesNotIncludeTheKeyIdHeader(): void
    {
        $message = SealedPayload::requestMessage('POST', '/p', 'r', 1, 'n', '');

        self::assertCount(6, explode("\n", $message));
        self::assertStringNotContainsString('vtone-2026a', $message);
    }

    public function testRequestMessageHashesRawBytesNotReEncodedJson(): void
    {
        $a = SealedPayload::requestMessage('POST', '/p', 'r', 1, 'n', '{"a":1, "b":2}');
        $b = SealedPayload::requestMessage('POST', '/p', 'r', 1, 'n', '{"a":1,"b":2}');

        self::assertNotSame($a, $b, 'Whitespace changes the body hash, as it must.');
    }

    // ── strict Base64 ───────────────────────────────────────────────

    public function testStrictBase64AcceptsValidEncodingsAndRejectsAnInvalidAlphabet(): void
    {
        self::assertSame('hi', SealedPayload::strictBase64('aGk='));

        // PHP's base64_decode(..., true) strips whitespace even in strict
        // mode — that is documented PHP behaviour, not a canonicalisation
        // hole: the decoded BYTES are what get hashed and signature-checked
        // downstream, and whitespace-tolerant decoding cannot change what
        // those bytes are.
        self::assertSame('hi', SealedPayload::strictBase64('aGk = '));

        // What "strict" actually buys us: characters outside the Base64
        // alphabet are rejected outright rather than silently discarded.
        self::assertNull(SealedPayload::strictBase64('****'));
    }

    // ── exact-byte digest ───────────────────────────────────────────

    public function testDigestMatchesOnlyTheExactBytes(): void
    {
        $bytes = '{"a":1}';

        self::assertTrue(SealedPayload::matchesDigest($bytes, md5($bytes)));
        self::assertTrue(SealedPayload::matchesDigest($bytes, strtoupper(md5($bytes))));
        self::assertFalse(SealedPayload::matchesDigest($bytes . ' ', md5($bytes)));
        self::assertFalse(SealedPayload::matchesDigest('{"a": 1}', md5($bytes)));
    }

    // ── signatures ──────────────────────────────────────────────────

    public function testValidDetachedSignatureVerifies(): void
    {
        $factory = new RecordFactory();

        self::assertTrue(SealedPayload::verify($factory->sign('hello'), 'hello', $factory->publicKey));
    }

    public function testSignatureFailsForMutatedMessage(): void
    {
        $factory = new RecordFactory();

        self::assertFalse(SealedPayload::verify($factory->sign('hello'), 'hellO', $factory->publicKey));
    }

    public function testSignatureFailsForAnotherKey(): void
    {
        $signer = new RecordFactory();
        $other = new RecordFactory();

        self::assertFalse(SealedPayload::verify($signer->sign('hello'), 'hello', $other->publicKey));
    }

    public function testSignatureFailsForMalformedInputs(): void
    {
        $factory = new RecordFactory();

        self::assertFalse(SealedPayload::verify('not base64!', 'hello', $factory->publicKey));
        self::assertFalse(SealedPayload::verify(base64_encode('short'), 'hello', $factory->publicKey));
        self::assertFalse(SealedPayload::verify($factory->sign('hello'), 'hello', 'wrong-length-key'));
    }
}
