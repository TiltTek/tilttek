<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Database;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Interloper;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Table;

/**
 * The DBAL stores on SQLite, in a file in write-ahead logging: the other writer has a connection of its own.
 */
#[CoversClass(DbalTokenLedger::class)]
#[CoversClass(DbalTokenRules::class)]
#[CoversClass(LuckTokensTables::class)]
#[CoversClass(Rows::class)]
final class DbalStoresTest extends StoresTestCase
{
    private Interloper $interloper;

    private Connection $connection;

    private Connection $other;

    private string $path;

    #[Override]
    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'luck-tokens-');
        self::assertIsString($path);
        $this->path = $path;
        $this->interloper = new Interloper();
        $this->connection = Database::file($this->path, $this->interloper);
        $this->other = Database::open($this->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection->close();
        $this->other->close();

        foreach (['', '-wal', '-shm'] as $suffix) {
            if (is_file($this->path.$suffix)) {
                unlink($this->path.$suffix);
            }
        }
    }

    public function testAnInsertionThatMeetsAnEntryAtItsPositionIsAConflictAndKeepsNothing(): void
    {
        $ledger = $this->ledger();
        $table = Table::ref('table:1');
        $entry = new LedgerEntry($table, 1, 'op-1', EntryType::Granted, 'fate', Table::ref('member:ana'), 1, 1, Table::gm(), new DateTimeImmutable('2026-11-13 20:00 UTC'));
        // On the store's own connection, inside its transaction: the primary key refuses the second row of position 1.
        $this->interloper->arm(fn (): int|string => $this->connection->insert(LuckTokensTables::ENTRIES, ['table_ref' => 'table:1', 'position' => 1, 'operation_id' => 'op-other', 'entry_type' => 'granted', 'token_kind' => 'fate', 'holder' => 'member:bo', 'delta' => 1, 'balance' => 1, 'actor' => 'member:gm', 'written_at' => '2026-11-13T20:00:00.000000Z']));

        try {
            $ledger->append($table, 0, [$entry]);
            self::fail('Position 1 was taken within the transaction.');
        } catch (LedgerConflict $ledgerConflict) {
            self::assertSame('The ledger of the table table:1 ends at position 0; entries came after position 0.', $ledgerConflict->getMessage());
        }

        self::assertSame([], $ledger->entries($table), 'The transaction is rolled back, the other row with it.');
    }

    #[Override]
    protected function connection(): Connection
    {
        return $this->connection;
    }

    #[Override]
    protected function other(): Connection
    {
        return $this->other;
    }

    #[Override]
    protected function interloper(): Interloper
    {
        return $this->interloper;
    }
}
