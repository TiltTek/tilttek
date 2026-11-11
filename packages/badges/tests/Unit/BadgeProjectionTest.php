<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Activity\VerbPattern;
use Tilttek\Badges\Award;
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Exception\InvalidBadge;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryProgressStore;
use Tilttek\Badges\Tests\Unit\Fixture\Clock;
use Tilttek\Badges\Tests\Unit\Fixture\Measured;
use Tilttek\Badges\Tests\Unit\Fixture\Observer;

#[CoversClass(BadgeProjection::class)]
#[CoversClass(Evaluator::class)]
final class BadgeProjectionTest extends TestCase
{
    private InMemoryActivityLog $log;

    private InMemoryProgressStore $progress;

    private InMemoryAwardStore $awards;

    private Observer $observer;

    private Measured $measures;

    #[Override]
    protected function setUp(): void
    {
        $this->log = new InMemoryActivityLog();
        $this->progress = new InMemoryProgressStore();
        $this->awards = new InMemoryAwardStore();
        $this->observer = new Observer();
        $this->measures = new Measured();
    }

    public function testTheProjection(): void
    {
        $projection = $this->projection(['a' => ['criterion' => ['all' => [['first' => ['verbs' => ['game.*']]], ['first' => ['verbs' => ['news.commented', 'game.*']]]]]], 'b' => ['criterion' => ['manual' => true]]]);

        self::assertSame('badges', $projection->name());
        self::assertSame('trophies', new BadgeProjection(new Evaluator(new Catalog(), $this->progress), $this->progress, $this->awards, name: 'trophies')->name());
        self::assertSame(['game.*', 'news.commented'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $projection->filter()->verbs->patterns));
    }

    public function testAMeasureNeedsMeasures(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A criterion of the catalog reads a measure, and no measures are provided.'));
        new Evaluator(Catalog::fromArray(['rich' => ['criterion' => ['measure' => ['name' => 'points', 'threshold' => 10, 'verbs' => ['a']]]]]), $this->progress);
    }

    public function testABadgeIsAwardedOnceItsTargetIsReached(): void
    {
        $journal = $this->journal([
            'commenter' => ['criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 2]]],
            'first' => ['criterion' => ['first' => ['verbs' => ['news.*']]]],
            'hand' => ['criterion' => ['manual' => true]],
        ]);

        $journal->record(Activity::of('c1', 'member:1', 'news.commented', $this->at('2026-11-01 10:00:00')));
        self::assertSame(['awarded first member:1 #1'], $this->observer->told);
        $journal->record(Activity::of('c2', 'member:1', 'news.commented', $this->at('2026-11-02 10:00:00')));
        $journal->record(Activity::of('c3', 'member:1', 'news.commented', $this->at('2026-11-03 10:00:00')));
        $journal->record(Activity::of('c4', 'member:2', 'news.commented', $this->at('2026-11-03 10:00:00')));

        self::assertSame(['awarded first member:1 #1', 'awarded commenter member:1 #1', 'awarded first member:2 #1'], $this->observer->told);
        self::assertSame([['first', 1, '2026-11-01T10:00:00+00:00', 'c1', false], ['commenter', 1, '2026-11-02T10:00:00+00:00', 'c2', false]], $this->awardsOf('1'));
        self::assertSame(3, $this->progress->count('commenter', 'r', ResourceReference::of('member', '1')));

        // An entry handed twice brings nothing more.
        $projection = $this->projection(['commenter' => ['criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 2]]]]);
        $projection->apply($this->log->read(2, 1, Filter::all())[0]);
        self::assertSame(3, $this->progress->count('commenter', 'r', ResourceReference::of('member', '1')));
        self::assertCount(2, $this->awardsOf('1'));
    }

    public function testARepeatableBadgeIsAwardedEachTimeItsTargetIsReachedAgain(): void
    {
        $journal = $this->journal(['reader' => ['repeatable' => true, 'criterion' => ['total' => ['verbs' => ['book.read'], 'amount' => 100]]]]);

        $journal->record(Activity::of('r1', 'member:1', 'book.read', $this->at('2026-11-01 10:00:00'), quantity: 90));
        $journal->record(Activity::of('r2', 'member:1', 'book.read', $this->at('2026-11-02 10:00:00'), quantity: 230));
        self::assertSame([['reader', 1, '2026-11-02T10:00:00+00:00', 'r2', false], ['reader', 2, '2026-11-02T10:00:00+00:00', 'r2', false], ['reader', 3, '2026-11-02T10:00:00+00:00', 'r2', false]], $this->awardsOf('1'));
        self::assertSame(['awarded reader member:1 #1', 'awarded reader member:1 #2', 'awarded reader member:1 #3'], $this->observer->told);

        // Not revocable: a cancellation keeps the awards.
        $journal->cancel('r2');
        self::assertCount(3, $this->awardsOf('1'));
        self::assertCount(3, $this->observer->told);
    }

    public function testOneActivityBringsABoundedNumberOfAwards(): void
    {
        $journal = $this->journal(['walker' => ['repeatable' => true, 'criterion' => ['total' => ['verbs' => ['walk.logged'], 'amount' => 10]]]]);

        // A quantity that would call for a hundred million awards brings the bound, ten.
        $journal->record(Activity::of('w1', 'member:1', 'walk.logged', $this->at('2026-11-01 10:00:00'), quantity: Activity::MAX_QUANTITY));
        self::assertCount(BadgeProjection::MAX_AWARDS_PER_ACTIVITY, $this->awardsOf('1'));
        self::assertSame(range(1, 10), array_column($this->awardsOf('1'), 1));
        self::assertCount(10, $this->observer->told);
        self::assertSame('awarded walker member:1 #10', $this->observer->told[9]);

        // The next activity brings ten more, numbered after them.
        $this->observer->forget();
        $journal->record(Activity::of('w2', 'member:1', 'walk.logged', $this->at('2026-11-02 10:00:00'), quantity: 1));
        self::assertSame(range(1, 20), array_column($this->awardsOf('1'), 1));
        self::assertSame(array_map(static fn (int $number): string => 'awarded walker member:1 #'.$number, range(11, 20)), $this->observer->told);
    }

    public function testTheBoundOfAwardsIsChosen(): void
    {
        $projection = new BadgeProjection(new Evaluator(Catalog::fromArray(['walker' => ['repeatable' => true, 'criterion' => ['total' => ['verbs' => ['walk.logged'], 'amount' => 10]]]]), $this->progress), $this->progress, $this->awards, $this->observer, maxAwardsPerActivity: 1);
        $journal = new Journal($this->log, new Clock(), observers: [new LiveProjections(new Projector($this->log, new InMemoryCheckpoints()), new Projections([$projection]))]);

        $journal->record(Activity::of('w1', 'member:1', 'walk.logged', $this->at('2026-11-01 10:00:00'), quantity: 30));
        self::assertSame(['awarded walker member:1 #1'], $this->observer->told);
    }

    public function testTheBoundOfAwardsIsAtLeastOne(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('An activity brings at least one award; 0 is not a bound.'));
        new BadgeProjection(new Evaluator(new Catalog(), $this->progress), $this->progress, $this->awards, maxAwardsPerActivity: 0);
    }

    public function testARevocableBadgeIsWithdrawnWhenItsActivityIsCancelled(): void
    {
        $journal = $this->journal([
            'reader' => ['repeatable' => true, 'revocable' => true, 'criterion' => ['total' => ['verbs' => ['book.read'], 'amount' => 100]]],
            'once' => ['revocable' => true, 'criterion' => ['first' => ['verbs' => ['book.read']]]],
            'gone' => ['criterion' => ['first' => ['verbs' => ['book.read']]]],
        ]);

        $journal->record(Activity::of('r1', 'member:1', 'book.read', $this->at('2026-11-01 10:00:00'), quantity: 120));
        $journal->record(Activity::of('r2', 'member:1', 'book.read', $this->at('2026-11-02 10:00:00'), quantity: 200));
        $journal->record(Activity::of('r3', 'member:2', 'book.read', $this->at('2026-11-02 10:00:00'), quantity: 100));

        $this->observer->forget();

        $journal->cancel('r2');
        self::assertSame(['revoked reader member:1 #2', 'revoked reader member:1 #3'], $this->observer->told);
        self::assertSame([['gone', 1, '2026-11-01T10:00:00+00:00', 'r1', false], ['once', 1, '2026-11-01T10:00:00+00:00', 'r1', false], ['reader', 1, '2026-11-01T10:00:00+00:00', 'r1', false]], $this->awardsOf('1'));

        $journal->cancel('r1');
        self::assertSame(['revoked reader member:1 #2', 'revoked reader member:1 #3', 'revoked reader member:1 #1', 'revoked once member:1 #1'], $this->observer->told);
        self::assertSame([['gone', 1, '2026-11-01T10:00:00+00:00', 'r1', false]], $this->awardsOf('1'));
        self::assertCount(3, $this->awardsOf('2'));

        // A cancellation of an activity no badge read changes nothing.
        $journal->record(Activity::of('x', 'member:1', 'news.read', $this->at('2026-11-02 10:00:00')));
        $journal->cancel('x');
        self::assertCount(4, $this->observer->told());
    }

    public function testAManualAwardStandsForANonRepeatableBadge(): void
    {
        $journal = $this->journal(['once' => ['revocable' => true, 'criterion' => ['first' => ['verbs' => ['book.read']]]]]);
        $this->awards->add(new Award('once', ResourceReference::of('member', '1'), 1, $this->at('2026-10-01 10:00:00'), 'gift', true));

        $journal->record(Activity::of('r1', 'member:1', 'book.read', $this->at('2026-11-01 10:00:00')));
        $journal->cancel('r1');
        self::assertSame([['once', 1, '2026-10-01T10:00:00+00:00', 'gift', true]], $this->awardsOf('1'));
        self::assertSame([], $this->observer->told);
    }

    public function testARepeatableBadgeNumbersAfterManualAwards(): void
    {
        $journal = $this->journal(['games' => ['repeatable' => true, 'criterion' => ['first' => ['verbs' => ['game.played']]]]]);
        $this->awards->add(new Award('games', ResourceReference::of('member', '1'), 1, $this->at('2026-10-01 10:00:00'), null, true));

        $journal->record(Activity::of('g1', 'member:1', 'game.played', $this->at('2026-11-01 10:00:00')));
        self::assertSame([['games', 1, '2026-10-01T10:00:00+00:00', null, true], ['games', 2, '2026-11-01T10:00:00+00:00', 'g1', false]], $this->awardsOf('1'));
    }

    public function testACancellationForABadgeNoLongerInTheCatalog(): void
    {
        $this->journal(['old' => ['criterion' => ['first' => ['verbs' => ['a']]]]])->record(Activity::of('k', 'member:1', 'a', $this->at('2026-11-01 10:00:00')));
        $this->log->cancel(IdempotencyKey::fromString('k'), $this->at('2026-11-02 10:00:00'));

        $projection = $this->projection(['new' => ['revocable' => true, 'criterion' => ['first' => ['verbs' => ['a']]]]]);
        $projection->apply($this->log->read(1, 1, Filter::all())[0]);
        self::assertSame(0, $this->progress->count('old', 'r', ResourceReference::of('member', '1')));
        self::assertSame([['old', 1, '2026-11-01T10:00:00+00:00', 'k', false]], $this->awardsOf('1'));
    }

    public function testAMeasureIsReadWhenItsActivityHappens(): void
    {
        $journal = $this->journal(['rich' => ['criterion' => ['measure' => ['name' => 'points', 'threshold' => 1000, 'verbs' => ['game.*']]]]]);

        $this->measures->set('points', 'member:1', 999);
        $journal->record(Activity::of('g1', 'member:1', 'game.played', $this->at('2026-11-01 10:00:00')));
        self::assertSame([], $this->awardsOf('1'));
        $this->measures->set('points', 'member:1', 1000);
        $journal->record(Activity::of('g2', 'member:1', 'game.played', $this->at('2026-11-02 10:00:00')));
        self::assertSame([['rich', 1, '2026-11-02T10:00:00+00:00', 'g2', false]], $this->awardsOf('1'));
    }

    public function testAMeasureIsNotReadOnAnotherActivity(): void
    {
        $journal = $this->journal([
            'rich' => ['criterion' => ['measure' => ['name' => 'points', 'threshold' => 10, 'verbs' => ['game.*']]]],
            'reader' => ['criterion' => ['first' => ['verbs' => ['news.read']]]],
        ]);

        $this->measures->set('points', 'member:1', 50);
        $journal->record(Activity::of('n1', 'member:1', 'news.read', $this->at('2026-11-01 10:00:00')));
        self::assertSame([['reader', 1, '2026-11-01T10:00:00+00:00', 'n1', false]], $this->awardsOf('1'));
    }

    public function testTheProjectionWorksWithoutAnObserver(): void
    {
        $projection = new BadgeProjection(new Evaluator(Catalog::fromArray(['once' => ['revocable' => true, 'criterion' => ['first' => ['verbs' => ['a']]]]]), $this->progress), $this->progress, $this->awards);
        $journal = new Journal($this->log, new Clock(), observers: [new LiveProjections(new Projector($this->log, new InMemoryCheckpoints()), new Projections([$projection]))]);

        $journal->record(Activity::of('k', 'member:1', 'a', $this->at('2026-11-01 10:00:00')));
        self::assertCount(1, $this->awardsOf('1'));
        $journal->cancel('k');
        self::assertSame([], $this->awardsOf('1'));
    }

    public function testARebuildAnnouncesTheAwardsThatAreNew(): void
    {
        $catalog = [
            'first' => ['criterion' => ['first' => ['verbs' => ['a']]]],
            'second' => ['repeatable' => true, 'criterion' => ['first' => ['verbs' => ['b']]]],
        ];
        $journal = $this->journal($catalog);
        $journal->record(Activity::of('a1', 'member:1', 'a', $this->at('2026-11-01 10:00:00')));
        $journal->record(Activity::of('b1', 'member:1', 'b', $this->at('2026-11-01 10:00:00')));
        $journal->record(Activity::of('b2', 'member:1', 'b', $this->at('2026-11-02 10:00:00')));
        $journal->record(Activity::of('a2', 'member:2', 'a', $this->at('2026-11-02 10:00:00')));

        $this->observer->forget();

        // Under the new catalog, `a` earns the second badge and `b` the first: member 1 earns again the badges and
        // numbers they held, and only member 2's second badge is new.
        $projection = $this->projection([
            'first' => ['criterion' => ['first' => ['verbs' => ['b']]]],
            'second' => ['repeatable' => true, 'criterion' => ['first' => ['verbs' => ['a']]]],
        ]);
        new Projector($this->log, new InMemoryCheckpoints())->rebuild($projection);
        self::assertSame(['awarded second member:2 #1'], $this->observer->told);
    }

    public function testARebuildReplaysTheJournalWithoutAnnouncingAgain(): void
    {
        $catalog = ['commenter' => ['criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 2]]], 'hand' => ['criterion' => ['manual' => true]]];
        $journal = $this->journal($catalog);
        $this->awards->add(new Award('hand', ResourceReference::of('member', '1'), 1, $this->at('2026-11-05 10:00:00'), null, true));

        foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'] as $index => $day) {
            $journal->record(Activity::of('c'.$index, 'member:1', 'news.commented', $this->at($day.' 10:00:00')));
        }

        self::assertSame(['awarded commenter member:1 #1'], $this->observer->told);

        // The new rule needs three comments: the award moves to the third, and is not announced again.
        $catalog['commenter']['criterion']['count']['times'] = 3;
        $projection = $this->projection($catalog);
        new Projector($this->log, new InMemoryCheckpoints())->rebuild($projection);
        self::assertSame([['commenter', 1, '2026-11-03T10:00:00+00:00', 'c2', false], ['hand', 1, '2026-11-05T10:00:00+00:00', null, true]], $this->awardsOf('1'));
        self::assertSame(['awarded commenter member:1 #1'], $this->observer->told);
        self::assertSame(4, $this->progress->count('commenter', 'r', ResourceReference::of('member', '1')));

        // A rebuild from a moment keeps the contributions before it; only the award that is new is announced.
        $catalog['commenter']['repeatable'] = true;
        $catalog['commenter']['criterion']['count']['times'] = 2;
        $projection = $this->projection($catalog);
        new Projector($this->log, new InMemoryCheckpoints())->rebuild($projection, $this->at('2026-11-03 10:00:00'));
        self::assertSame([['commenter', 1, '2026-11-03T10:00:00+00:00', 'c2', false], ['commenter', 2, '2026-11-04T10:00:00+00:00', 'c3', false], ['hand', 1, '2026-11-05T10:00:00+00:00', null, true]], $this->awardsOf('1'));
        self::assertSame(['awarded commenter member:1 #1', 'awarded commenter member:1 #2'], $this->observer->told);
    }

    public function testClearingTheReplayAnnouncesAnAwardEarnedAfterIt(): void
    {
        $journal = $this->journal(['first' => ['criterion' => ['first' => ['verbs' => ['a']]]]]);
        $journal->record(Activity::of('a1', 'member:1', 'a', $this->at('2026-11-01 10:00:00')));

        $this->observer->forget();

        // Under the new catalog, `a` no longer earns the badge: the rebuild forgets the award.
        $projection = $this->projection(['first' => ['criterion' => ['first' => ['verbs' => ['b']]]]]);
        $projector = new Projector($this->log, new InMemoryCheckpoints());
        $projector->rebuild($projection);
        self::assertSame([], $this->awardsOf('1'));

        $projection->clearReplay();
        self::assertEquals($this->projection(['first' => ['criterion' => ['first' => ['verbs' => ['b']]]]]), $projection);

        $this->log->append(Activity::of('b1', 'member:1', 'b', $this->at('2026-11-02 10:00:00')), $this->at('2026-11-02 10:00:00'));
        $projector->catchUp($projection);
        self::assertSame(['awarded first member:1 #1'], $this->observer->told);
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     */
    private function projection(array $catalog): BadgeProjection
    {
        return new BadgeProjection(new Evaluator(Catalog::fromArray($catalog), $this->progress, $this->measures), $this->progress, $this->awards, $this->observer);
    }

    /**
     * @param array<string, array<string, mixed>> $catalog
     */
    private function journal(array $catalog): Journal
    {
        $projector = new Projector($this->log, new InMemoryCheckpoints());

        return new Journal($this->log, new Clock(), observers: [new LiveProjections($projector, new Projections([$this->projection($catalog)]))]);
    }

    /**
     * @return list<array{string, int, string, ?string, bool}>
     */
    private function awardsOf(string $member): array
    {
        return array_map(
            static fn (Award $award): array => [$award->badge, $award->number, $award->awardedAt->format(DateTimeInterface::ATOM), $award->cause, $award->manual],
            $this->awards->of(ResourceReference::of('member', $member)),
        );
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
