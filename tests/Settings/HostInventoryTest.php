<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Settings;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Vtinnovations\Draggo\Settings\HostInventory;

/**
 * Host normalisation must change how a name is written and never which host it
 * means. Every "widening" case below is a way a licence for one site could be
 * stretched to cover another, so each one is pinned as a rejection.
 */
final class HostInventoryTest extends TestCase
{
    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('representationOnly')]
    public function testRepresentationIsNormalisedWithoutChangingIdentity(string $input, ?string $expected): void
    {
        self::assertSame($expected, HostInventory::normalise($input));
    }

    public static function representationOnly(): array
    {
        return [
            'already canonical' => ['example.com', 'example.com'],
            'uppercase' => ['EXAMPLE.com', 'example.com'],
            'trailing root dot' => ['example.com.', 'example.com'],
            'explicit port' => ['example.com:8443', 'example.com'],
            'surrounding space' => ['  example.com  ', 'example.com'],
            'full url' => ['https://example.com/path', 'example.com'],
            'subdomain kept' => ['www.example.com', 'www.example.com'],
            'ipv4 literal' => ['192.0.2.10', '192.0.2.10'],
        ];
    }

    /**
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('malformed')]
    public function testMalformedOrUnsafeValuesAreRefused(mixed $input): void
    {
        self::assertNull(HostInventory::normalise($input));
    }

    public static function malformed(): array
    {
        return [
            'empty' => [''],
            'wildcard' => ['*.example.com'],
            'userinfo' => ['user@example.com'],
            'space inside' => ['exa mple.com'],
            'double dot' => ['example..com'],
            'leading dot' => ['.example.com'],
            'leading hyphen label' => ['-example.com'],
            'trailing hyphen label' => ['example-.com'],
            'not a string' => [123],
            'null' => [null],
            'over length' => [str_repeat('a.', 200) . 'com'],
        ];
    }

    public function testNormalisationNeverStripsWwwOrCollapsesToARegistrableDomain(): void
    {
        self::assertNotSame(
            HostInventory::normalise('example.com'),
            HostInventory::normalise('www.example.com'),
        );
        self::assertNotSame(
            HostInventory::normalise('example.com'),
            HostInventory::normalise('shop.example.com'),
        );
    }

    public function testConfiguredInventoryIsUniqueAndSorted(): void
    {
        $inventory = $this->inventory(['b.example.com', 'A.example.com', 'b.example.com.', 'a.example.com']);

        self::assertSame(['a.example.com', 'b.example.com'], $inventory->configured());
    }

    public function testInventoryFallsBackToTheTrustedRequestHostWhenNoRootDeclaresOne(): void
    {
        $inventory = $this->inventory([], 'fallback.example');

        self::assertSame(['fallback.example'], $inventory->configured());
    }

    public function testVerificationHostPrefersTheCurrentHostWhenItIsConfigured(): void
    {
        $inventory = $this->inventory(['other.example', 'current.example'], 'current.example');

        self::assertSame('current.example', $inventory->verificationHost());
    }

    public function testVerificationHostIsDeterministicWhenTheCurrentHostIsNotConfigured(): void
    {
        $inventory = $this->inventory(['b.example', 'a.example'], 'unrelated.example');

        self::assertSame('a.example', $inventory->verificationHost());
    }

    public function testMatchIsAnExactSetIntersection(): void
    {
        $inventory = $this->inventory(['shop.example.com']);

        self::assertSame('shop.example.com', $inventory->match(['a.com', 'shop.example.com']));
        self::assertNull($inventory->match(['example.com']), 'parent domain must not match');
        self::assertNull($inventory->match(['www.shop.example.com']), 'child domain must not match');
        self::assertNull($inventory->match([]));
    }

    /**
     * @param list<string> $roots
     */
    private function inventory(array $roots, string $currentHost = 'example.com'): HostInventory
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($roots);

        $stack = new RequestStack();
        $stack->push(Request::create('https://' . $currentHost . '/'));

        return new HostInventory($connection, $stack);
    }
}
