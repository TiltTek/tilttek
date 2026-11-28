<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Tests\Unit;

use ArrayIterator;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\CelebrationChanged;
use Tilttek\Celebrations\Celebrations;
use Tilttek\Celebrations\Change;
use Tilttek\Celebrations\Entry;
use Tilttek\Celebrations\EntryState;
use Tilttek\Celebrations\Exception\InvalidCelebration;
use Tilttek\Celebrations\Group;
use Tilttek\Celebrations\Limits;
use Tilttek\Celebrations\Motion;
use Tilttek\Celebrations\Preferences;
use Tilttek\Celebrations\Store\InMemoryCelebrationStore;
use Tilttek\Celebrations\Tests\Unit\Fixture\Recorder;

#[CoversClass(Celebrations::class)]
#[CoversClass(InMemoryCelebrationStore::class)]
#[CoversClass(CelebrationChanged::class)]
final class CelebrationsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryCelebrationStore $store;

    private Recorder $recorder;

    private Celebrations $celebrations;

    private ResourceReference $ada;

    private ResourceReference $bo;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-27 10:00:00', 'UTC');
        $this->store = new InMemoryCelebrationStore();
        $this->recorder = new Recorder();
        $this->celebrations = new Celebrations($this->store, $this->clock, new Limits(), [$this->recorder]);
        $this->ada = ResourceReference::of('member', '1');
        $this->bo = ResourceReference::of('member', '2');
    }

    public function testAnAchievementWaitsInItsMembersInbox(): void
    {
        $entry = $this->celebrations->receive($this->badge($this->ada, 'first-roll'));

        self::assertSame(1, $entry->sequence);
        self::assertSame(EntryState::Pending, $entry->state);
        self::assertEquals($this->clock->now(), $entry->receivedAt);
        self::assertNull($entry->settledAt);
        self::assertSame(1, $this->celebrations->count($this->ada));
        self::assertSame(0, $this->celebrations->count($this->bo));
        self::assertSame(['member:1 received badges badge.first-roll#1'], $this->recorder->summary());
        self::assertEquals($this->clock->now(), $this->recorder->changes[0]->at);
        self::assertSame(Change::Received, $this->recorder->changes[0]->change);
    }

    public function testAnAchievementToldAgainIsKeptOnceAndToldOnce(): void
    {
        $first = $this->celebrations->receive($this->badge($this->ada, 'first-roll'));
        $this->clock->sleep(60);
        $again = $this->celebrations->receive($this->badge($this->ada, 'first-roll'));

        self::assertEquals($first, $again);
        self::assertSame(1, $this->celebrations->count($this->ada));
        self::assertCount(1, $this->recorder->changes);

        $other = $this->celebrations->receive($this->badge($this->bo, 'first-roll'));
        self::assertSame(1, $other->sequence);
        self::assertSame(1, $this->celebrations->count($this->bo));
    }

    public function testAnAchievementToldAgainAfterItsMomentPassedIsReadLapsed(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'first-roll', '+1 hour'));
        $this->clock->sleep(3600);

        $again = $this->celebrations->receive($this->badge($this->ada, 'first-roll', '+1 hour'));

        self::assertSame(EntryState::Lapsed, $again->state);
    }

    public function testAnAchievementWhoseMomentPassedIsKeptLapsed(): void
    {
        $earned = $this->clock->now()->modify('-2 hours');
        $late = new Achievement($this->ada, 'badges', 'badge', 'badge.late', 'late#1', $earned, [], $earned->modify('+1 hour'));

        $entry = $this->celebrations->receive($late);

        self::assertSame(EntryState::Lapsed, $entry->state);
        self::assertEquals($earned->modify('+1 hour'), $entry->settledAt);
        self::assertSame(0, $this->celebrations->count($this->ada));
        self::assertSame([], $this->celebrations->pending($this->ada));
        self::assertSame(['member:1 received badges late#1'], $this->recorder->summary());
        self::assertSame([$entry->sequence], array_map(static fn (Entry $e): int => $e->sequence, $this->celebrations->history($this->ada)));
    }

    public function testAnAchievementLapsesWhenItsMomentComesWhileItWaits(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'first-roll', '+1 hour'));
        $this->clock->sleep(3599);
        self::assertSame(1, $this->celebrations->count($this->ada));

        $this->clock->sleep(1);
        self::assertSame(0, $this->celebrations->count($this->ada));
        self::assertNull($this->celebrations->next($this->ada));
        self::assertSame(0, $this->celebrations->markAllSeen($this->ada));

        $history = $this->celebrations->history($this->ada);
        self::assertSame(EntryState::Lapsed, $history[0]->state);
    }

    public function testPendingEntriesAreGroupedBySourceAndKindTheEarliestEarnedFirst(): void
    {
        $now = $this->clock->now();
        $this->celebrations->receive(new Achievement($this->ada, 'progression', 'level', 'track.reader', 'reader#2', $now->modify('-1 minute'), ['to' => 2]));
        $this->celebrations->receive($this->badge($this->ada, 'b', null, $now->modify('-3 minutes')));
        $this->celebrations->receive($this->badge($this->ada, 'a', null, $now->modify('-2 minutes')));
        $this->celebrations->receive($this->badge($this->ada, 'c', null, $now->modify('-3 minutes')));
        $this->celebrations->receive(new Achievement($this->ada, 'badges', 'tier', 'badge.b', 'b#tier.2', $now));

        $groups = $this->celebrations->pending($this->ada);

        self::assertSame([['badges', 'badge', 3, [2, 4, 3]], ['progression', 'level', 1, [1]], ['badges', 'tier', 1, [5]]], array_map(
            static fn (Group $group): array => [$group->source, $group->kind, $group->count, $group->sequences()],
            $groups,
        ));
        self::assertSame(5, $this->celebrations->count($this->ada));
    }

    public function testEntriesEarnedWithinOneSecondComeInTheOrderOfTheirMicroseconds(): void
    {
        $second = new DateTimeImmutable('2026-11-27 09:00:00.000000 UTC');
        $this->celebrations->receive($this->badge($this->ada, 'later', null, $second->modify('+500 milliseconds')));
        $this->celebrations->receive($this->badge($this->ada, 'sooner', null, $second->modify('+200 milliseconds')));
        $this->celebrations->receive($this->badge($this->ada, 'tied', null, $second->modify('+200 milliseconds')));
        $this->celebrations->receive($this->badge($this->ada, 'before', null, $second->modify('-100 milliseconds')));

        self::assertSame([4, 2, 3, 1], $this->celebrations->pending($this->ada)[0]->sequences());
    }

    public function testAGroupShowsAsManyEntriesAsTheLimitsAllow(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(shownPerGroup: 2));

        foreach (['a', 'b', 'c'] as $badge) {
            $celebrations->receive($this->badge($this->ada, $badge));
        }

        $group = $celebrations->pending($this->ada)[0];

        self::assertSame([1, 2], $group->sequences());
        self::assertSame(3, $group->count);
        self::assertSame(1, $group->hidden());
        self::assertSame(3, $celebrations->markGroupSeen($this->ada, 'badges', 'badge'));
    }

    public function testTheNextCelebrationIsTheFirstGroupWithTheMembersPreferences(): void
    {
        self::assertNull($this->celebrations->next($this->bo));

        $this->celebrations->receive($this->badge($this->ada, 'a'));
        $this->celebrations->receive(new Achievement($this->ada, 'quests', 'quest', 'quest.tavern', 'tavern#1', $this->clock->now()));

        $next = $this->celebrations->next($this->ada);
        self::assertNotNull($next);
        self::assertSame('badges', $next->group->source);
        self::assertSame(Motion::Full, $next->motion);
        self::assertFalse($next->sound);
        self::assertSame(1, $next->remaining);

        self::assertSame(Motion::Reduced, $this->celebrations->next($this->ada, true)?->motion);

        $this->celebrations->choose($this->ada, new Preferences(motion: Motion::None, sound: true));
        $chosen = $this->celebrations->next($this->ada);
        self::assertSame(Motion::None, $chosen?->motion);
        self::assertTrue($chosen->sound);

        $this->celebrations->markSeen($this->ada, $chosen->group->sequences());
        $last = $this->celebrations->next($this->ada);
        self::assertSame('quests', $last?->group->source);
        self::assertSame(0, $last->remaining);
    }

    public function testAMemberWhoTurnedCelebrationsOffKeepsTheInbox(): void
    {
        $this->celebrations->choose($this->ada, new Preferences(celebrate: false));
        $this->celebrations->receive($this->badge($this->ada, 'a'));

        self::assertNull($this->celebrations->next($this->ada));
        self::assertSame(1, $this->celebrations->count($this->ada));
        self::assertCount(1, $this->celebrations->pending($this->ada));
        self::assertFalse($this->celebrations->preferences($this->ada)->celebrate);
        self::assertTrue($this->celebrations->preferences($this->bo)->celebrate);
        self::assertSame(['member:1 preferences_changed - -', 'member:1 received badges badge.a#1'], $this->recorder->summary());
    }

    public function testEntriesAreMarkedSeenBySequence(): void
    {
        foreach (['a', 'b', 'c'] as $badge) {
            $this->celebrations->receive($this->badge($this->ada, $badge));
        }

        $this->celebrations->receive($this->badge($this->bo, 'a'));
        $this->recorder->changes = [];
        $this->clock->sleep(5);

        self::assertSame(2, $this->celebrations->markSeen($this->ada, [3, 1, 9]));
        self::assertSame(0, $this->celebrations->markSeen($this->ada, [1]));
        self::assertSame(1, $this->celebrations->count($this->ada));
        self::assertSame(1, $this->celebrations->count($this->bo));
        self::assertSame(['member:1 seen badges badge.a#1', 'member:1 seen badges badge.c#1'], $this->recorder->summary());

        $seen = $this->store->find('member:1', 'badges', 'badge.a#1');
        self::assertSame(EntryState::Seen, $seen?->state);
        self::assertEquals($this->clock->now(), $seen->settledAt);
    }

    public function testMarkingSeenTakesAsManySequencesAsPendingEntries(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(pendingPerMember: 2, entriesPerMember: 2));

        self::assertSame(0, $celebrations->markSeen($this->ada, [1, 2]));

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('At most 2 entries are marked seen at once; 3 were given.');

        $celebrations->markSeen($this->ada, [1, 2, 3]);
    }

    public function testAGroupIsMarkedSeenWhole(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'a'));
        $this->celebrations->receive($this->badge($this->ada, 'b'));
        $this->celebrations->receive(new Achievement($this->ada, 'badges', 'tier', 'badge.a', 'a#tier', $this->clock->now()));
        $this->celebrations->receive(new Achievement($this->ada, 'quests', 'badge', 'quest.a', 'a#q', $this->clock->now()));

        self::assertSame(2, $this->celebrations->markGroupSeen($this->ada, 'badges', 'badge'));
        self::assertSame(2, $this->celebrations->count($this->ada));
        self::assertSame(['tier', 'badge'], array_map(static fn (Group $g): string => $g->kind, $this->celebrations->pending($this->ada)));
    }

    public function testMarkingAGroupSeenChecksItsKeys(): void
    {
        try {
            $this->celebrations->markGroupSeen($this->ada, 'Badges', 'badge');
            self::fail('An invalid source was taken.');
        } catch (InvalidCelebration $invalidCelebration) {
            self::assertStringContainsString('The key of a source', $invalidCelebration->getMessage());
        }

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('The key of a kind');

        $this->celebrations->markGroupSeen($this->ada, 'badges', 'Badge');
    }

    public function testEveryPendingEntryIsMarkedSeen(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'a'));
        $this->celebrations->receive(new Achievement($this->ada, 'quests', 'quest', 'quest.a', 'a#q', $this->clock->now()));
        $this->celebrations->receive($this->badge($this->bo, 'a'));

        self::assertSame(2, $this->celebrations->markAllSeen($this->ada));
        self::assertSame(0, $this->celebrations->count($this->ada));
        self::assertSame(1, $this->celebrations->count($this->bo));
        self::assertSame([], $this->celebrations->pending($this->ada));
    }

    public function testAWithdrawnEntryIsNeverShownAndAWithdrawalToldTwiceIsHarmless(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'a'));
        $this->celebrations->receive($this->badge($this->ada, 'b'));
        $this->celebrations->markSeen($this->ada, [2]);

        $this->recorder->changes = [];
        $this->clock->sleep(10);

        self::assertTrue($this->celebrations->withdraw($this->ada, 'badges', 'badge.a#1'));
        self::assertFalse($this->celebrations->withdraw($this->ada, 'badges', 'badge.a#1'));
        self::assertTrue($this->celebrations->withdraw($this->ada, 'badges', 'badge.b#1'));
        self::assertFalse($this->celebrations->withdraw($this->ada, 'badges', 'badge.z#1'));
        self::assertFalse($this->celebrations->withdraw($this->bo, 'badges', 'badge.a#1'));

        self::assertSame(0, $this->celebrations->count($this->ada));
        self::assertSame(['member:1 withdrawn badges badge.a#1', 'member:1 withdrawn badges badge.b#1'], $this->recorder->summary());

        $withdrawn = $this->store->find('member:1', 'badges', 'badge.a#1');
        self::assertSame(EntryState::Withdrawn, $withdrawn?->state);
        self::assertEquals($this->clock->now(), $withdrawn->settledAt);

        $again = $this->celebrations->receive($this->badge($this->ada, 'a'));
        self::assertSame(EntryState::Withdrawn, $again->state);
    }

    public function testAWithdrawalChecksItsKeys(): void
    {
        try {
            $this->celebrations->withdraw($this->ada, 'Badges', 'a');
            self::fail('An invalid source was taken.');
        } catch (InvalidCelebration $invalidCelebration) {
            self::assertStringContainsString('The key of a source', $invalidCelebration->getMessage());
        }

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('The occurrence of an achievement');

        $this->celebrations->withdraw($this->ada, 'badges', 'a b');
    }

    public function testTheEarliestEarnedLapseWhenThePendingEntriesReachTheirBound(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(pendingPerMember: 2, entriesPerMember: 10), [$this->recorder]);
        $now = $this->clock->now();

        $celebrations->receive($this->badge($this->ada, 'late', null, $now->modify('-1 minute')));
        $celebrations->receive($this->badge($this->ada, 'early', null, $now->modify('-2 minutes')));

        $this->recorder->changes = [];

        $celebrations->receive($this->badge($this->ada, 'new'));

        self::assertSame(2, $celebrations->count($this->ada));
        self::assertSame([1, 3], $celebrations->pending($this->ada)[0]->sequences());
        self::assertSame(['member:1 lapsed badges badge.early#1', 'member:1 received badges badge.new#1'], $this->recorder->summary());

        $lapsed = $this->store->find('member:1', 'badges', 'badge.early#1');
        self::assertSame(EntryState::Lapsed, $lapsed?->state);
        self::assertEquals($now, $lapsed->settledAt);
    }

    public function testEntriesWhoseMomentPassedLapseFirstToMakeRoom(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(pendingPerMember: 2, entriesPerMember: 10), [$this->recorder]);

        $celebrations->receive($this->badge($this->ada, 'brief', '+1 minute'));
        $celebrations->receive($this->badge($this->ada, 'kept'));

        $this->clock->sleep(60);
        $this->recorder->changes = [];

        $celebrations->receive($this->badge($this->ada, 'new'));

        self::assertSame(2, $celebrations->count($this->ada));
        self::assertSame([2, 3], $celebrations->pending($this->ada)[0]->sequences());
        self::assertSame(['member:1 lapsed badges badge.brief#1', 'member:1 received badges badge.new#1'], $this->recorder->summary());
        self::assertSame(EntryState::Lapsed, $this->store->find('member:1', 'badges', 'badge.brief#1')?->state);
        self::assertSame(1, $this->store->countEntries('member:1', EntryState::Lapsed));
    }

    public function testTheInboxDropsItsFirstSettledEntriesBeyondItsBound(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(pendingPerMember: 2, entriesPerMember: 3), [$this->recorder]);

        $celebrations->receive($this->badge($this->ada, 'a'));
        $celebrations->receive($this->badge($this->ada, 'b'));
        $celebrations->markSeen($this->ada, [2]);
        $celebrations->receive($this->badge($this->ada, 'c'));
        $celebrations->markSeen($this->ada, [3]);

        $celebrations->receive($this->badge($this->ada, 'd'));
        self::assertSame([4, 3, 1], $this->sequences($celebrations->history($this->ada)));

        $celebrations->receive($this->badge($this->ada, 'e'));
        self::assertSame([5, 4, 3], $this->sequences($celebrations->history($this->ada)));
        self::assertSame(['member:1 lapsed badges badge.a#1', 'member:1 received badges badge.e#1'], \array_slice($this->recorder->summary(), -2));
        self::assertNull($this->store->find('member:1', 'badges', 'badge.a#1'));
        self::assertNull($this->store->find('member:1', 'badges', 'badge.b#1'));
    }

    public function testTheInboxKeepsItsPendingEntriesWhenItTrims(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(pendingPerMember: 2, entriesPerMember: 2));

        $celebrations->receive($this->badge($this->ada, 'a'));
        $celebrations->receive($this->badge($this->ada, 'b'));

        self::assertSame([2, 1], $this->sequences($celebrations->history($this->ada)));

        $celebrations->receive($this->badge($this->ada, 'c'));

        self::assertSame([3, 2], $this->sequences($celebrations->history($this->ada)));
        self::assertSame(2, $celebrations->count($this->ada));
    }

    public function testTheHistoryReadsPagesTheLastReceivedFirst(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $badge) {
            $this->celebrations->receive($this->badge($this->ada, $badge));
        }

        $this->celebrations->receive($this->badge($this->bo, 'z'));

        self::assertSame([5, 4, 3, 2, 1], $this->sequences($this->celebrations->history($this->ada)));
        self::assertSame([5, 4], $this->sequences($this->celebrations->history($this->ada, 2)));
        self::assertSame([3, 2], $this->sequences($this->celebrations->history($this->ada, 2, 4)));
        self::assertSame([1], $this->sequences($this->celebrations->history($this->ada, 2, 2)));
        self::assertSame([], $this->celebrations->history($this->ada, 2, 1));
        self::assertCount(5, $this->celebrations->history($this->ada, 100));
        self::assertCount(1, $this->celebrations->history($this->ada, 1));
    }

    public function testAHistoryPageHoldsOneToAHundredEntries(): void
    {
        try {
            $this->celebrations->history($this->ada, 0);
            self::fail('An empty page was taken.');
        } catch (InvalidCelebration $invalidCelebration) {
            self::assertSame('A page of the history holds 1 to 100 entries; 0 is out of bounds.', $invalidCelebration->getMessage());
        }

        $this->expectException(InvalidCelebration::class);
        $this->expectExceptionMessageIsOrContains('A page of the history holds 1 to 100 entries; 101 is out of bounds.');

        $this->celebrations->history($this->ada, 101);
    }

    public function testAForgottenMemberLeavesNothingBehindAndGetsNewSequences(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'a'));
        $this->celebrations->choose($this->ada, new Preferences(celebrate: false));
        $this->celebrations->receive($this->badge($this->bo, 'a'));

        $this->recorder->changes = [];

        $this->celebrations->forget($this->ada);

        self::assertSame([], $this->celebrations->history($this->ada));
        self::assertTrue($this->celebrations->preferences($this->ada)->celebrate);
        self::assertSame(1, $this->celebrations->count($this->bo));
        self::assertSame(['member:1 forgotten - -'], $this->recorder->summary());

        self::assertSame(2, $this->celebrations->receive($this->badge($this->ada, 'a'))->sequence);
    }

    public function testObserversAreTakenFromAnyIterable(): void
    {
        $celebrations = new Celebrations($this->store, $this->clock, new Limits(), new ArrayIterator([$this->recorder]));

        $celebrations->receive($this->badge($this->ada, 'a'));

        self::assertCount(1, $this->recorder->changes);
    }

    public function testTheStoreCountsAndFiltersByState(): void
    {
        $this->celebrations->receive($this->badge($this->ada, 'a'));
        $this->celebrations->receive($this->badge($this->ada, 'b'));
        $this->celebrations->markSeen($this->ada, [1]);

        self::assertSame(2, $this->store->countEntries('member:1'));
        self::assertSame(1, $this->store->countEntries('member:1', EntryState::Seen));
        self::assertSame([2], $this->sequences($this->store->entries('member:1', EntryState::Pending)));
        self::assertSame(0, $this->store->countEntries('member:3'));

        $this->store->remove('member:1', 'badges', 'badge.a#1');
        self::assertSame([2], $this->sequences($this->store->entries('member:1')));
    }

    public function testGroupsAndEntriesAreToldApartWhereverTheirKeysSplit(): void
    {
        $now = $this->clock->now();

        foreach ([['ab', 'c', 'c'], ['a', 'bc', 'bc'], ['a', 'c', 'c'], ['a', 'c', 'x']] as [$source, $kind, $occurrence]) {
            $this->celebrations->receive(new Achievement($this->ada, $source, $kind, 'subject', $occurrence, $now));
        }

        $groups = array_map(
            static fn (Group $group): array => [$group->source, $group->kind, $group->count],
            $this->celebrations->pending($this->ada),
        );

        self::assertSame([['ab', 'c', 1], ['a', 'bc', 1], ['a', 'c', 2]], $groups);
        self::assertSame(4, $this->store->countEntries('member:1'));
    }

    public function testTheHistoryHoldsFiftyEntriesByDefault(): void
    {
        for ($i = 1; $i <= 51; ++$i) {
            $this->celebrations->receive($this->badge($this->ada, 'badge'.$i));
        }

        $page = $this->celebrations->history($this->ada);

        self::assertCount(50, $page);
        self::assertSame(51, $page[0]->sequence);
    }

    public function testTheStoreListsItsEntriesInTheOrderOfTheirSequences(): void
    {
        $now = $this->clock->now();
        $second = new Entry(new Achievement($this->ada, 'badges', 'badge', 'badge.b', 'b#1', $now), 2, EntryState::Pending, $now);
        $first = new Entry(new Achievement($this->ada, 'badges', 'badge', 'badge.a', 'a#1', $now), 1, EntryState::Pending, $now);

        $this->store->save($second);
        $this->store->save($first);

        self::assertSame([1, 2], $this->sequences($this->store->entries('member:1')));
    }

    private function badge(ResourceReference $member, string $badge, ?string $showFor = null, ?DateTimeImmutable $earnedAt = null): Achievement
    {
        $earnedAt ??= $this->clock->now();

        return new Achievement($member, 'badges', 'badge', 'badge.'.$badge, 'badge.'.$badge.'#1', $earnedAt, [], null === $showFor ? null : $earnedAt->modify($showFor));
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<int>
     */
    private function sequences(array $entries): array
    {
        return array_map(static fn (Entry $entry): int => $entry->sequence, $entries);
    }
}
