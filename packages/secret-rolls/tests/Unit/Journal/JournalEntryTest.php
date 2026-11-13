<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit\Journal;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\Instant;
use Tilttek\SecretRolls\Journal\EntryKind;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(JournalEntry::class)]
final class JournalEntryTest extends TestCase
{
    public function testACommittedEntryHashesItsPublicFields(): void
    {
        $hidden = new Table()->rolls->roll('cave', '1d6', 'gm', at: Table::at());
        $entry = JournalEntry::committed($hidden, JournalEntry::GENESIS);

        self::assertSame(['scene' => 'cave', 'position' => 1, 'kind' => 'committed', 'roll' => $hidden->roll(), 'commitment' => $hidden->commitment()->hex, 'roller' => 'gm', 'opening' => null, 'at' => '2026-11-12T20:00:00.000000Z', 'previous' => JournalEntry::GENESIS, 'hash' => $entry->hash], $entry->toArray());
        self::assertSame(Encoding::hash(['tilttek-secret-rolls/1', 'cave', 1, 'committed', $hidden->roll(), $hidden->commitment()->hex, 'gm', null, '2026-11-12T20:00:00.000000Z', JournalEntry::GENESIS]), $entry->hash);
        self::assertTrue($entry->holds());
        self::assertEquals($entry, JournalEntry::fromArray($entry->toArray()));
    }

    public function testARevealedEntryCarriesTheOpening(): void
    {
        $hidden = new Table()->rolls->roll('cave', '1d6');
        $at = new DateTimeImmutable('2026-11-12 15:00:00.5', new DateTimeZone('America/Toronto'));
        $entry = JournalEntry::revealed($hidden->opening, 2, $at, str_repeat('a', 64));

        self::assertSame(EntryKind::Revealed, $entry->kind);
        self::assertSame($hidden->roll(), $entry->roll);
        self::assertSame($hidden->commitment()->hex, $entry->commitment);
        self::assertNull($entry->roller);
        self::assertSame($hidden->opening, $entry->opening);
        self::assertSame('2026-11-12T20:00:00.500000Z', Instant::write($entry->at));
        self::assertSame('UTC', $entry->at->getTimezone()->getName());
        self::assertTrue($entry->holds());
        $decoded = json_decode(json_encode($entry->toArray(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertEquals($entry, JournalEntry::fromArray($decoded));
    }

    public function testAClosingEntryHoldsNoRoll(): void
    {
        $entry = JournalEntry::closed('cave', 3, Table::at(), str_repeat('b', 64));

        self::assertSame(EntryKind::Closed, $entry->kind);
        self::assertNull($entry->roll);
        self::assertNull($entry->commitment);
        self::assertNull($entry->opening);
        self::assertSame(3, $entry->position);
        self::assertSame(str_repeat('b', 64), $entry->previous);
        self::assertEquals($entry, JournalEntry::fromArray($entry->toArray()));
    }

    public function testAnEntryChangedNoLongerHolds(): void
    {
        $data = JournalEntry::closed('cave', 3, Table::at(), JournalEntry::GENESIS)->toArray();
        $data['position'] = 4;

        self::assertFalse(JournalEntry::fromArray($data)->holds());
    }

    /**
     * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, string}>
     */
    public static function badEntries(): iterable
    {
        yield 'no kind' => [static fn (array $data): array => ['kind' => null] + $data, 'A journal entry holds'];
        yield 'an unknown kind' => [static fn (array $data): array => array_replace($data, ['kind' => 'rerolled']), 'A journal entry holds'];
        yield 'no scene' => [static fn (array $data): array => array_replace($data, ['scene' => 1]), 'A journal entry holds'];
        yield 'position 0' => [static fn (array $data): array => array_replace($data, ['position' => 0]), 'A journal entry holds'];
        yield 'a position in text' => [static fn (array $data): array => array_replace($data, ['position' => '1']), 'A journal entry holds'];
        yield 'no time' => [static fn (array $data): array => array_replace($data, ['at' => null]), 'A journal entry holds'];
        yield 'no previous hash' => [static fn (array $data): array => array_replace($data, ['previous' => null]), 'A journal entry holds'];
        yield 'no hash' => [static fn (array $data): array => array_replace($data, ['hash' => 1]), 'A journal entry holds'];
        yield 'a committed entry without commitment' => [static fn (array $data): array => array_replace($data, ['commitment' => null]), 'A committed entry does not hold these fields.'];
        yield 'a committed entry without roll' => [static fn (array $data): array => array_replace($data, ['roll' => null]), 'A committed entry'];
        yield 'a committed entry with a roller of numbers' => [static fn (array $data): array => array_replace($data, ['roller' => 1]), 'A committed entry'];
        yield 'a committed entry with an opening' => [static fn (array $data): array => array_replace($data, ['opening' => []]), 'A committed entry'];
        yield 'a revealed entry without opening' => [static fn (array $data): array => array_replace($data, ['kind' => 'revealed', 'roller' => null]), 'A revealed entry'];
        yield 'a revealed entry with a roller' => [static fn (array $data): array => array_replace($data, ['kind' => 'revealed', 'opening' => []]), 'A revealed entry'];
        yield 'a revealed entry without roll' => [static fn (array $data): array => array_replace($data, ['kind' => 'revealed', 'roller' => null, 'opening' => [], 'roll' => null]), 'A revealed entry'];
        yield 'a revealed entry without commitment' => [static fn (array $data): array => array_replace($data, ['kind' => 'revealed', 'roller' => null, 'opening' => [], 'commitment' => null]), 'A revealed entry'];
        yield 'a closing with a roll' => [static fn (array $data): array => array_replace($data, ['kind' => 'closed', 'commitment' => null, 'roller' => null]), 'A closed entry'];
        yield 'a closing with a commitment' => [static fn (array $data): array => array_replace($data, ['kind' => 'closed', 'roll' => null, 'roller' => null]), 'A closed entry'];
        yield 'a closing with a roller' => [static fn (array $data): array => array_replace($data, ['kind' => 'closed', 'roll' => null, 'commitment' => null]), 'A closed entry'];
        yield 'a closing with an opening' => [static fn (array $data): array => array_replace($data, ['kind' => 'closed', 'roll' => null, 'commitment' => null, 'roller' => null, 'opening' => []]), 'A closed entry'];
        yield 'a short roll' => [static fn (array $data): array => array_replace($data, ['roll' => 'abc']), 'The roll of an entry'];
        yield 'a roll with a prefix' => [static fn (array $data): array => array_replace($data, ['roll' => 'zz'.str_repeat('a', 32)]), 'The roll of an entry'];
        yield 'a roll too long' => [static fn (array $data): array => array_replace($data, ['roll' => str_repeat('a', 33)]), 'The roll of an entry'];
        yield 'a roll with a final newline' => [static fn (array $data): array => array_replace($data, ['roll' => str_repeat('a', 32)."\n"]), 'The roll of an entry'];
        yield 'a bad commitment' => [static fn (array $data): array => array_replace($data, ['commitment' => 'abc']), 'A commitment is'];
        yield 'a bad roller' => [static fn (array $data): array => array_replace($data, ['roller' => '']), 'A roller is'];
        yield 'a bad scene' => [static fn (array $data): array => array_replace($data, ['scene' => 'the cave']), 'A scene is'];
        yield 'a bad time' => [static fn (array $data): array => array_replace($data, ['at' => 'now']), 'A time is'];
        yield 'a bad previous hash' => [static fn (array $data): array => array_replace($data, ['previous' => 'abc']), 'A previous hash is'];
        yield 'a bad hash' => [static fn (array $data): array => array_replace($data, ['hash' => 'abc']), 'A hash is'];
        yield 'a bad opening' => [static fn (array $data): array => array_replace($data, ['kind' => 'revealed', 'roller' => null, 'opening' => ['roll' => 'x']]), 'An opening holds'];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    #[DataProvider('badEntries')]
    public function testAnEntryThatDoesNotReadIsRefused(callable $change, string $message): void
    {
        $data = JournalEntry::committed(new Table()->rolls->roll('cave', '1d6', 'gm'), JournalEntry::GENESIS)->toArray();

        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains($message);

        JournalEntry::fromArray($change($data));
    }
}
