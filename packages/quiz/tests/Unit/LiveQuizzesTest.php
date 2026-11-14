<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine;
use Random\Engine\Mt19937;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Contract\PseudonymFilter;
use Tilttek\Quiz\Event\GuestInvited;
use Tilttek\Quiz\Event\LiveQuizFinished;
use Tilttek\Quiz\Event\PerfectScore;
use Tilttek\Quiz\Event\PlayerJoined;
use Tilttek\Quiz\Event\QuestionClosed;
use Tilttek\Quiz\Event\QuestionShown;
use Tilttek\Quiz\Event\QuizCompleted;
use Tilttek\Quiz\Event\TeamWon;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Live\LiveState;
use Tilttek\Quiz\LiveQuizzes;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Scoring;
use Tilttek\Quiz\Store\InMemoryLiveSessionStore;
use Tilttek\Quiz\Store\InMemoryQuizCatalog;
use Tilttek\Quiz\Team\TeamScoring;
use Tilttek\Quiz\Tests\Unit\Fixture\ConflictingSessionStore;
use Tilttek\Quiz\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;
use Tilttek\Quiz\Tests\Unit\Fixture\RecordingDispatcher;

#[CoversClass(LiveQuizzes::class)]
#[CoversClass(InMemoryLiveSessionStore::class)]
#[CoversClass(LiveQuizFinished::class)]
#[CoversClass(GuestInvited::class)]
#[CoversClass(PlayerJoined::class)]
#[CoversClass(QuestionShown::class)]
#[CoversClass(QuestionClosed::class)]
#[CoversClass(TeamWon::class)]
final class LiveQuizzesTest extends TestCase
{
    private FrozenClock $clock;

    private RecordingDispatcher $events;

    private ConflictingSessionStore $store;

    private LiveQuizzes $live;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->events = new RecordingDispatcher();
        $this->store = new ConflictingSessionStore();
        $this->live = $this->live();
    }

    public function testAHostRunsASessionAndItsEndIsSaid(): void
    {
        $session = $this->live->open('night-1', 'trivia');

        self::assertMatchesRegularExpression('/^\d{6}$/', $session->pin);
        self::assertSame($session->pin, $this->live->session('night-1')->pin);

        $this->live->join($session->pin, Player::member('member:1'));
        $this->live->join($session->pin, Player::guest('guest-1', 'Pixel'));
        $this->live->join($session->pin, Player::guest('guest-2', 'Elf'));

        $timer = $this->live->next('night-1');
        self::assertSame(20, $timer->limit);
        $this->clock->move('+2 seconds');
        self::assertSame(100, $this->live->answer('night-1', 'member:1', ChoiceAnswer::of('b'))->total());
        $this->live->answer('night-1', 'guest-1', ChoiceAnswer::of('a'));
        $closed = $this->live->close('night-1');
        self::assertSame(LiveState::Review, $closed->state());
        self::assertSame(LiveState::Review, $this->live->close('night-1')->state());

        $this->live->next('night-1');
        $this->live->answer('night-1', 'member:1', new NumberAnswer(1974));
        $this->live->close('night-1');

        $this->events->events = [];

        $finished = $this->live->finish('night-1');

        self::assertSame(LiveState::Finished, $finished->state());
        self::assertEquals([
            new LiveQuizFinished('trivia', 'night-1'),
            new QuizCompleted('trivia', 'night-1', 'member:1', true),
            new PerfectScore('trivia', 'night-1', 'member:1'),
            new QuizCompleted('trivia', 'night-1', 'guest-1', false),
            new QuizCompleted('trivia', 'night-1', 'guest-2', false),
            new GuestInvited('night-1', 'guest-1'),
            new GuestInvited('night-1', 'guest-2'),
        ], $this->events->events);
        self::assertSame(LiveState::Finished, $this->live->session('night-1')->state());

        $claimed = $this->live->claimGuest('night-1', 'guest-1', Player::member('member:9'));
        self::assertArrayHasKey('member:9', $claimed->players());
        self::assertArrayHasKey('member:9', $this->live->session('night-1')->players());
    }

    public function testEachMoveOfASessionIsSaidOnceSaved(): void
    {
        $session = $this->live->open('night-1', 'trivia');
        $this->live->join($session->pin, Player::member('member:1'));
        $this->live->join(substr($session->pin, 0, 3).' '.substr($session->pin, 3), Player::guest('guest-1', 'Pixel'));
        $this->live->join($session->pin, Player::member('member:1'));
        $this->live->next('night-1');
        $this->live->close('night-1');
        $this->live->close('night-1');
        $this->live->next('night-1');
        $this->live->close('night-1');

        self::assertEquals([
            new PlayerJoined('night-1', 'member:1'),
            new PlayerJoined('night-1', 'guest-1'),
            new QuestionShown('trivia', 'night-1', 0),
            new QuestionClosed('trivia', 'night-1', 0),
            new QuestionShown('trivia', 'night-1', 1),
            new QuestionClosed('trivia', 'night-1', 1),
        ], $this->events->events);
    }

    public function testAMalformedPinIsRefusedBeforeTheStoreIsAsked(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('A PIN is six digits, which spaces or hyphens may split.');
        $this->live->join('12345', Player::member('member:1'));
    }

    public function testASessionPlayedByTeamsSaysItsWinningTeams(): void
    {
        $session = $this->live->open('night-1', 'trivia', ['red', 'blue', 'green'], TeamScoring::Average);

        self::assertSame(['red', 'blue', 'green'], $session->teams());
        self::assertSame(TeamScoring::Average, $this->live->session('night-1')->teamScoring);

        $this->live->join($session->pin, Player::member('m1'), 'red');
        $this->live->join($session->pin, Player::member('m2'), 'blue');
        $this->live->join($session->pin, Player::member('m3'));
        self::assertSame('green', $this->live->session('night-1')->teamOf('m3'));

        $this->live->next('night-1');
        $this->live->answer('night-1', 'm1', ChoiceAnswer::of('b'));
        $this->live->answer('night-1', 'm2', ChoiceAnswer::of('b'));
        $this->live->close('night-1');

        $this->events->events = [];

        $this->live->finish('night-1');

        self::assertEquals([new TeamWon('trivia', 'night-1', 'blue'), new TeamWon('trivia', 'night-1', 'red')], $this->teamWins());
    }

    public function testNoTeamWinsWithoutAPoint(): void
    {
        $session = $this->live->open('night-1', 'trivia', ['red', 'blue']);
        $this->live->join($session->pin, Player::member('m1'));
        $this->live->finish('night-1');

        self::assertSame([], $this->teamWins());
    }

    public function testASessionOfPlayersSaysNoTeam(): void
    {
        $session = $this->live->open('night-1', 'trivia');
        $this->live->join($session->pin, Player::member('m1'));
        $this->live->next('night-1');
        $this->live->answer('night-1', 'm1', ChoiceAnswer::of('b'));
        $this->live->close('night-1');
        $this->live->finish('night-1');

        self::assertSame([], $this->teamWins());
    }

    public function testAConflictIsReadAgainAndMadeAgain(): void
    {
        $session = $this->live->open('night-1', 'trivia');
        $this->store->conflicts = LiveQuizzes::ATTEMPTS - 1;

        $this->live->join($session->pin, Player::member('member:1'));

        self::assertSame(LiveQuizzes::ATTEMPTS, $this->store->saves);
        self::assertArrayHasKey('member:1', $this->live->session('night-1')->players());
    }

    public function testTooManyConflictsAreLetThrough(): void
    {
        $session = $this->live->open('night-1', 'trivia');
        $this->store->conflicts = LiveQuizzes::ATTEMPTS;

        try {
            $this->live->join($session->pin, Player::member('member:1'));
            self::fail('Endless conflicts were hidden.');
        } catch (QuizConflict) {
            self::assertSame(LiveQuizzes::ATTEMPTS, $this->store->saves);
        }
    }

    public function testAChangeThatChangesNothingIsNotSaved(): void
    {
        $session = $this->live->open('night-1', 'trivia');
        $this->live->join($session->pin, Player::member('member:1'));
        $saves = $this->store->saves;

        $this->live->join($session->pin, Player::member('member:1'));

        self::assertSame($saves, $this->store->saves);
    }

    public function testAPseudonymMustPassTheFilter(): void
    {
        $filter = new class implements PseudonymFilter {
            #[Override]
            public function allows(string $pseudonym): bool
            {
                return 'Rude' !== $pseudonym;
            }
        };
        $live = new LiveQuizzes(new InMemoryQuizCatalog(Quizzes::trivia()), $this->store, $this->events, $this->clock, $filter);
        $session = $live->open('night-1', 'trivia');
        $live->join($session->pin, Player::guest('guest-1', 'Polite'));
        $live->join($session->pin, Player::member('member:1'));

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('This pseudonym is not allowed; choose another.');
        $live->join($session->pin, Player::guest('guest-2', 'Rude'));
    }

    public function testAPinIsDrawnAgainWhileTaken(): void
    {
        $first = $this->live(new Randomizer(new Xoshiro256StarStar(7)))->open('one', 'trivia');
        $second = $this->live(new Randomizer(new Xoshiro256StarStar(7)))->open('two', 'trivia');

        self::assertNotSame($first->pin, $second->pin);
    }

    /**
     * Two hosts open a session at once and draw the same PIN: the PIN goes to the first session kept, and the other
     * session draws another, rather than both answering to one PIN.
     */
    public function testTwoSessionsOpenedAtOnceNeverShareAPin(): void
    {
        $other = $this->live(new Randomizer(new Xoshiro256StarStar(7)));
        $opened = [];
        $this->store->meanwhile = static function () use ($other, &$opened): void {
            $opened[] = $other->open('two', 'trivia');
        };

        $first = $this->live(new Randomizer(new Xoshiro256StarStar(7)))->open('one', 'trivia');

        self::assertCount(1, $opened);
        self::assertNotSame($opened[0]->pin, $first->pin);
        self::assertSame('two', $this->store->findByPin($opened[0]->pin)?->id);
        self::assertSame('one', $this->store->findByPin($first->pin)?->id);
    }

    public function testAPinIsSixDigitsWithLeadingZeros(): void
    {
        $randomizer = new Randomizer(new class implements Engine {
            #[Override]
            public function generate(): string
            {
                return "\x00\x00\x00\x00\x00\x00\x00\x00";
            }
        });

        self::assertSame('000000', $this->live($randomizer)->open('one', 'trivia')->pin);
    }

    public function testNoFreePinIsReported(): void
    {
        $randomizer = new Randomizer(new class implements Engine {
            #[Override]
            public function generate(): string
            {
                return "\x00\x00\x00\x00\x00\x00\x00\x00";
            }
        });
        $live = $this->live($randomizer);
        $live->open('one', 'trivia');

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('No free PIN was found for a new live session; try again.');
        $live->open('two', 'trivia');
    }

    public function testAPinOfAFinishedSessionIsFreeAgain(): void
    {
        $randomizer = new Randomizer(new Mt19937(1));
        $live = $this->live($randomizer);
        $first = $live->open('one', 'trivia');
        $live->finish('one');

        self::assertNull($this->store->findByPin($first->pin));
        self::assertSame('one', $this->store->inner->find('one')?->id);
        self::assertNull($this->store->find('nope'));
    }

    public function testUnknownThingsAreRefused(): void
    {
        foreach ([
            'No live session that is not over has this PIN.' => fn (): mixed => $this->live->join('123456', Player::member('m')),
            'No live session has the identifier nope.' => fn (): mixed => $this->live->next('nope'),
            'No quiz has the identifier nope.' => fn (): mixed => $this->live->open('s', 'nope'),
        ] as $message => $call) {
            try {
                $call();
                self::fail('Something unknown was accepted.');
            } catch (QuizRefused $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testTheStoreRefusesTwiceTheSameIdentifierAndStaleVersions(): void
    {
        $this->live->open('night-1', 'trivia');

        try {
            $this->live->open('night-1', 'trivia');
            self::fail('Two sessions had the same identifier.');
        } catch (QuizConflict $quizConflict) {
            self::assertSame('The live session night-1 is at version 1, not 0.', $quizConflict->getMessage());
        }

        $stale = $this->store->inner->find('night-1');
        self::assertNotNull($stale);
        $stale->join(Player::member('m'));
        $stale->join(Player::member('n'));

        $this->expectException(QuizConflict::class);
        $this->expectExceptionMessageIsOrContains('The live session night-1 is at version 1, not 2.');
        $this->store->inner->save($stale);
    }

    /**
     * @return list<TeamWon>
     */
    private function teamWins(): array
    {
        return array_values(array_filter($this->events->events, static fn (object $event): bool => $event instanceof TeamWon));
    }

    private function live(?Randomizer $randomizer = null): LiveQuizzes
    {
        return new LiveQuizzes(new InMemoryQuizCatalog(Quizzes::trivia(Scoring::accuracy())), $this->store, $this->events, $this->clock, null, $randomizer ?? new Randomizer());
    }

    public function testAPinIsDrawnSoManyTimesBeforeGivingUp(): void
    {
        $live = $this->live(new Randomizer(new class implements Engine {
            #[Override]
            public function generate(): string
            {
                return "\x00\x00\x00\x00\x00\x00\x00\x00";
            }
        }));
        $live->open('one', 'trivia');

        $this->store->adds = 0;

        try {
            $live->open('two', 'trivia');
            self::fail('A taken PIN was given twice.');
        } catch (QuizRefused) {
            self::assertSame(LiveQuizzes::PIN_DRAWS, $this->store->adds);
            self::assertSame(0, $this->store->pinReads);
            self::assertNull($this->store->find('two'));
        }
    }

    public function testAPinIsDrawnFromTheMillionPinsOfSixDigits(): void
    {
        $randomizer = new Randomizer(new class implements Engine {
            #[Override]
            public function generate(): string
            {
                return pack('P', 1_999_999);
            }
        });

        self::assertSame('999999', $this->live($randomizer)->open('one', 'trivia')->pin);
    }

    public function testOnlyTheFirstTeamsWin(): void
    {
        $session = $this->live->open('night-1', 'trivia', ['red', 'blue']);
        $this->live->join($session->pin, Player::member('m1'), 'red');
        $this->live->join($session->pin, Player::member('m2'), 'blue');
        $this->live->next('night-1');
        $this->live->answer('night-1', 'm1', ChoiceAnswer::of('b'));
        $this->live->close('night-1');
        $this->live->next('night-1');
        $this->live->answer('night-1', 'm1', new NumberAnswer(1974));
        $this->live->answer('night-1', 'm2', new NumberAnswer(1974));
        $this->live->close('night-1');
        $this->live->finish('night-1');

        self::assertEquals([new TeamWon('trivia', 'night-1', 'red')], $this->teamWins());
    }
}
