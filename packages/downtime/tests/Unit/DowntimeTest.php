<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Downtime\Contract\Roller;
use Tilttek\Downtime\Downtime;
use Tilttek\Downtime\Entry;
use Tilttek\Downtime\Event\ActivityCancelled;
use Tilttek\Downtime\Event\ActivityChosen;
use Tilttek\Downtime\Event\ActivityCompleted;
use Tilttek\Downtime\Event\ActivityConfirmed;
use Tilttek\Downtime\Event\ActivityProgressed;
use Tilttek\Downtime\Event\ActivityResolved;
use Tilttek\Downtime\Event\DowntimeEvent;
use Tilttek\Downtime\Exception\DowntimeRefused;
use Tilttek\Downtime\Exception\EntryNotFound;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Exception\UnknownActivity;
use Tilttek\Downtime\Rules\InMemoryRuleBook;
use Tilttek\Downtime\Stage;
use Tilttek\Downtime\Store\InMemoryEntryStore;
use Tilttek\Downtime\Tests\Unit\Fixture\Dice;
use Tilttek\Downtime\Tests\Unit\Fixture\Observed;
use Tilttek\Downtime\Tests\Unit\Fixture\Recorded;
use Tilttek\Downtime\Tests\Unit\Fixture\Rules;
use Tilttek\Downtime\Tests\Unit\Fixture\Table;

#[CoversClass(Downtime::class)]
#[CoversClass(EntryNotFound::class)]
#[CoversClass(UnknownActivity::class)]
#[CoversClass(DowntimeRefused::class)]
#[CoversClass(ActivityChosen::class)]
#[CoversClass(ActivityProgressed::class)]
#[CoversClass(ActivityCompleted::class)]
#[CoversClass(ActivityResolved::class)]
#[CoversClass(ActivityConfirmed::class)]
#[CoversClass(ActivityCancelled::class)]
final class DowntimeTest extends TestCase
{
    private const string CAMPAIGN = 'campaign-1';

    private const string GM = 'member-1';

    private const string ARIA = 'member-2';

    private const string BRAM = 'member-3';

    private InMemoryEntryStore $store;

    private Observed $observed;

    private Downtime $downtime;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryEntryStore();
        $this->observed = new Observed();
        $this->downtime = $this->service();
    }

    private function service(?Roller $roller = null): Downtime
    {
        return new Downtime(
            $this->store,
            new InMemoryRuleBook(Rules::system()),
            new Table([self::CAMPAIGN => self::GM, 'campaign-2' => 'member-9'], ['aria' => self::ARIA, 'bram' => self::BRAM]),
            $roller,
            $this->observed,
        );
    }

    private function choose(string $id, string $character = 'aria', string $activity = 'crafting', string $by = self::ARIA): Entry
    {
        $entry = $this->downtime->choose($id, self::CAMPAIGN, $character, Rules::SYSTEM, $activity, $by);
        $this->observed->take();

        return $entry;
    }

    /**
     * @param list<DowntimeEvent> $events
     *
     * @return list<array{class-string, string, string, string}>
     */
    private function facts(array $events): array
    {
        return array_map(static fn (DowntimeEvent $event): array => [$event::class, $event->entry, $event->campaign, $event->character], $events);
    }

    public function testAPlayerChoosesAnActivityTheRulesDeclare(): void
    {
        $entry = $this->downtime->choose('entry-1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'crafting', self::ARIA);

        self::assertSame(1, $entry->version);
        self::assertSame(Stage::Queued, $entry->stage);
        self::assertSame(self::ARIA, $entry->chosenBy);
        self::assertSame(Rules::SYSTEM, $entry->system);
        self::assertSame(5, $entry->activity->days);
        self::assertSame(['gp' => 25, 'supplies' => 1], $entry->costs());
        self::assertSame($entry, $this->store->entry('entry-1'));
        self::assertSame([[ActivityChosen::class, 'entry-1', self::CAMPAIGN, 'aria']], $this->facts($this->observed->take()));
    }

    public function testTheGameMasterChoosesForACharacter(): void
    {
        self::assertSame(self::GM, $this->downtime->choose('entry-1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'training', self::GM)->chosenBy);
    }

    public function testNoOneElseChooses(): void
    {
        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the player of the character aria, or the game master of the campaign campaign-1, does that.');

        try {
            $this->downtime->choose('entry-1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'crafting', self::BRAM);
        } finally {
            self::assertNull($this->store->entry('entry-1'));
            self::assertSame([], $this->observed->events);
        }
    }

    public function testAPlayerChoosesOnlyInTheirCampaign(): void
    {
        $this->expectException(DowntimeRefused::class);

        $this->downtime->choose('entry-1', 'campaign-3', 'aria', Rules::SYSTEM, 'crafting', self::ARIA);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unknownActivities(): iterable
    {
        yield 'an activity the system lacks' => [Rules::SYSTEM, 'carousing'];
        yield 'a system without rules' => ['space', 'crafting'];
    }

    #[DataProvider('unknownActivities')]
    public function testAnActivityTheRulesLackIsRefused(string $system, string $activity): void
    {
        try {
            $this->downtime->choose('entry-1', self::CAMPAIGN, 'aria', $system, $activity, self::ARIA);
            self::fail('The activity was chosen.');
        } catch (UnknownActivity $unknownActivity) {
            self::assertSame($system, $unknownActivity->system);
            self::assertSame($activity, $unknownActivity->activity);
            self::assertSame(\sprintf('The system %s declares no activity %s.', $system, $activity), $unknownActivity->getMessage());
        }

        self::assertNull($this->store->entry('entry-1'));
        self::assertSame([], $this->observed->events);
    }

    public function testAnIdentifierIsChosenOnce(): void
    {
        $this->choose('entry-1');

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('An entry has the identifier entry-1.');

        $this->downtime->choose('entry-1', self::CAMPAIGN, 'bram', Rules::SYSTEM, 'training', self::BRAM);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, string}>
     */
    public static function badChoices(): iterable
    {
        yield 'the entry' => ['1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'crafting', 'The identifier of an entry'];
        yield 'the campaign' => ['entry-1', '', 'aria', Rules::SYSTEM, 'crafting', 'The identifier of a campaign'];
        yield 'the character' => ['entry-1', self::CAMPAIGN, '-aria', Rules::SYSTEM, 'crafting', 'The identifier of a character'];
        yield 'the system' => ['entry-1', self::CAMPAIGN, 'aria', 'a b', 'crafting', 'The identifier of a system'];
        yield 'the activity' => ['entry-1', self::CAMPAIGN, 'aria', Rules::SYSTEM, str_repeat('a', 65), 'The key of an activity'];
    }

    #[DataProvider('badChoices')]
    public function testAChoiceIsMadeOfIdentifiers(string $id, string $campaign, string $character, string $system, string $activity, string $message): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->downtime->choose($id, $campaign, $character, $system, $activity, self::ARIA);
    }

    public function testTheMemberIsAnIdentifier(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a member');

        $this->downtime->choose('entry-1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'crafting', 'aria@example.com');
    }

    public function testTheEntryIsCheckedBeforeTheRulesAreAsked(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The identifier of an entry');

        $this->downtime->choose('1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'nothing', self::ARIA);
    }

    public function testTheGameMasterOfAnAdvanceIsAnIdentifier(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a member');

        $this->downtime->advance(self::CAMPAIGN, 1, 'aria@example.com');
    }

    public function testACharacterWithNoDaysLeftDoesNotStopTheOthers(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->choose('a2', 'aria', 'training');
        $this->choose('b1', 'bram', 'training', self::BRAM);

        $kept = $this->downtime->advance(self::CAMPAIGN, 3, self::GM);

        self::assertSame(['a1', 'b1'], array_map(static fn (Entry $entry): string => $entry->id, $kept));
    }

    public function testTheQueueOfACharacterIsBounded(): void
    {
        for ($i = 1; $i <= Downtime::MAX_QUEUE; ++$i) {
            $this->choose('entry-'.$i);
        }

        $this->choose('other', 'bram', 'training', self::BRAM);

        try {
            $this->downtime->choose('entry-9', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'crafting', self::ARIA);
            self::fail('The queue grew beyond its bound.');
        } catch (DowntimeRefused $downtimeRefused) {
            self::assertSame('The character aria has 8 open entries already.', $downtimeRefused->getMessage());
        }

        $this->downtime->cancel('entry-3', self::ARIA);

        self::assertSame(Stage::Queued, $this->downtime->choose('entry-9', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'crafting', self::ARIA)->stage);
    }

    public function testAnEntryIsReadByItsIdentifier(): void
    {
        $entry = $this->choose('entry-1');

        self::assertSame($entry, $this->downtime->entry('entry-1'));
    }

    public function testAnEntryNoOneKeptIsNotFound(): void
    {
        $this->expectException(EntryNotFound::class);
        $this->expectExceptionMessageIsOrContains('No entry has the identifier entry-1.');

        $this->downtime->entry('entry-1');
    }

    public function testAnEntryIsReadByAnIdentifier(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The identifier of an entry');

        $this->downtime->entry('');
    }

    public function testAnAdvanceSpendsTheDaysOnEachQueueInTheOrderChosen(): void
    {
        $this->choose('a1');
        $this->choose('b1', 'bram', 'training', self::BRAM);
        $this->choose('a2', 'aria', 'training');

        $first = $this->downtime->advance(self::CAMPAIGN, 2, self::GM);

        self::assertSame(['a1', 'b1'], array_map(static fn (Entry $entry): string => $entry->id, $first));
        self::assertSame([2, 2], array_map(static fn (Entry $entry): int => $entry->progress, $first));
        self::assertSame([2, 2], array_map(static fn (Entry $entry): int => $entry->version, $first));
        self::assertSame([
            [ActivityProgressed::class, 'a1', self::CAMPAIGN, 'aria'],
            [ActivityProgressed::class, 'b1', self::CAMPAIGN, 'bram'],
        ], $this->facts($this->observed->take()));

        $second = $this->downtime->advance(self::CAMPAIGN, 4, self::GM);

        self::assertSame(['a1', 'b1', 'a2'], array_map(static fn (Entry $entry): string => $entry->id, $second));
        self::assertSame([Stage::AwaitingRoll, Stage::Resolved, Stage::Queued], array_map(static fn (Entry $entry): Stage => $entry->stage, $second));
        self::assertSame([5, 3, 1], array_map(static fn (Entry $entry): int => $entry->progress, $second));
        self::assertSame('learned', $second[1]->outcome);
        self::assertNull($second[1]->roll);
        self::assertSame([
            [ActivityCompleted::class, 'a1', self::CAMPAIGN, 'aria'],
            [ActivityResolved::class, 'b1', self::CAMPAIGN, 'bram'],
            [ActivityProgressed::class, 'a2', self::CAMPAIGN, 'aria'],
        ], $this->facts($this->observed->take()));
        self::assertSame($second[0], $this->store->entry('a1'));

        $third = $this->downtime->advance(self::CAMPAIGN, 1, self::GM);

        self::assertSame(['a2'], array_map(static fn (Entry $entry): string => $entry->id, $third));
        self::assertSame(2, $third[0]->progress);
    }

    public function testDaysLeftOverByOneEntryGoToTheNext(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->choose('a2', 'aria', 'training');
        $this->choose('a3', 'aria', 'training');

        $kept = $this->downtime->advance(self::CAMPAIGN, 7, self::GM);

        self::assertSame([Stage::Resolved, Stage::Resolved, Stage::Queued], array_map(static fn (Entry $entry): Stage => $entry->stage, $kept));
        self::assertSame(1, $kept[2]->progress);
    }

    public function testDaysSpentExactlyStopAtTheEntryThatTakesThem(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->choose('a2', 'aria', 'training');

        $kept = $this->downtime->advance(self::CAMPAIGN, 3, self::GM);

        self::assertSame(['a1'], array_map(static fn (Entry $entry): string => $entry->id, $kept));
        self::assertSame(0, $this->downtime->entry('a2')->progress);
    }

    public function testARollerEndsARolledActivityWithTheOutcomeOfItsBand(): void
    {
        $dice = new Dice(14, 25);
        $downtime = $this->service($dice);
        $this->choose('a1');
        $this->choose('b1', 'bram', 'crafting', self::BRAM);

        $kept = $downtime->advance(self::CAMPAIGN, 5, self::GM);

        self::assertSame(['1d20+2', '1d20+2'], $dice->rolled);
        self::assertSame([Stage::Resolved, Stage::Resolved], array_map(static fn (Entry $entry): Stage => $entry->stage, $kept));
        self::assertSame([14, 25], array_map(static fn (Entry $entry): ?int => $entry->roll, $kept));
        self::assertSame(['made', 'masterwork'], array_map(static fn (Entry $entry): ?string => $entry->outcome, $kept));
        self::assertSame([ActivityResolved::class, ActivityResolved::class], array_map(static fn (DowntimeEvent $event): string => $event::class, $this->observed->take()));
    }

    public function testARollerRollsOnlyOnceTheDaysAreSpent(): void
    {
        $dice = new Dice(14);
        $this->choose('a1');

        $this->service($dice)->advance(self::CAMPAIGN, 4, self::GM);

        self::assertSame([], $dice->rolled);
    }

    public function testARollerNeverRollsForAFixedActivity(): void
    {
        $dice = new Dice(1);
        $this->choose('a1', 'aria', 'training');

        self::assertSame('learned', $this->service($dice)->advance(self::CAMPAIGN, 3, self::GM)[0]->outcome);
        self::assertSame([], $dice->rolled);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function rollsBeyondTheirBounds(): iterable
    {
        yield 'above' => [Entry::MAX_ROLL + 5, Entry::MAX_ROLL, 'masterwork'];
        yield 'below' => [-Entry::MAX_ROLL - 5, -Entry::MAX_ROLL, 'spoiled'];
        yield 'at the top' => [Entry::MAX_ROLL, Entry::MAX_ROLL, 'masterwork'];
        yield 'at the bottom' => [-Entry::MAX_ROLL, -Entry::MAX_ROLL, 'spoiled'];
    }

    #[DataProvider('rollsBeyondTheirBounds')]
    public function testARollIsKeptWithinItsBounds(int $rolled, int $kept, string $outcome): void
    {
        $this->choose('a1');

        $entry = $this->service(new Dice($rolled))->advance(self::CAMPAIGN, 5, self::GM)[0];

        self::assertSame($kept, $entry->roll);
        self::assertSame($outcome, $entry->outcome);
    }

    public function testAnAdvanceSpendsTheDaysOfOneCampaign(): void
    {
        $this->choose('a1');
        $this->downtime->choose('other', 'campaign-2', 'aria', Rules::SYSTEM, 'crafting', 'member-9');
        $this->observed->take();

        self::assertCount(1, $this->downtime->advance(self::CAMPAIGN, 1, self::GM));
        self::assertSame(0, $this->downtime->entry('other')->progress);
    }

    public function testAnAdvanceWithNothingQueuedKeepsAndTellsNothing(): void
    {
        $entry = $this->choose('a1', 'aria', 'training');
        $this->downtime->advance(self::CAMPAIGN, 3, self::GM);
        $this->observed->take();

        self::assertSame([], $this->downtime->advance(self::CAMPAIGN, 3, self::GM));
        self::assertSame([], $this->observed->events);
        self::assertSame($entry->version + 1, $this->downtime->entry('a1')->version);
    }

    public function testOnlyTheGameMasterAdvances(): void
    {
        $this->choose('a1');

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the game master of the campaign campaign-1 does that.');

        try {
            $this->downtime->advance(self::CAMPAIGN, 1, self::ARIA);
        } finally {
            self::assertSame(0, $this->downtime->entry('a1')->progress);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function advancesOutOfBounds(): iterable
    {
        yield 'none' => [0];
        yield 'above the most' => [Downtime::MAX_ADVANCE + 1];
    }

    #[DataProvider('advancesOutOfBounds')]
    public function testAnAdvanceIsBounded(int $days): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('An advance spends 1 to 365 days.');

        $this->downtime->advance(self::CAMPAIGN, $days, self::GM);
    }

    public function testAnAdvanceSpendsUpToItsMostDays(): void
    {
        $this->choose('a1', 'aria', 'training');

        self::assertSame(Stage::Resolved, $this->downtime->advance(self::CAMPAIGN, Downtime::MAX_ADVANCE, self::GM)[0]->stage);
    }

    public function testAnAdvanceNamesItsCampaignAndMember(): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a campaign');

        $this->downtime->advance('', 1, self::GM);
    }

    public function testAnAdvanceReadsAtMostItsMostEntries(): void
    {
        $this->queue(Downtime::MAX_READ);

        self::assertCount(Downtime::MAX_READ, $this->downtime->advance(self::CAMPAIGN, 1, self::GM));

        $this->queue(1, 'more');

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('The campaign campaign-1 has more than 1000 queued entries.');

        $this->downtime->advance(self::CAMPAIGN, 1, self::GM);
    }

    private function queue(int $count, string $prefix = 'e'): void
    {
        $entries = [];

        for ($i = 0; $i < $count; ++$i) {
            $entries[] = new Entry($prefix.$i, self::CAMPAIGN, 'c'.$i, self::GM, Rules::SYSTEM, Rules::crafting());
        }

        $this->store->save($entries);
    }

    public function testThePlayerRecordsTheRollFromTheTable(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 5, self::GM);
        $this->observed->take();

        $entry = $this->downtime->record('a1', 8, self::ARIA);

        self::assertSame(Stage::Resolved, $entry->stage);
        self::assertSame(8, $entry->roll);
        self::assertSame('spoiled', $entry->outcome);
        self::assertSame(3, $entry->version);
        self::assertSame([[ActivityResolved::class, 'a1', self::CAMPAIGN, 'aria']], $this->facts($this->observed->take()));
    }

    public function testTheGameMasterRecordsARoll(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 5, self::GM);

        self::assertSame('made', $this->downtime->record('a1', 10, self::GM)->outcome);
    }

    public function testNoOneElseRecordsARoll(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 5, self::GM);
        $this->observed->take();

        $this->expectException(DowntimeRefused::class);

        try {
            $this->downtime->record('a1', 10, self::BRAM);
        } finally {
            self::assertSame(Stage::AwaitingRoll, $this->downtime->entry('a1')->stage);
            self::assertSame([], $this->observed->events);
        }
    }

    public function testARollIsRecordedOnlyOnceTheDaysAreSpent(): void
    {
        $this->choose('a1');

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only an entry awaiting its roll is resolved.');

        $this->downtime->record('a1', 10, self::ARIA);
    }

    public function testTheGameMasterConfirmsTheOutcome(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->downtime->advance(self::CAMPAIGN, 3, self::GM);
        $this->observed->take();

        $entry = $this->downtime->confirm('a1', self::GM);

        self::assertSame(Stage::Confirmed, $entry->stage);
        self::assertSame('learned', $entry->outcome);
        self::assertSame([[ActivityConfirmed::class, 'a1', self::CAMPAIGN, 'aria']], $this->facts($this->observed->take()));
    }

    public function testTheGameMasterConfirmsAnotherOutcome(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 5, self::GM);

        $entry = $this->downtime->confirm('a1', self::GM, 'masterwork');

        self::assertSame(Stage::Confirmed, $entry->stage);
        self::assertSame('masterwork', $entry->outcome);
        self::assertNull($entry->roll);
    }

    public function testAnOutcomeConfirmedIsOneOfTheActivity(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 5, self::GM);

        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('The activity crafting has no outcome lost.');

        $this->downtime->confirm('a1', self::GM, 'lost');
    }

    public function testOnlyTheGameMasterConfirms(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->downtime->advance(self::CAMPAIGN, 3, self::GM);

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the game master of the campaign campaign-1 does that.');

        $this->downtime->confirm('a1', self::ARIA);
    }

    public function testThePlayerCancelsAQueuedEntry(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 2, self::GM);
        $this->observed->take();

        $entry = $this->downtime->cancel('a1', self::ARIA);

        self::assertSame(Stage::Cancelled, $entry->stage);
        self::assertSame(2, $entry->progress);
        self::assertSame([[ActivityCancelled::class, 'a1', self::CAMPAIGN, 'aria']], $this->facts($this->observed->take()));
    }

    public function testThePlayerCancelsNothingNoLongerQueued(): void
    {
        $this->choose('a1');
        $this->downtime->advance(self::CAMPAIGN, 5, self::GM);

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the game master cancels an entry no longer queued: a1 is awaiting_roll.');

        $this->downtime->cancel('a1', self::ARIA);
    }

    public function testTheGameMasterCancelsAResolvedEntry(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->downtime->advance(self::CAMPAIGN, 3, self::GM);

        $entry = $this->downtime->cancel('a1', self::GM);

        self::assertSame(Stage::Cancelled, $entry->stage);
        self::assertSame('learned', $entry->outcome);
    }

    public function testAConfirmedEntryIsNeverCancelled(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->downtime->advance(self::CAMPAIGN, 3, self::GM);
        $this->downtime->confirm('a1', self::GM);

        $this->expectException(DowntimeRefused::class);
        $this->expectExceptionMessageIsOrContains('A confirmed or cancelled entry stays as it is.');

        $this->downtime->cancel('a1', self::ARIA);
    }

    public function testNoOneElseCancels(): void
    {
        $this->choose('a1');

        $this->expectException(DowntimeRefused::class);

        $this->downtime->cancel('a1', self::BRAM);
    }

    public function testTheLogOfACharacterIsReadInTheOrderChosen(): void
    {
        $this->choose('a1', 'aria', 'training');
        $this->choose('b1', 'bram', 'training', self::BRAM);
        $this->choose('a2');
        $this->downtime->advance(self::CAMPAIGN, 3, self::GM);
        $this->downtime->confirm('a1', self::GM);

        self::assertSame(['a1', 'a2'], array_map(static fn (Entry $entry): string => $entry->id, $this->downtime->log(self::CAMPAIGN, 'aria', self::ARIA)));
        self::assertSame(['a1', 'a2'], array_map(static fn (Entry $entry): string => $entry->id, $this->downtime->log(self::CAMPAIGN, 'aria', self::GM)));
        self::assertSame(['a1'], array_map(static fn (Entry $entry): string => $entry->id, $this->downtime->log(self::CAMPAIGN, 'aria', self::ARIA, 1)));
        self::assertCount(2, $this->downtime->log(self::CAMPAIGN, 'aria', self::ARIA, Downtime::MAX_READ));
    }

    public function testOnlyThePlayerAndTheGameMasterReadALog(): void
    {
        $this->expectException(DowntimeRefused::class);

        $this->downtime->log(self::CAMPAIGN, 'aria', self::BRAM);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function limitsOutOfBounds(): iterable
    {
        yield 'none' => [0];
        yield 'above the most' => [Downtime::MAX_READ + 1];
    }

    #[DataProvider('limitsOutOfBounds')]
    public function testAReadOfALogIsBounded(int $limit): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('A read of entries is of 1 to 1000.');

        $this->downtime->log(self::CAMPAIGN, 'aria', self::ARIA, $limit);
    }

    public function testTheServiceWorksWithoutAnObserver(): void
    {
        $downtime = new Downtime($this->store, new InMemoryRuleBook(Rules::system()), new Table([self::CAMPAIGN => self::GM], ['aria' => self::ARIA]));

        self::assertSame(1, $downtime->choose('a1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'training', self::ARIA)->version);
        self::assertSame(Stage::Resolved, $downtime->advance(self::CAMPAIGN, Downtime::MAX_ADVANCE, self::GM)[0]->stage);
    }

    public function testTheServiceReadsOnlyWhatItsBoundsNeed(): void
    {
        $store = new Recorded();
        $downtime = new Downtime($store, new InMemoryRuleBook(Rules::system()), new Table([self::CAMPAIGN => self::GM], ['aria' => self::ARIA]));

        $downtime->choose('a1', self::CAMPAIGN, 'aria', Rules::SYSTEM, 'training', self::ARIA);
        $downtime->advance(self::CAMPAIGN, 1, self::GM);
        $downtime->log(self::CAMPAIGN, 'aria', self::ARIA);

        self::assertSame([['openOf', Downtime::MAX_QUEUE], ['queuedIn', Downtime::MAX_READ + 1], ['entriesOf', Downtime::MAX_READ]], $store->reads);
    }
}
