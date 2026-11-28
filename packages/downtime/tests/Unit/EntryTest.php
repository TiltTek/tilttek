<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Downtime\Entry;
use Tilttek\Downtime\Exception\DowntimeRefused;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Stage;
use Tilttek\Downtime\Tests\Unit\Fixture\Rules;

#[CoversClass(Entry::class)]
#[CoversClass(Stage::class)]
#[CoversClass(DowntimeRefused::class)]
final class EntryTest extends TestCase
{
    private function entry(Stage $stage = Stage::Queued, int $progress = 0, ?int $roll = null, ?string $outcome = null): Entry
    {
        return new Entry('entry-1', 'campaign-1', 'character-1', 'member-2', Rules::SYSTEM, Rules::crafting(), $stage, $progress, $roll, $outcome);
    }

    public function testANewEntryIsQueuedWithNoDaySpent(): void
    {
        $entry = new Entry('entry-1', 'campaign-1', 'character-1', 'member-2', Rules::SYSTEM, Rules::crafting());

        self::assertSame('entry-1', $entry->id);
        self::assertSame('campaign-1', $entry->campaign);
        self::assertSame('character-1', $entry->character);
        self::assertSame('member-2', $entry->chosenBy);
        self::assertSame(Rules::SYSTEM, $entry->system);
        self::assertSame('crafting', $entry->activity->key);
        self::assertSame(Stage::Queued, $entry->stage);
        self::assertSame(0, $entry->progress);
        self::assertNull($entry->roll);
        self::assertNull($entry->outcome);
        self::assertNull($entry->result());
        self::assertSame(0, $entry->version);
        self::assertSame(5, $entry->remaining());
        self::assertSame(['gp' => 25, 'supplies' => 1], $entry->costs());
    }

    public function testAnEntryAdvancesUntilItsDaysAreSpent(): void
    {
        $two = $this->entry()->advancedBy(2);
        self::assertSame(Stage::Queued, $two->stage);
        self::assertSame(2, $two->progress);
        self::assertSame(3, $two->remaining());

        $four = $two->advancedBy(2);
        self::assertSame(Stage::Queued, $four->stage);
        self::assertSame(1, $four->remaining());

        $done = $four->advancedBy(9);
        self::assertSame(Stage::AwaitingRoll, $done->stage);
        self::assertSame(5, $done->progress);
        self::assertSame(0, $done->remaining());
        self::assertSame(Stage::AwaitingRoll, $this->entry()->advancedBy(5)->stage);
    }

    public function testAnEntryAdvancesByOneDayOrMore(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An entry advances by 1 day or more.');

        $this->entry()->advancedBy(0);
    }

    public function testOnlyAQueuedEntryAdvances(): void
    {
        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only a queued entry advances. The entry entry-1 is awaiting_roll.');

        $this->entry(Stage::AwaitingRoll, 5)->advancedBy(1);
    }

    public function testAnEntryIsResolvedThenConfirmed(): void
    {
        $resolved = $this->entry(Stage::AwaitingRoll, 5)->resolved('made', 14);
        self::assertSame(Stage::Resolved, $resolved->stage);
        self::assertSame(14, $resolved->roll);
        self::assertSame('made', $resolved->outcome);
        self::assertSame('Made', $resolved->result()?->label);

        $confirmed = $resolved->confirmed();
        self::assertSame(Stage::Confirmed, $confirmed->stage);
        self::assertSame(14, $confirmed->roll);
        self::assertSame('made', $confirmed->outcome);

        $chosen = $resolved->confirmed('masterwork');
        self::assertSame('masterwork', $chosen->outcome);
        self::assertSame(14, $chosen->roll);
    }

    public function testTheGameMasterConfirmsAnOutcomeWithoutARoll(): void
    {
        $confirmed = $this->entry(Stage::AwaitingRoll, 5)->confirmed('spoiled');

        self::assertSame(Stage::Confirmed, $confirmed->stage);
        self::assertSame('spoiled', $confirmed->outcome);
        self::assertNull($confirmed->roll);
    }

    public function testAnEntryAwaitingItsRollIsConfirmedOnlyWithAnOutcome(): void
    {
        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only a resolved entry, or one awaiting its roll with an outcome chosen, is confirmed. The entry entry-1 is awaiting_roll.');

        $this->entry(Stage::AwaitingRoll, 5)->confirmed();
    }

    public function testAQueuedEntryIsNotConfirmed(): void
    {
        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('is queued.');

        $this->entry()->confirmed('made');
    }

    public function testOnlyAnEntryAwaitingItsRollIsResolved(): void
    {
        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only an entry awaiting its roll is resolved. The entry entry-1 is resolved.');

        $this->entry(Stage::Resolved, 5, 3, 'spoiled')->resolved('made', 12);
    }

    public function testAnOpenEntryIsCancelledWithWhatItHad(): void
    {
        $queued = $this->entry(Stage::Queued, 2)->cancelled();
        self::assertSame(Stage::Cancelled, $queued->stage);
        self::assertSame(2, $queued->progress);

        $resolved = $this->entry(Stage::Resolved, 5, 3, 'spoiled')->cancelled();
        self::assertSame(Stage::Cancelled, $resolved->stage);
        self::assertSame(3, $resolved->roll);
        self::assertSame('spoiled', $resolved->outcome);
        self::assertSame(Stage::Cancelled, $this->entry(Stage::AwaitingRoll, 5)->cancelled()->stage);
    }

    /**
     * @return iterable<string, array{Entry}>
     */
    public static function closedEntries(): iterable
    {
        $entry = new Entry('entry-1', 'campaign-1', 'character-1', 'member-2', Rules::SYSTEM, Rules::crafting(), Stage::Resolved, 5, null, 'made');

        yield 'confirmed' => [$entry->confirmed()];
        yield 'cancelled' => [$entry->cancelled()];
    }

    #[DataProvider('closedEntries')]
    public function testAClosedEntryStaysAsItIs(Entry $entry): void
    {
        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('A confirmed or cancelled entry stays as it is.');

        $entry->cancelled();
    }

    public function testTheStagesTellWhetherAnEntryIsOpen(): void
    {
        self::assertTrue(Stage::Queued->isOpen());
        self::assertTrue(Stage::AwaitingRoll->isOpen());
        self::assertTrue(Stage::Resolved->isOpen());
        self::assertFalse(Stage::Confirmed->isOpen());
        self::assertFalse(Stage::Cancelled->isOpen());
    }

    public function testTheVersionChangesAlone(): void
    {
        $entry = $this->entry(Stage::Resolved, 5, 12, 'made')->withVersion(3);

        self::assertSame(3, $entry->version);
        self::assertSame(Stage::Resolved, $entry->stage);
        self::assertSame(12, $entry->roll);
        self::assertSame('made', $entry->outcome);
        self::assertSame(2, $this->entry()->withVersion(2)->advancedBy(1)->version);
        self::assertSame(3, $entry->confirmed()->version);
    }

    /**
     * @return iterable<string, array{Stage, int, int|null, string|null, string}>
     */
    public static function disagreements(): iterable
    {
        yield 'days below 0' => [Stage::Queued, -1, null, null, 'The days spent on the entry entry-1 are 0 to 5.'];
        yield 'days beyond the activity' => [Stage::Cancelled, 6, null, null, 'The days spent on the entry entry-1 are 0 to 5.'];
        yield 'queued with every day spent' => [Stage::Queued, 5, null, null, 'A queued entry has days still to spend: entry-1 has none.'];
        yield 'awaiting a roll with days left' => [Stage::AwaitingRoll, 4, null, null, 'An entry awaiting its roll, resolved or confirmed has spent all its days: entry-1 has not.'];
        yield 'resolved with days left' => [Stage::Resolved, 4, null, 'made', 'An entry awaiting its roll, resolved or confirmed has spent all its days: entry-1 has not.'];
        yield 'confirmed with days left' => [Stage::Confirmed, 0, null, 'made', 'An entry awaiting its roll, resolved or confirmed has spent all its days: entry-1 has not.'];
        yield 'resolved without an outcome' => [Stage::Resolved, 5, null, null, 'A resolved or confirmed entry has an outcome: entry-1 has none.'];
        yield 'confirmed without an outcome' => [Stage::Confirmed, 5, null, null, 'A resolved or confirmed entry has an outcome: entry-1 has none.'];
        yield 'a roll without an outcome' => [Stage::Cancelled, 5, 12, null, 'An entry has a roll only with its outcome: entry-1 has none.'];
        yield 'queued with an outcome' => [Stage::Queued, 0, null, 'made', 'A queued entry, or one awaiting its roll, has no outcome yet: entry-1 has one.'];
        yield 'awaiting a roll with an outcome' => [Stage::AwaitingRoll, 5, null, 'made', 'A queued entry, or one awaiting its roll, has no outcome yet: entry-1 has one.'];
        yield 'an outcome the activity lacks' => [Stage::Resolved, 5, null, 'lost', 'The activity crafting has no outcome lost.'];
        yield 'a roll below its bounds' => [Stage::Resolved, 5, -Entry::MAX_ROLL - 1, 'made', 'A roll is -1000000 to 1000000.'];
        yield 'a roll above its bounds' => [Stage::Resolved, 5, Entry::MAX_ROLL + 1, 'made', 'A roll is -1000000 to 1000000.'];
    }

    #[DataProvider('disagreements')]
    public function testTheValuesAgreeWithTheStage(Stage $stage, int $progress, ?int $roll, ?string $outcome, string $message): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->entry($stage, $progress, $roll, $outcome);
    }

    public function testAnEntryKeepsTheBoundsOfItsValues(): void
    {
        self::assertSame(-Entry::MAX_ROLL, $this->entry(Stage::Resolved, 5, -Entry::MAX_ROLL, 'spoiled')->roll);
        self::assertSame(Entry::MAX_ROLL, $this->entry(Stage::Confirmed, 5, Entry::MAX_ROLL, 'masterwork')->roll);
        self::assertSame(0, $this->entry(Stage::Cancelled, 0)->progress);
        self::assertSame(5, $this->entry(Stage::Cancelled, 5)->progress);
        self::assertSame(4, $this->entry(Stage::Queued, 4)->progress);
    }

    public function testOnlyARolledActivityHasARoll(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('Only a rolled activity has a roll: training is not rolled.');

        new Entry('entry-1', 'campaign-1', 'character-1', 'member-2', Rules::SYSTEM, Rules::training(), Stage::Resolved, 3, 12, 'learned');
    }

    public function testTheVersionIsZeroOrMore(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The version of an entry is 0 or more.');

        $this->entry()->withVersion(-1);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'the entry' => ['', 'campaign-1', 'character-1', 'member-2', 'fantasy', 'an entry'];
        yield 'the campaign' => ['entry-1', '1', 'character-1', 'member-2', 'fantasy', 'a campaign'];
        yield 'the character' => ['entry-1', 'campaign-1', 'c d', 'member-2', 'fantasy', 'a character'];
        yield 'the member' => ['entry-1', 'campaign-1', 'character-1', 'm@example.com', 'fantasy', 'a member'];
        yield 'the system' => ['entry-1', 'campaign-1', 'character-1', 'member-2', '', 'a system'];
    }

    #[DataProvider('badIdentifiers')]
    public function testItsIdentifiersAreIdentifiers(string $id, string $campaign, string $character, string $member, string $system, string $what): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The identifier of %s is', $what));

        new Entry($id, $campaign, $character, $member, $system, Rules::crafting());
    }
}
