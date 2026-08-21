<?php

declare(strict_types=1);

namespace Vtinnovations\Draggo\Tests\History;

use PHPUnit\Framework\TestCase;
use Vtinnovations\Draggo\History\ExchangeJournal;
use Vtinnovations\Draggo\Settings\ActivationStore;
use Vtinnovations\Draggo\Tests\Support\TempDir;

/**
 * Replay defence for the inbound updater.
 *
 * Also asserts the ledger's discretion: it must be able to recognise a replay
 * without ever holding the nonce or the body it is recognising.
 */
final class ExchangeJournalTest extends TestCase
{
    private string $projectDir;
    private ExchangeJournal $journal;
    private ActivationStore $store;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/draggo-journal-' . bin2hex(random_bytes(6));
        $this->store = new ActivationStore($this->projectDir);
        $this->journal = new ExchangeJournal($this->store);
    }

    protected function tearDown(): void
    {
        TempDir::remove($this->projectDir);
    }

    public function testUnseenRequestIsUnknown(): void
    {
        self::assertNull($this->journal->find('req-1'));
        self::assertFalse($this->journal->nonceUsed('nonce-1'));
    }

    public function testExactRetryIsRecognisedAsTheSameExchange(): void
    {
        $body = '{"request_id":"req-1"}';
        $this->journal->remember('req-1', 'nonce-1', $this->journal->fingerprint($body), 9);

        $seen = $this->journal->find('req-1');

        self::assertNotNull($seen);
        self::assertSame(9, $seen['version']);
        self::assertSame($this->journal->fingerprint($body), $seen['fingerprint']);
    }

    public function testSameIdWithDifferentContentHasADifferentFingerprint(): void
    {
        $this->journal->remember('req-1', 'nonce-1', $this->journal->fingerprint('{"v":1}'), 9);

        $seen = $this->journal->find('req-1');

        self::assertNotSame($this->journal->fingerprint('{"v":2}'), $seen['fingerprint']);
    }

    public function testSpentNonceIsRemembered(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'fp', 9);

        self::assertTrue($this->journal->nonceUsed('nonce-1'));
        self::assertFalse($this->journal->nonceUsed('nonce-2'));
    }

    public function testLedgerStoresNoNonceOrRequestIdInTheClear(): void
    {
        $this->journal->remember('req-secret-id', 'nonce-secret-value', 'fp', 9);

        $raw = (string) file_get_contents($this->store->directory() . '/journal.json');

        self::assertStringNotContainsString('req-secret-id', $raw);
        self::assertStringNotContainsString('nonce-secret-value', $raw);
    }

    public function testLedgerSurvivesReinstantiation(): void
    {
        $this->journal->remember('req-1', 'nonce-1', 'fp', 9);

        $fresh = new ExchangeJournal(new ActivationStore($this->projectDir));

        self::assertNotNull($fresh->find('req-1'));
    }
}
