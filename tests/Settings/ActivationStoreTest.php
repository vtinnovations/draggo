<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\Settings;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Tests\Support\TempDir;

/**
 * Atomicity of the activation record.
 *
 * The invariant under test: a reader never sees record bytes paired with an
 * envelope that does not belong to them, no matter where a write fails.
 */
final class ActivationStoreTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/draggo-store-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->projectDir);
    }

    private function store(): ActivationStore
    {
        return new ActivationStore($this->projectDir);
    }

    public function testReadsBackTheExactBytesItWasGiven(): void
    {
        $store = $this->store();
        // Deliberately irregular spacing: the digest covers these bytes, so
        // the store must not normalise them.
        $bytes = '{ "a" : 1,  "b":"ä/x" }';

        self::assertTrue($store->commit($bytes, ['license_md5' => md5($bytes)]));
        self::assertSame($bytes, $store->read()['bytes']);
    }

    public function testEmptyStoreReadsAsNull(): void
    {
        self::assertNull($this->store()->read());
    }

    public function testCommitIsRejectedWhenTheValidatorRefusesTheCandidate(): void
    {
        $store = $this->store();

        self::assertFalse($store->commit('{"a":1}', [], static fn (): bool => false));
        self::assertNull($store->read(), 'A refused candidate must not be stored.');
    }

    public function testFailedPostWriteValidationRollsBackToThePreviousRecord(): void
    {
        $store = $this->store();
        $good = '{"v":1}';
        self::assertTrue($store->commit($good, ['license_md5' => md5($good)]));

        // A validator that accepts the candidate but rejects it once live
        // simulates a corrupted activation.
        $calls = 0;
        $result = $store->commit('{"v":2}', ['license_md5' => md5('{"v":2}')], static function () use (&$calls): bool {
            ++$calls;

            return $calls < 3;
        });

        self::assertFalse($result);
        self::assertSame($good, $store->read()['bytes'], 'The previous valid record must survive.');
    }

    public function testFirstEverCommitThatFailsPostWriteLeavesNothingBehind(): void
    {
        $store = $this->store();
        $calls = 0;

        $store->commit('{"v":1}', [], static function () use (&$calls): bool {
            ++$calls;

            return $calls < 3;
        });

        self::assertNull($store->read(), 'A rolled-back first activation must not leave a half-written pair.');
    }

    public function testRemovingClearsBothHalves(): void
    {
        $store = $this->store();
        $store->commit('{"v":1}', ['license_md5' => md5('{"v":1}')]);

        self::assertTrue($store->clear());
        self::assertNull($store->read());
        self::assertFileDoesNotExist($store->directory() . '/record.json');
        self::assertFileDoesNotExist($store->directory() . '/record.seal');
    }

    public function testAnOrphanedRecordWithoutItsEnvelopeIsNotState(): void
    {
        $store = $this->store();
        $store->commit('{"v":1}', ['license_md5' => md5('{"v":1}')]);
        @unlink($store->directory() . '/record.seal');

        // A fresh instance, mirroring a new request: the deliberate
        // per-instance cache (read() is hit on nearly every render) must not
        // be mistaken for stale-state protection. What matters is that
        // nothing reads the orphan as valid once it is actually consulted
        // from disk.
        $fresh = new ActivationStore($this->projectDir);

        self::assertNull($fresh->read(), 'Bytes without an authenticated envelope must never count as state.');
    }

    public function testOversizedRecordsAreRefused(): void
    {
        $store = $this->store();

        self::assertFalse($store->commit(str_repeat('x', ActivationStore::MAX_RECORD_BYTES + 1), []));
        self::assertFalse($store->commit('', []));
    }

    public function testNestedTransactionsDoNotDeadlock(): void
    {
        $store = $this->store();

        $result = $store->transaction(static fn (): mixed => $store->transaction(static fn (): string => 'inner'));

        self::assertSame('inner', $result);
    }

    public function testStateLivesOutsideAnyPublicDirectory(): void
    {
        self::assertStringEndsWith('/var/draggo/state', $this->store()->directory());
    }
}
