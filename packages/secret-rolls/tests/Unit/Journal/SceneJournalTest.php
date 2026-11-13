<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit\Journal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Journal\SceneJournal;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(SceneJournal::class)]
final class SceneJournalTest extends TestCase
{
    public function testAnHonestJournalHoldsAndReadsBackFromItsExport(): void
    {
        $journal = $this->honest();

        self::assertSame([], $journal->problems());
        self::assertTrue($journal->holds());
        self::assertEquals($journal, SceneJournal::fromJson($journal->toJson()));
        self::assertEquals($journal, SceneJournal::fromArray($journal->toArray()));
        self::assertSame(Encoding::json($journal->toArray()), $journal->toJson());
        self::assertStringContainsString('"scene":"cave"', $journal->toJson());
    }

    public function testItTellsItsHeadPositionStateAndRolls(): void
    {
        $table = new Table();
        $shown = $table->rolls->roll('cave', '1d6')->roll();
        $kept = $table->rolls->roll('cave', '1d8')->roll();
        $empty = $table->rolls->journal('forest');

        self::assertSame(JournalEntry::GENESIS, $empty->head());
        self::assertSame(1, $empty->nextPosition());
        self::assertFalse($empty->isClosed());
        self::assertSame([], $empty->revealed());

        $table->rolls->close('cave', $shown);
        $journal = $table->rolls->journal('cave');

        self::assertSame($journal->entries[3]->hash, $journal->head());
        self::assertSame(5, $journal->nextPosition());
        self::assertTrue($journal->isClosed());
        self::assertSame($journal->entries[0], $journal->committed($shown));
        self::assertNull($journal->committed('ffffffffffffffffffffffffffffffff'));
        self::assertTrue($journal->isRevealed($shown));
        self::assertFalse($journal->isRevealed($kept));
        self::assertSame([$shown], array_keys($journal->revealed()));
        self::assertSame($journal->entries[2]->opening, $journal->revealed()[$shown]);
        self::assertSame([$journal->entries[1]], $journal->withheld());
    }

    public function testAnEntryOfAnotherSceneIsAFault(): void
    {
        $data = $this->honest()->toArray();
        $data['scene'] = 'forest';

        self::assertSame(['Entry 1 belongs to the scene cave.', 'Entry 1 commits a roll whose identifier is not its own.'], \array_slice(SceneJournal::fromArray($data)->problems(), 0, 2));
    }

    public function testAPositionOutOfOrderIsAFault(): void
    {
        $entries = $this->honest()->toArray()['entries'];
        $entries[] = $this->rehash(['scene' => 'cave', 'position' => 9, 'kind' => 'closed', 'roll' => null, 'commitment' => null, 'roller' => null, 'opening' => null, 'at' => '2026-11-12T21:00:00.000000Z', 'previous' => $entries[3]['hash']]);

        self::assertSame(['Entry 5 says it is at position 9.', 'Entry 5 closes the scene a second time.'], $this->problems($entries));
    }

    public function testAnEntryThatDoesNotCarryThePreviousHashIsAFault(): void
    {
        $entries = $this->honest()->toArray()['entries'];
        $entries[1] = $this->rehash([...$entries[1], 'previous' => str_repeat('a', 64)]);

        self::assertSame(['Entry 2 does not carry the hash of the entry before it.', 'Entry 3 does not carry the hash of the entry before it.'], $this->problems($entries));
    }

    public function testAnEntryChangedAfterItsHashIsAFault(): void
    {
        $entries = $this->honest()->toArray()['entries'];
        $entries[0]['roller'] = 'someone-else';

        self::assertSame(['Entry 1 does not match its hash.'], $this->problems($entries));
    }

    public function testARollCommittedAfterTheClosingIsAFault(): void
    {
        $table = new Table();
        $table->rolls->close('cave');

        $journal = $table->rolls->journal('cave');
        $entries = $journal->toArray()['entries'];
        $hidden = new Table()->rolls->roll('cave', '1d6');
        $committed = JournalEntry::committed($hidden, $journal->head())->toArray();
        $entries[] = $this->rehash([...$committed, 'position' => 2, 'roll' => Encoding::rollId('cave', 2)]);

        self::assertSame(['Entry 2 commits a roll after the scene was closed.'], $this->problems($entries));
    }

    public function testARollCommittedTwiceOrUnderAnotherIdentifierIsAFault(): void
    {
        $entries = $this->honest()->toArray()['entries'];
        $first = $entries[0];
        $entries = [$first, $this->rehash([...$first, 'position' => 2, 'previous' => $first['hash']])];

        self::assertSame(['Entry 2 commits a roll whose identifier is not its own.'], $this->problems($entries));

        $entries[1] = $this->rehash([...$first, 'position' => 2, 'roll' => str_repeat('b', 32), 'previous' => $first['hash']]);

        self::assertSame(['Entry 2 commits a roll whose identifier is not its own.'], $this->problems($entries));
    }

    public function testARevealOfARollNotCommittedBeforeIsAFault(): void
    {
        $entries = $this->honest()->toArray()['entries'];
        $reveal = $entries[2];
        $entries = [$this->rehash([...$reveal, 'position' => 1, 'previous' => JournalEntry::GENESIS])];

        self::assertSame(['Entry 1 reveals a roll the scene did not commit before.'], $this->problems($entries));
    }

    public function testARollRevealedTwiceIsAFault(): void
    {
        $entries = \array_slice($this->honest()->toArray()['entries'], 0, 3);
        $entries[] = $this->rehash([...$entries[2], 'position' => 4, 'previous' => $entries[2]['hash']]);

        self::assertSame(['Entry 4 reveals a roll revealed already.'], $this->problems($entries));
    }

    public function testARevealOfTheOpeningOfAnotherPositionIsAFault(): void
    {
        $table = new Table();
        $table->rolls->roll('cave', '1d6');

        $entries = $table->rolls->journal('cave')->toArray()['entries'];
        $other = $table->rolls->roll('cave', '1d6')->opening->toArray();
        $entries[] = $this->rehash(['scene' => 'cave', 'position' => 2, 'kind' => 'revealed', 'roll' => $entries[0]['roll'], 'commitment' => $entries[0]['commitment'], 'roller' => null, 'opening' => [...$other, 'roll' => $entries[0]['roll']], 'at' => '2026-11-12T21:00:00.000000Z', 'previous' => $entries[0]['hash']]);

        self::assertSame([
            'Entry 2 reveals an opening of another scene or position.',
            'Entry 2 reveals an opening that does not match the commitment published, or does not roll again to its result.',
        ], $this->problems($entries));
    }

    public function testARevealUnderAnotherCommitmentOrWithAnotherResultIsAFault(): void
    {
        $entries = \array_slice($this->honest()->toArray()['entries'], 0, 3);
        $entries[2] = $this->rehash([...$entries[2], 'commitment' => str_repeat('c', 64)]);

        self::assertSame(['Entry 3 reveals an opening that does not match the commitment published, or does not roll again to its result.'], $this->problems($entries));

        $entries = \array_slice($this->honest()->toArray()['entries'], 0, 3);
        $opening = $entries[2]['opening'];
        self::assertIsArray($opening);
        $opening['context'] = 'Another reason';
        $entries[2] = $this->rehash([...$entries[2], 'opening' => $opening]);

        self::assertSame(['Entry 3 reveals an opening that does not match the commitment published, or does not roll again to its result.'], $this->problems($entries));
    }

    public function testASecondClosingIsAFault(): void
    {
        $entries = $this->honest()->toArray()['entries'];
        $entries[] = $this->rehash([...$entries[3], 'position' => 5, 'previous' => $entries[3]['hash']]);

        self::assertSame(['Entry 5 closes the scene a second time.'], $this->problems($entries));
    }

    public function testARevealAfterTheClosingHolds(): void
    {
        $table = new Table();
        $roll = $table->rolls->roll('cave', '1d6')->roll();
        $table->rolls->close('cave');
        $table->rolls->reveal('cave', $roll);

        self::assertTrue($table->rolls->journal('cave')->holds());
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>}>
     */
    public static function unreadable(): iterable
    {
        yield 'no scene' => [['entries' => []]];
        yield 'entries not a list' => [['scene' => 'cave', 'entries' => ['a' => []]]];
        yield 'entries not an array' => [['scene' => 'cave', 'entries' => 'none']];
        yield 'too many entries' => [['scene' => 'cave', 'entries' => array_fill(0, SceneJournal::MAX_ENTRIES + 1, [])]];
        yield 'an entry not an object' => [['scene' => 'cave', 'entries' => ['entry']]];
        yield 'a bad scene' => [['scene' => 'a cave', 'entries' => []]];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('unreadable')]
    public function testDataThatDoesNotReadAsAJournalIsRefused(array $data): void
    {
        $this->expectException(InvalidSecretRoll::class);

        SceneJournal::fromArray($data);
    }

    public function testTheMostEntriesStillRead(): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains('A journal entry holds a kind');

        SceneJournal::fromArray(['scene' => 'cave', 'entries' => array_fill(0, SceneJournal::MAX_ENTRIES, [])]);
    }

    public function testTheLimitsAreTheRollsRevealedAndTheClosing(): void
    {
        self::assertSame(4_999, SceneJournal::MAX_ROLLS);
        self::assertSame(9_999, SceneJournal::MAX_ENTRIES);
        self::assertSame(32 * 1024 * 1024, SceneJournal::MAX_JSON);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function unreadableJson(): iterable
    {
        yield 'not JSON' => ['{', 'A journal is a JSON object.'];
        yield 'not an object' => ['"cave"', 'A journal is a JSON object.'];
        yield 'too deep' => [str_repeat('[', 64).str_repeat(']', 64), 'A journal is a JSON object.'];
        yield 'too long' => [str_repeat(' ', SceneJournal::MAX_JSON + 1), 'A journal is at most 33554432 bytes of JSON.'];
    }

    #[DataProvider('unreadableJson')]
    public function testJsonThatDoesNotReadAsAJournalIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains($message);

        SceneJournal::fromJson($json);
    }

    public function testJsonAtTheDepthLimitStillReads(): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains('A journal holds its scene');

        SceneJournal::fromJson(str_repeat('[', 63).str_repeat(']', 63));
    }

    public function testJsonOfTheLongestLengthStillReads(): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains('A journal holds its scene');

        SceneJournal::fromJson('[]'.str_repeat(' ', SceneJournal::MAX_JSON - 2));
    }

    /**
     * A scene with a roll revealed, a roll withheld, then the closing.
     */
    private function honest(): SceneJournal
    {
        $table = new Table();
        $shown = $table->rolls->roll('cave', '1d6', 'gm', 'Ambush')->roll();
        $table->rolls->roll('cave', '2d8');
        $table->rolls->close('cave', $shown);

        return $table->rolls->journal('cave');
    }

    /**
     * The entry with the hash of its fields.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function rehash(array $entry): array
    {
        $entry['hash'] = Encoding::hash([Encoding::VERSION, $entry['scene'], $entry['position'], $entry['kind'], $entry['roll'], $entry['commitment'], $entry['roller'], $entry['opening'], $entry['at'], $entry['previous']]);

        return $entry;
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<string>
     */
    private function problems(array $entries): array
    {
        return SceneJournal::fromArray(['scene' => 'cave', 'entries' => $entries])->problems();
    }

    public function testEntriesThatAreNotAListOrAreTooManyAreRefusedWhateverTheyHold(): void
    {
        $entry = $this->honest()->entries[0]->toArray();

        foreach ([['a' => $entry], array_fill(0, SceneJournal::MAX_ENTRIES + 1, $entry)] as $entries) {
            try {
                SceneJournal::fromArray(['scene' => 'cave', 'entries' => $entries]);
                self::fail('Entries that are not a list, or too many, were read.');
            } catch (InvalidSecretRoll $invalid) {
                self::assertStringContainsString('A journal holds its scene and a list of at most', $invalid->getMessage());
            }
        }
    }

    public function testEveryOpeningRevealedIsListed(): void
    {
        $table = new Table();
        $first = $table->rolls->roll('cave', '1d6')->roll();
        $second = $table->rolls->roll('cave', '1d8')->roll();
        $table->rolls->close('cave', $first, $second);

        self::assertSame([$first, $second], array_keys($table->rolls->journal('cave')->revealed()));
    }
}
