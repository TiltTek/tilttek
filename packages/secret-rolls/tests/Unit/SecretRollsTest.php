<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\Exception\SecretRollRefused;
use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Journal\EntryKind;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Journal\SceneJournal;
use Tilttek\SecretRolls\RollCommitment;
use Tilttek\SecretRolls\RollOpening;
use Tilttek\SecretRolls\RollVerifier;
use Tilttek\SecretRolls\SecretRolls;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(SecretRolls::class)]
final class SecretRollsTest extends TestCase
{
    public function testARollIsSealedAndOnlyItsCommitmentIsPublished(): void
    {
        $table = new Table();
        $hidden = $table->rolls->roll('cave', '1d20+3', 'gm-1', 'Perception of the goblins', at: Table::at());

        self::assertSame(Encoding::rollId('cave', 1), $hidden->roll());
        self::assertSame('cave', $hidden->scene());
        self::assertSame(1, $hidden->opening->position);
        self::assertSame('1d20+3', $hidden->opening->expression);
        self::assertSame('NegativeInfinity', $hidden->opening->rounding);
        self::assertSame('Perception of the goblins', $hidden->opening->context);
        self::assertSame('gm-1', $hidden->roller);
        self::assertEquals(Table::at(), $hidden->rolledAt);
        self::assertSame($hidden, $table->rolls->hidden($hidden->roll()));

        [$entry] = $table->published;
        self::assertSame(EntryKind::Committed, $entry->kind);
        self::assertSame($hidden->commitment()->hex, $entry->commitment);
        self::assertSame(JournalEntry::GENESIS, $entry->previous);
        self::assertNull($entry->opening);
        self::assertSame([$entry], $table->rolls->journal('cave')->entries);
        self::assertTrue(new RollVerifier()->verify($hidden->opening, RollCommitment::fromHex($hidden->commitment()->hex)));
    }

    public function testTheResultIsRolledFromTheSeedWithTheRollAsClientSeed(): void
    {
        $table = new Table();
        $hidden = $table->rolls->roll('cave', '4d6', rounding: RoundingMode::PositiveInfinity);
        $again = new DiceRoller()->rollVerifiable(new DiceRoller()->parse('4d6'), Seed::fromHex($hidden->opening->seed), $hidden->roll(), 0, RoundingMode::PositiveInfinity);

        self::assertSame('PositiveInfinity', $hidden->opening->rounding);
        self::assertSame($again->result->toArray(), $hidden->opening->result);
        self::assertSame(64, \strlen($hidden->opening->seed));
    }

    public function testAnInputIsReadInTheNotationOfItsLocale(): void
    {
        $table = new Table();

        self::assertSame('2d6', $table->rolls->roll('cave', '2d6', locale: 'en')->opening->expression);
        self::assertSame('1d8', $table->rolls->roll('cave', new DiceRoller()->parse('1d8'))->opening->expression);
    }

    public function testEachRollChainsOnTheLastEntry(): void
    {
        $table = new Table();
        $first = $table->rolls->roll('cave', '1d6');
        $second = $table->rolls->roll('cave', '1d6');
        $journal = $table->rolls->journal('cave');

        self::assertSame(2, $second->opening->position);
        self::assertSame(Encoding::rollId('cave', 2), $second->roll());
        self::assertSame($journal->entries[0]->hash, $journal->entries[1]->previous);
        self::assertNotSame($first->opening->seed, $second->opening->seed);
        self::assertTrue($journal->holds());
    }

    public function testARevealPublishesTheOpeningsInTheOrderGivenOnce(): void
    {
        $table = new Table();
        $first = $table->rolls->roll('cave', '1d6');
        $second = $table->rolls->roll('cave', '1d6');

        $entries = $table->rolls->reveal('cave', $second->roll(), $first->roll(), $second->roll());

        self::assertCount(2, $entries);
        self::assertSame([3, 4], [$entries[0]->position, $entries[1]->position]);
        self::assertEquals($second->opening, $entries[0]->opening);
        self::assertEquals($first->opening, $entries[1]->opening);
        self::assertSame($entries[0]->hash, $entries[1]->previous);
        self::assertSame($table->rolls->journal('cave')->entries[1]->hash, $entries[0]->previous);
        self::assertSame($entries, \array_slice($table->published, 2));
        self::assertTrue($table->rolls->journal('cave')->holds());
    }

    public function testARollIsRevealedOnce(): void
    {
        $table = new Table();
        $roll = $table->rolls->roll('cave', '1d6')->roll();
        $table->rolls->reveal('cave', $roll);

        $this->expectException(SecretRollRefused::class);
        $this->expectExceptionMessageIsOrContains('revealed already');

        $table->rolls->reveal('cave', $roll);
    }

    public function testOnlyARollOfTheSceneIsRevealed(): void
    {
        $table = new Table();
        $roll = $table->rolls->roll('cave', '1d6')->roll();

        $this->expectException(SecretRollRefused::class);
        $this->expectExceptionMessageIsOrContains('The scene forest committed no roll');

        $table->rolls->reveal('forest', $roll);
    }

    public function testARollWhoseOpeningTheStoreLostIsNotRevealed(): void
    {
        $store = new InMemorySecretRollStore();
        $table = new Table();
        $hidden = $table->rolls->roll('cave', '1d6');
        $store->append($table->published[0]);

        $this->expectException(SecretRollRefused::class);
        $this->expectExceptionMessageIsOrContains('no sealed roll');

        new SecretRolls($store)->reveal('cave', $hidden->roll());
    }

    public function testNothingIsAppendedWhenOneRollOfARevealIsRefused(): void
    {
        $table = new Table();
        $roll = $table->rolls->roll('cave', '1d6')->roll();

        try {
            $table->rolls->reveal('cave', $roll, 'ffffffffffffffffffffffffffffffff');
            self::fail('The unknown roll was revealed.');
        } catch (SecretRollRefused) {
            self::assertCount(1, $table->rolls->journal('cave')->entries);
        }
    }

    public function testClosingRevealsTheRollsChosenAndWithholdsTheOthers(): void
    {
        $table = new Table();
        $shown = $table->rolls->roll('cave', '1d6')->roll();
        $kept = $table->rolls->roll('cave', '1d6')->roll();

        $entries = $table->rolls->close('cave', $shown);
        $journal = $table->rolls->journal('cave');

        self::assertSame([EntryKind::Revealed, EntryKind::Closed], array_map(static fn (JournalEntry $entry): EntryKind => $entry->kind, $entries));
        self::assertSame(4, $entries[1]->position);
        self::assertSame($entries[0]->hash, $entries[1]->previous);
        self::assertSame($entries[1], $table->published[3]);
        self::assertTrue($journal->isClosed());
        self::assertSame([$kept], array_map(static fn (JournalEntry $entry): ?string => $entry->roll, $journal->withheld()));
        self::assertTrue($journal->holds());
    }

    public function testClosingWithoutARollRevealsNone(): void
    {
        $table = new Table();
        $table->rolls->roll('cave', '1d6');

        $entries = $table->rolls->close('cave');

        self::assertCount(1, $entries);
        self::assertSame(EntryKind::Closed, $entries[0]->kind);
        self::assertSame(2, $entries[0]->position);
    }

    public function testAWithheldRollIsStillRevealedAfterTheScene(): void
    {
        $table = new Table();
        $roll = $table->rolls->roll('cave', '1d6')->roll();
        $table->rolls->close('cave');

        $table->rolls->reveal('cave', $roll);

        self::assertSame([], $table->rolls->journal('cave')->withheld());
        self::assertTrue($table->rolls->journal('cave')->holds());
    }

    public function testClosingRevealingAllRevealsEveryRollLeft(): void
    {
        $table = new Table();
        $first = $table->rolls->roll('cave', '1d6')->roll();
        $second = $table->rolls->roll('cave', '1d6')->roll();
        $table->rolls->reveal('cave', $first);

        $entries = $table->rolls->closeRevealingAll('cave');

        self::assertCount(2, $entries);
        self::assertSame($second, $entries[0]->roll);
        self::assertSame([], $table->rolls->journal('cave')->withheld());
        self::assertTrue($table->rolls->journal('cave')->holds());
    }

    public function testAClosedSceneTakesNoRollNorASecondClosing(): void
    {
        $table = new Table();
        $table->rolls->close('cave');

        try {
            $table->rolls->roll('cave', '1d6');
            self::fail('A closed scene took a roll.');
        } catch (SecretRollRefused $secretRollRefused) {
            self::assertStringContainsString('The scene cave is closed', $secretRollRefused->getMessage());
        }

        $this->expectException(SecretRollRefused::class);

        $table->rolls->close('cave');
    }

    public function testAFullSceneTakesNoRoll(): void
    {
        $store = $this->filled(SceneJournal::MAX_ROLLS);

        $this->expectException(SecretRollRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('holds %d entries', SceneJournal::MAX_ROLLS));

        new SecretRolls($store)->roll('cave', '1d6');
    }

    public function testASceneOneBelowTheLimitStillTakesARoll(): void
    {
        $store = $this->filled(SceneJournal::MAX_ROLLS - 1);

        self::assertSame(SceneJournal::MAX_ROLLS, new SecretRolls($store)->roll('cave', '1d6')->opening->position);
    }

    public function testTheSceneTheRollerAndTheContextAreChecked(): void
    {
        $table = new Table();

        foreach ([['bad scene', null, null], ['cave', '', null], ['cave', null, "two\nlines"], ['cave', null, str_repeat('a', Encoding::MAX_CONTEXT + 1)], ['cave', str_repeat('a', Encoding::MAX_ROLLER + 1), null]] as [$scene, $roller, $context]) {
            try {
                $table->rolls->roll($scene, '1d6', $roller, $context);
                self::fail('A roll was taken with a value the library refuses.');
            } catch (InvalidSecretRoll) {
                self::assertSame([], $table->rolls->journal('cave')->entries);
            }
        }

        self::assertSame(str_repeat('a', Encoding::MAX_CONTEXT), $table->rolls->roll('cave', '1d6', str_repeat('r', Encoding::MAX_ROLLER), str_repeat('a', Encoding::MAX_CONTEXT))->opening->context);
    }

    public function testAnInputThatDoesNotReadIsRefusedBeforeAnythingIsKept(): void
    {
        $table = new Table();

        try {
            $table->rolls->roll('cave', '1d');
            self::fail('An input that does not read was rolled.');
        } catch (InvalidExpression) {
            self::assertSame([], $table->published);
        }
    }

    public function testARollWithoutATimeIsDatedNow(): void
    {
        $before = new DateTimeImmutable('-1 second');
        $hidden = new Table()->rolls->roll('cave', '1d6');

        self::assertGreaterThanOrEqual($before, $hidden->rolledAt);
        self::assertLessThanOrEqual(new DateTimeImmutable('+1 second'), $hidden->rolledAt);
    }

    public function testTheDefaultsRollFromASecureSourceAndPublishNothing(): void
    {
        $store = new InMemorySecretRollStore();
        $hidden = new SecretRolls($store)->roll('cave', '1d6');

        self::assertSame($hidden, $store->hidden($hidden->roll()));
        self::assertNull(new SecretRolls($store)->hidden('ffffffffffffffffffffffffffffffff'));
    }

    private function filled(int $count): InMemorySecretRollStore
    {
        $store = new InMemorySecretRollStore();
        $previous = JournalEntry::GENESIS;

        for ($position = 1; $position <= $count; ++$position) {
            $opening = new RollOpening(Encoding::rollId('cave', $position), 'cave', $position, '1d2', 'NegativeInfinity', str_repeat('0', 64), [['total' => 1]], null);
            $entry = JournalEntry::committed(new HiddenRoll($opening, null, Table::at()), $previous);
            $store->append($entry);
            $previous = $entry->hash;
        }

        return $store;
    }
}
