<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Support;

use Vtinnovations\Draggo\Draggo;
use Vtinnovations\Draggo\Security\SealedPayload;
use Vtinnovations\Draggo\Security\TrustAnchors;

/**
 * Builds signed record/envelope pairs for tests.
 *
 * Signing uses a throwaway keypair generated per test run: the production
 * private key is not ours to have, and fabricating a vector for the real
 * `vtone-2026a` key would be inventing evidence. What these fixtures DO prove
 * is that the canonicalisation, digest and verification code paths agree with
 * themselves and reject every mutation — and they run through exactly the
 * production functions, not a test reimplementation.
 *
 * Interoperability with the real registry key is covered separately by the
 * fingerprint assertion in TrustAnchorsTest, and finally by the fixed vector
 * V&T must supply (see the completion report's external dependencies).
 */
final class RecordFactory
{
    public string $publicKey;
    private string $secretKey;

    public function __construct(public readonly string $keyId = 'test-key')
    {
        $pair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($pair);
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
    }

    /** A ring containing only this test key, built the production way. */
    public function anchors(?int $from = null, ?int $until = null): TrustAnchors
    {
        return new TrustAnchors([
            $this->keyId => [
                'algorithm' => 'ed25519',
                'material' => self::fragments(base64_encode($this->publicKey)),
                'fingerprint' => substr(hash('sha256', $this->publicKey), 0, 16),
                'purposes' => ['record', 'envelope', 'request'],
                'from' => $from ?? 0,
                'until' => $until,
            ],
        ]);
    }

    /**
     * A complete, valid Pro record. Overrides let a test change exactly one
     * field and watch the pipeline reject it.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{bytes: string, seal: array<string, mixed>}
     */
    public function record(array $overrides = []): array
    {
        $now = time();

        $document = [
            'schema_version' => Draggo::SCHEMA,
            'project' => Draggo::PROJECT,
            'project_slug' => Draggo::SLUG,
            'license_key' => 'DRG-TEST-0001',
            'license_domain' => 'example.com',
            'license_domains' => ['example.com', 'staging.example.com'],
            'license_max_domains' => 3,
            'license_package' => 'pro',
            'license_features' => [],
            'license_version' => 7,
            'license_issued_at' => $now - 86400,
            'license_starts_at' => $now - 86400,
            'license_expires_at' => $now + 86400 * 30,
            'license_lifetime' => false,
            'license_verified_at' => $now,
            'free_available' => false,
            'validation_status' => 'valid',
        ];

        foreach ($overrides as $k => $v) {
            if (null === $v && \array_key_exists($k, $document)) {
                $document[$k] = null;
            } else {
                $document[$k] = $v;
            }
        }

        return $this->seal($document);
    }

    /**
     * Sign a document and wrap it in an authenticated envelope.
     *
     * @param array<string, mixed> $document
     *
     * @return array{bytes: string, seal: array<string, mixed>}
     */
    public function seal(array $document): array
    {
        // Sign the canonical form (which excludes "signature"), then emit the
        // bytes that will actually be stored.
        $unsigned = SealedPayload::decodeDocument((string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $document['signature'] = $this->sign((string) SealedPayload::canonicalJson($unsigned));

        $bytes = (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['bytes' => $bytes, 'seal' => $this->envelope($bytes, (int) $document['license_version'])];
    }

    /**
     * @return array<string, mixed>
     */
    public function envelope(string $bytes, int $version, ?string $digest = null): array
    {
        $seal = [
            'project' => Draggo::PROJECT,
            'project_slug' => Draggo::SLUG,
            'license_version' => $version,
            'license_md5' => $digest ?? md5($bytes),
            'generated_at' => time(),
            'key_id' => $this->keyId,
            'signature_algorithm' => 'ed25519',
        ];

        $decoded = SealedPayload::decodeDocument((string) json_encode($seal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $seal['signature'] = $this->sign((string) SealedPayload::canonicalJson($decoded));

        return $seal;
    }

    public function sign(string $message): string
    {
        return base64_encode(sodium_crypto_sign_detached($message, $this->secretKey));
    }

    /**
     * Reverse-and-split a Base64 key the same way the shipped anchors are
     * stored, so the tests exercise the real reassembly path.
     *
     * @return list<string>
     */
    public static function fragments(string $b64): array
    {
        $size = (int) ceil(\strlen($b64) / 4);

        return array_map(
            static fn (string $chunk): string => strrev($chunk),
            str_split($b64, max(1, $size)),
        );
    }
}
