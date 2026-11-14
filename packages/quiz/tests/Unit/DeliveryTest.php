<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Contract\LiveSessionStore;
use Tilttek\Quiz\Event\GuestInvited;
use Tilttek\Quiz\Event\LiveQuizFinished;
use Tilttek\Quiz\Event\PerfectScore;
use Tilttek\Quiz\Event\QuizCompleted;
use Tilttek\Quiz\Event\TeamWon;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Live\LiveState;
use Tilttek\Quiz\LiveQuizzes;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Scoring;
use Tilttek\Quiz\Store\InMemoryLiveSessionStore;
use Tilttek\Quiz\Store\InMemoryQuizCatalog;
use Tilttek\Quiz\Tests\Unit\Fixture\ConflictingSessionStore;
use Tilttek\Quiz\Tests\Unit\Fixture\FailingDispatcher;
use Tilttek\Quiz\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;

/**
 * The events of the end of a live session are kept in the session until a listener took each one without an
 * exception: a failure is made up by finishing again, or by `deliver()`.
 */
#[CoversClass(LiveQuizzes::class)]
#[CoversClass(LiveSession::class)]
#[CoversClass(LiveQuizFinished::class)]
#[CoversClass(QuizCompleted::class)]
#[CoversClass(PerfectScore::class)]
#[CoversClass(TeamWon::class)]
#[CoversClass(GuestInvited::class)]
final class DeliveryTest extends TestCase
{
    private InMemoryLiveSessionStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryLiveSessionStore();
    }

    public function testAFailedListenerIsMadeUpByFinishingAgain(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof QuizCompleted && 'member:1' === $event->player);
        $live = $this->played($events);

        try {
            $live->finish('night-1');
            self::fail('The failure of a listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed, time 1.', $runtimeException->getMessage());
        }

        self::assertSame(LiveState::Finished, $live->session('night-1')->state());
        self::assertEquals([
            new LiveQuizFinished('trivia', 'night-1'),
            new PerfectScore('trivia', 'night-1', 'member:1'),
            new QuizCompleted('trivia', 'night-1', 'guest-1', false),
            new GuestInvited('night-1', 'guest-1'),
        ], $events->delivered);

        $finished = $live->finish('night-1');

        self::assertSame([], $finished->undelivered());
        self::assertSame([], $live->session('night-1')->undelivered());
        self::assertEquals(new QuizCompleted('trivia', 'night-1', 'member:1', true), $events->delivered[4]);
        self::assertCount(5, $events->delivered);

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The live session night-1 is over already.');
        $live->finish('night-1');
    }

    public function testDeliverResumesAndSaysHowManyWent(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof QuizCompleted || $event instanceof PerfectScore, 2);
        $live = $this->played($events);

        try {
            $live->finish('night-1');
            self::fail('The failure of a listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed, time 1.', $runtimeException->getMessage());
        }

        $held = $live->session('night-1')->undelivered();
        self::assertEquals([
            new QuizCompleted('trivia', 'night-1', 'member:1', true),
            new PerfectScore('trivia', 'night-1', 'member:1'),
        ], $held);

        self::assertSame(2, $live->deliver('night-1'));
        self::assertSame(0, $live->deliver('night-1'));
        self::assertCount(5, $events->delivered);
    }

    public function testDeliverSaysAFailedListenerAndKeepsItsEvent(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof GuestInvited, 2);
        $live = $this->played($events);

        try {
            $live->finish('night-1');
        } catch (RuntimeException) {
        }

        try {
            $live->deliver('night-1');
            self::fail('The failure of a listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed, time 2.', $runtimeException->getMessage());
        }

        self::assertEquals([new GuestInvited('night-1', 'guest-1')], $live->session('night-1')->undelivered());
        self::assertSame(1, $live->deliver('night-1'));
    }

    public function testEveryEndEventHasAStableKeyOfItsOwn(): void
    {
        $keys = [
            new LiveQuizFinished('trivia', 'night-1')->key(),
            new QuizCompleted('trivia', 'night-1', 'red', true)->key(),
            new PerfectScore('trivia', 'night-1', 'red')->key(),
            new TeamWon('trivia', 'night-1', 'red')->key(),
            new GuestInvited('night-1', 'red')->key(),
        ];

        self::assertSame([
            'night-1/finished',
            'trivia/night-1/red/completed',
            'trivia/night-1/red/perfect',
            'night-1/team/red/won',
            'night-1/red/invited',
        ], $keys);
        self::assertSame($keys[1], new QuizCompleted('trivia', 'night-1', 'red', false)->key());
    }

    public function testAnEndWithoutFailureLeavesNothingToDeliver(): void
    {
        $events = new FailingDispatcher(static fn (object $event): bool => false, 0);
        $live = $this->played($events);
        $live->finish('night-1');

        self::assertCount(5, $events->delivered);
        self::assertSame(0, $live->deliver('night-1'));
    }

    public function testAnEndThatMeetsAConflictIsWrittenOnceWithItsEvents(): void
    {
        $store = new ConflictingSessionStore($this->store);
        $events = new FailingDispatcher(static fn (object $event): bool => false, 0);
        $live = $this->played($events, $store);
        $store->conflicts = 1;

        $live->finish('night-1');

        self::assertCount(5, $events->delivered);
        self::assertSame([], $live->session('night-1')->undelivered());
        self::assertSame(11, $live->session('night-1')->version());
    }

    private function played(FailingDispatcher $events, ?LiveSessionStore $store = null): LiveQuizzes
    {
        $clock = new FrozenClock();
        $live = new LiveQuizzes(new InMemoryQuizCatalog(Quizzes::trivia(Scoring::accuracy())), $store ?? $this->store, $events, $clock);
        $session = $live->open('night-1', 'trivia');
        $live->join($session->pin, Player::member('member:1'));
        $live->join($session->pin, Player::guest('guest-1', 'Pixel'));

        $events->delivered = [];
        $live->next('night-1');
        $live->answer('night-1', 'member:1', ChoiceAnswer::of('b'));
        $live->close('night-1');
        $live->next('night-1');
        $live->answer('night-1', 'member:1', new NumberAnswer(1974));
        $live->close('night-1');

        $events->delivered = [];

        return $live;
    }
}
