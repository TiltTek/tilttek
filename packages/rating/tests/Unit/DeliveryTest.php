<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Event\PeakReached;
use Tilttek\Rating\Event\RatingChanged;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\Ratings;
use Tilttek\Rating\Store\InMemoryRatingStore;
use Tilttek\Rating\Tests\Unit\Fixture\FailingDispatcher;
use Tilttek\Rating\Tests\Unit\Fixture\ListSource;

/**
 * The events of a rated result are kept with it until a listener took each one without an exception: a failure is
 * made up by recording the result again, or by `deliver()`, without rating it again.
 */
#[CoversClass(Ratings::class)]
#[CoversClass(InMemoryRatingStore::class)]
#[CoversClass(RatingChanged::class)]
#[CoversClass(PeakReached::class)]
final class DeliveryTest extends TestCase
{
    private InMemoryRatingStore $store;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryRatingStore();
    }

    public function testAFailedListenerIsMadeUpByRecordingTheResultAgain(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof RatingChanged && 'ann' === $event->member);
        $ratings = new Ratings($this->store, new EloSystem(), $events, celebrateAfter: 1);
        new Ratings($this->store, new EloSystem())->record(GameResult::duel('r0', 'chess', $this->at(1), 'ann', 'bob'));
        $duel = GameResult::duel('r1', 'chess', $this->at(2), 'ann', 'bob');

        try {
            $ratings->record($duel);
            self::fail('The failure of a listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed, time 1.', $runtimeException->getMessage());
        }

        self::assertEquals([new PeakReached('chess', 'ann', 'r1'), new RatingChanged('chess', 'bob', 'r1')], $events->delivered);
        self::assertEquals([new RatingChanged('chess', 'ann', 'r1')], $this->store->undelivered('chess', 'r1'));

        self::assertSame([], $ratings->record($duel));
        self::assertEquals(new RatingChanged('chess', 'ann', 'r1'), $events->delivered[2]);
        self::assertCount(3, $events->delivered);
        self::assertSame([], $this->store->undelivered('chess', 'r1'));
        self::assertSame(2, $ratings->rating('chess', 'ann')->games);
        self::assertSame('r1', $ratings->rating('chess', 'ann')->lastResult);

        $this->expectException(ResultAlreadyRecorded::class);
        $ratings->record($duel);
    }

    public function testAReplayCancelsTheEventsLeftUndelivered(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => true, 10);
        $first = GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob');
        $ratings = new Ratings($this->store, new EloSystem(), $events, new ListSource([GameResult::duel('r1', 'chess', $this->at(1), 'bob', 'ann')]), celebrateAfter: 1);

        try {
            $ratings->record($first);
            self::fail('The failure of a listener was hidden.');
        } catch (RuntimeException) {
        }

        $this->store->save([new MemberRating('go', 'ann', 'elo', new EloRating(1), 1)], 'g1', [new RatingChanged('go', 'ann', 'g1')]);
        self::assertCount(2, $this->store->undelivered('chess', 'r1'));

        // The replay rates the result the other way round: the events of the result it replaced are stale, and go.
        self::assertSame(1, $ratings->replay('chess'));
        self::assertSame([], $this->store->undelivered('chess', 'r1'));
        self::assertSame(0, $ratings->deliver('chess', 'r1'));
        self::assertSame([], $events->delivered);
        self::assertEquals([new RatingChanged('go', 'ann', 'g1')], $this->store->undelivered('go', 'g1'));
        self::assertGreaterThan($ratings->rating('chess', 'ann')->score, $ratings->rating('chess', 'bob')->score);
    }

    public function testDeliverResumesAndSaysHowManyWent(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => true, 3);
        $ratings = new Ratings($this->store, new EloSystem(), $events, celebrateAfter: 1);
        new Ratings($this->store, new EloSystem())->record(GameResult::duel('r0', 'chess', $this->at(1), 'ann', 'bob'));

        try {
            $ratings->record(GameResult::duel('r1', 'chess', $this->at(2), 'ann', 'bob'));
            self::fail('The failure of a listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed, time 1.', $runtimeException->getMessage());
        }

        self::assertCount(0, $events->delivered);
        self::assertEquals([
            new RatingChanged('chess', 'ann', 'r1'),
            new PeakReached('chess', 'ann', 'r1'),
            new RatingChanged('chess', 'bob', 'r1'),
        ], $this->store->undelivered('chess', 'r1'));
        self::assertSame(3, $ratings->deliver('chess', 'r1'));
        self::assertSame(0, $ratings->deliver('chess', 'r1'));
        self::assertSame(0, $ratings->deliver('chess', 'unknown'));
        self::assertCount(3, $events->delivered);
    }

    public function testWithoutADispatcherNothingIsDelivered(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => true, 2);

        try {
            new Ratings($this->store, new EloSystem(), $events)->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        } catch (RuntimeException) {
        }

        self::assertSame(0, new Ratings($this->store, new EloSystem())->deliver('chess', 'r1'));
        self::assertCount(2, $this->store->undelivered('chess', 'r1'));
    }

    public function testWithoutADispatcherNothingIsKeptToDeliver(): void
    {
        $ratings = new Ratings($this->store, new EloSystem());
        $duel = GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob');
        $ratings->record($duel);

        self::assertSame([], $this->store->undelivered('chess', 'r1'));
        self::assertSame(0, $ratings->deliver('chess', 'r1'));
        $this->expectException(ResultAlreadyRecorded::class);
        $ratings->record($duel);
    }

    public function testAMemberForgottenTakesTheirEventsLeftAlong(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => true, 2);
        $ratings = new Ratings($this->store, new EloSystem(), $events);

        try {
            $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        } catch (RuntimeException) {
        }

        $this->store->forget('ann');

        self::assertEquals([new RatingChanged('chess', 'bob', 'r1')], $this->store->undelivered('chess', 'r1'));
    }

    public function testEveryEventHasAStableKeyOfItsOwn(): void
    {
        self::assertSame('chess/r1/ann/changed', new RatingChanged('chess', 'ann', 'r1')->key());
        self::assertSame('chess/r1/ann/peak', new PeakReached('chess', 'ann', 'r1')->key());
    }

    private function at(int $hour): DateTimeImmutable
    {
        return new DateTimeImmutable(\sprintf('2026-11-13 %02d:00:00 UTC', $hour));
    }
}
