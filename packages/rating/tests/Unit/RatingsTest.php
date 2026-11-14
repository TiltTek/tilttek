<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\Elo\EloRating;
use Tilttek\Rating\Elo\EloSystem;
use Tilttek\Rating\Event\PeakReached;
use Tilttek\Rating\Event\RatingChanged;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\Exception\RatingConflict;
use Tilttek\Rating\Exception\RatingRefused;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\OpenSkill\OpenSkillModel;
use Tilttek\Rating\OpenSkill\OpenSkillSystem;
use Tilttek\Rating\OpenSkill\SkillRating;
use Tilttek\Rating\RatingChange;
use Tilttek\Rating\Ratings;
use Tilttek\Rating\Store\InMemoryRatingStore;
use Tilttek\Rating\Tests\Unit\Fixture\ForgetfulStore;
use Tilttek\Rating\Tests\Unit\Fixture\ListSource;
use Tilttek\Rating\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Rating\Tests\Unit\Fixture\ReplaceSpy;
use Tilttek\Rating\Tests\Unit\Fixture\SpyStore;
use Tilttek\Rating\Tests\Unit\Fixture\StaleStore;
use Tilttek\Rating\Visibility;

#[CoversClass(Ratings::class)]
#[CoversClass(RatingChange::class)]
#[CoversClass(RatingChanged::class)]
#[CoversClass(PeakReached::class)]
#[CoversClass(RatingRefused::class)]
#[CoversClass(ResultAlreadyRecorded::class)]
final class RatingsTest extends TestCase
{
    private InMemoryRatingStore $store;

    private RecordingDispatcher $events;

    private ListSource $source;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryRatingStore();
        $this->events = new RecordingDispatcher();
        $this->source = new ListSource();
    }

    public function testARatedResultMovesAndKeepsTheRatingsOfItsMembers(): void
    {
        $ratings = $this->ratings();
        $changes = $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));

        self::assertEquals([new RatingChange('ann', 150_000, 151_600, false), new RatingChange('bob', 150_000, 148_400, false)], $changes);
        self::assertSame(1_600, $changes[0]->delta());
        self::assertSame(-1_600, $changes[1]->delta());

        $ann = $ratings->rating('chess', 'ann');
        self::assertEquals(new EloRating(151_600), $ann->rating);
        self::assertSame(1, $ann->games);
        self::assertSame('r1', $ann->lastResult);
        self::assertSame(1, $ann->version);
        self::assertSame(Visibility::Private, $ann->visibility);
        self::assertEquals([new RatingChanged('chess', 'ann', 'r1'), new RatingChanged('chess', 'bob', 'r1')], $this->events->events);
    }

    public function testAFriendlyResultChangesNothing(): void
    {
        $ratings = $this->ratings();

        self::assertSame([], $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob', friendly: true)));
        self::assertSame([], $this->store->ofGame('chess'));
        self::assertSame([], $this->events->events);
    }

    public function testANewHighestScoreIsCelebratedAfterTheGamesAskedFor(): void
    {
        $ratings = $this->ratings(celebrateAfter: 3);

        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $ratings->record(GameResult::duel('r2', 'chess', $this->at(2), 'ann', 'bob'));

        $changes = $ratings->record(GameResult::duel('r3', 'chess', $this->at(3), 'ann', 'bob'));

        self::assertTrue($changes[0]->peak);
        self::assertFalse($changes[1]->peak);
        $peaks = array_values(array_filter($this->events->events, static fn (object $event): bool => $event instanceof PeakReached));
        self::assertEquals([new PeakReached('chess', 'ann', 'r3')], $peaks);
        self::assertSame([RatingChanged::class, PeakReached::class, RatingChanged::class], array_map(get_class(...), \array_slice($this->events->events, 4)));
    }

    public function testASecondGameIsAPeakWhenOneIsEnough(): void
    {
        $ratings = $this->ratings(celebrateAfter: 1);
        $first = $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $second = $ratings->record(GameResult::duel('r2', 'chess', $this->at(2), 'ann', 'bob'));

        self::assertFalse($first[0]->peak);
        self::assertTrue($second[0]->peak);
    }

    public function testARatingIsPublicOnlyByTheMembersChoice(): void
    {
        $ratings = $this->ratings();
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));

        self::assertSame([], $ratings->standings('chess'));

        $public = $ratings->publish('chess', 'bob');
        self::assertSame(Visibility::Public, $public->visibility);
        self::assertSame(2, $public->version);
        self::assertSame($public->version, $ratings->rating('chess', 'bob')->version);
        self::assertSame(['bob'], array_map(static fn (MemberRating $rating): string => $rating->member, $ratings->standings('chess')));
        self::assertSame($public->version, $ratings->publish('chess', 'bob')->version);

        $hidden = $ratings->hide('chess', 'bob');
        self::assertSame(Visibility::Private, $hidden->visibility);
        self::assertSame(3, $hidden->version);
        self::assertSame([], $ratings->standings('chess'));
        self::assertSame(Visibility::Private, $ratings->hide('chess', 'cy')->visibility);
        self::assertSame([], $this->store->find('chess', ['cy']));
    }

    public function testAPeakIsReachedWithoutAnyoneToHearIt(): void
    {
        $ratings = new Ratings($this->store, new EloSystem(), celebrateAfter: 1);
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));

        self::assertTrue($ratings->record(GameResult::duel('r2', 'chess', $this->at(2), 'ann', 'bob'))[0]->peak);
    }

    public function testAPeakIsCelebratedAfterFiveGamesByDefault(): void
    {
        $ratings = new Ratings($this->store, new EloSystem(), $this->events);
        $peaks = [];

        for ($game = 1; $game <= 6; ++$game) {
            $peaks[] = $ratings->record(GameResult::duel('r'.$game, 'chess', $this->at($game), 'ann', 'bob'))[0]->peak;
        }

        self::assertSame([false, false, false, false, true, true], $peaks);
    }

    public function testTheStandingsListAHundredMembersByDefault(): void
    {
        $ratings = $this->ratings();

        for ($pair = 0; $pair < 51; ++$pair) {
            $ratings->record(GameResult::duel('r'.$pair, 'chess', $this->at(1), \sprintf('m%03d', 2 * $pair), \sprintf('m%03d', 2 * $pair + 1)));
        }

        for ($member = 0; $member < 102; ++$member) {
            $ratings->publish('chess', \sprintf('m%03d', $member));
        }

        self::assertCount(100, $ratings->standings('chess'));
    }

    public function testAReplayHandsOverAListOfRatings(): void
    {
        $spy = new ReplaceSpy($this->store);
        $ratings = new Ratings($spy, new EloSystem(), source: $this->source);
        $this->source->results = [GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob')];
        $ratings->replay('chess');

        self::assertCount(2, $spy->replaced);
        self::assertTrue(array_is_list($spy->replaced));
    }

    public function testTheGameOfARatingIsChecked(): void
    {
        $spy = new ReplaceSpy($this->store);

        try {
            new Ratings($spy, new EloSystem())->rating('a game', 'ann');
            self::fail('The ratings of a game that is not one were read.');
        } catch (InvalidRating) {
            self::assertSame([], $spy->found);
        }
    }

    public function testTheStandingsAreBounded(): void
    {
        $ratings = $this->ratings();
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $ratings->publish('chess', 'ann');
        $ratings->publish('chess', 'bob');

        self::assertCount(1, $ratings->standings('chess', 1));
        self::assertCount(2, $ratings->standings('chess', 1000));

        foreach ([0, 1001] as $limit) {
            try {
                $ratings->standings('chess', $limit);
                self::fail('Standings of '.$limit.' were given.');
            } catch (InvalidRating $invalid) {
                self::assertSame('The standings list 1 to 1000 members.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidRating::class);
        $ratings->standings('');
    }

    public function testRatingsOfAnotherSystemAreRefused(): void
    {
        $this->ratings()->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $other = new Ratings($this->store, new OpenSkillSystem());

        $this->expectException(RatingRefused::class);
        $this->expectExceptionMessageIsOrContains('The ratings of the game chess were computed by the system elo; replay the game to rate it with the system openskill-pl.');

        $other->record(GameResult::duel('r2', 'chess', $this->at(2), 'cy', 'ann'));
    }

    public function testARaceKeepsNothing(): void
    {
        $ratings = $this->ratings();
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));

        $this->store->save([$this->store->find('chess', ['bob'])['bob']]);
        $stale = new Ratings(new StaleStore($this->store), new EloSystem(), $this->events);
        $this->events->events = [];

        try {
            $stale->record(GameResult::duel('r2', 'chess', $this->at(2), 'ann', 'bob'));
            self::fail('A stale rating was kept.');
        } catch (RatingConflict) {
            self::assertSame([], $this->events->events);
            self::assertSame(151_600, $this->store->find('chess', ['ann'])['ann']->score);
        }
    }

    public function testAReplayGivesTheSameRatingsAsTheRecording(): void
    {
        foreach ([new EloSystem(), new OpenSkillSystem(), new OpenSkillSystem(OpenSkillModel::ThurstoneMosteller)] as $system) {
            $this->setUp();
            $ratings = new Ratings($this->store, $system, $this->events, $this->source);
            $results = [
                GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'),
                GameResult::placings('r2', 'chess', $this->at(2), [['cy'], ['ann', 'bob']]),
                GameResult::duel('r3', 'chess', $this->at(2), 'bob', 'dee', friendly: true),
                new GameResult('r4', 'chess', $this->at(3), [['ann', 'dee'], ['bob', 'cy']], [2, 1]),
            ];

            foreach ($results as $result) {
                $ratings->record($result);
            }

            $ratings->publish('chess', 'cy');
            $recorded = $this->values($this->store->ofGame('chess'));
            $this->source->results = $results;
            $this->events->events = [];

            self::assertSame(3, $ratings->replay('chess'));
            self::assertSame($recorded, $this->values($this->store->ofGame('chess')));
            self::assertSame([], $this->events->events);
            self::assertSame(Visibility::Public, $ratings->rating('chess', 'cy')->visibility);
            self::assertSame(3, $ratings->rating('chess', 'ann')->games);
        }
    }

    public function testMembersNamedByNumbersAreRatedAndReplayed(): void
    {
        foreach ([new EloSystem(), new OpenSkillSystem()] as $system) {
            $this->setUp();
            $ratings = new Ratings($this->store, $system, source: $this->source);
            $this->source->results = [GameResult::duel('1', 'chess', $this->at(1), '42', '7'), new GameResult('2', 'chess', $this->at(2), [['7', '8'], ['42', '9']], [1, 2])];

            foreach ($this->source->results as $result) {
                $ratings->record($result);
            }

            $ratings->publish('chess', '42');
            $recorded = $this->values($this->store->ofGame('chess'));

            self::assertSame(2, $ratings->replay('chess'));
            self::assertSame($recorded, $this->values($this->store->ofGame('chess')));
            self::assertSame('42', $ratings->standings('chess')[0]->member);
        }
    }

    public function testAReplayCanSwitchTheSystem(): void
    {
        $this->ratings()->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $this->source->results = [GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob')];
        $skill = new Ratings($this->store, new OpenSkillSystem(), source: $this->source);

        self::assertSame(1, $skill->replay('chess'));
        self::assertSame('openskill-pl', $skill->rating('chess', 'ann')->system);
    }

    public function testAReplayKeepsAPublicMemberWithoutAResultAndDropsAPrivateOne(): void
    {
        $ratings = $this->ratings();
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $ratings->publish('chess', 'ann');

        $this->source->results = [GameResult::duel('r2', 'chess', $this->at(1), 'cy', 'dee')];

        self::assertSame(1, $ratings->replay('chess'));
        $kept = $this->store->ofGame('chess');
        self::assertSame(['cy', 'dee', 'ann'], array_map(static fn (MemberRating $rating): string => $rating->member, $kept));
        self::assertSame(0, $kept[2]->games);
        self::assertSame(Visibility::Public, $kept[2]->visibility);
    }

    public function testAReplayChecksItsSource(): void
    {
        $bad = [
            'The source gives the result r1 of the game go when asked for the game chess.' => [GameResult::duel('r1', 'go', $this->at(1), 'ann', 'bob')],
            'The source gives the result r2 out of the order the games were played in.' => [GameResult::duel('r1', 'chess', $this->at(2), 'ann', 'bob'), GameResult::duel('r2', 'chess', $this->at(1), 'ann', 'bob')],
            'The source gives the result r1 twice.' => [GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob', friendly: true), GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob')],
        ];

        foreach ($bad as $message => $results) {
            $this->source->results = $results;

            try {
                $this->ratings()->replay('chess');
                self::fail('A source was replayed: '.$message);
            } catch (InvalidRating $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        $this->expectException(RatingRefused::class);
        $this->expectExceptionMessageIsOrContains('A replay reads the results from a source of results, and none is configured.');

        new Ratings($this->store, new EloSystem())->replay('chess');
    }

    public function testAReplayChecksTheGame(): void
    {
        $this->expectException(InvalidRating::class);

        new Ratings($this->store, new EloSystem())->replay('a game');
    }

    public function testAMemberIsExportedAndForgotten(): void
    {
        $ratings = $this->ratings();
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        $ratings->record(GameResult::duel('r2', 'go', $this->at(1), 'ann', 'bob'));

        self::assertSame(['chess', 'go'], array_map(static fn (MemberRating $rating): string => $rating->game, $ratings->export('ann')));
        $ratings->forget('ann');
        self::assertSame([], $ratings->export('ann'));
        self::assertCount(2, $ratings->export('bob'));

        $this->expectException(InvalidRating::class);
        $ratings->forget('');
    }

    public function testAnExportChecksTheMember(): void
    {
        $this->expectException(InvalidRating::class);

        $this->ratings()->export('');
    }

    public function testAResultIsRecordedOnce(): void
    {
        $ratings = $this->ratings();
        $duel = GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob');
        $ratings->record($duel);
        $this->events->events = [];

        try {
            $ratings->record($duel);
            self::fail('A result was recorded twice.');
        } catch (ResultAlreadyRecorded $resultAlreadyRecorded) {
            self::assertSame('The result r1 of the game chess is recorded already.', $resultAlreadyRecorded->getMessage());
        }

        self::assertSame([], $this->events->events);
        self::assertSame(151_600, $ratings->rating('chess', 'ann')->score);
        self::assertSame(1, $ratings->rating('chess', 'ann')->games);
        self::assertCount(2, $ratings->record(GameResult::duel('r1', 'go', $this->at(1), 'ann', 'bob')));

        $friendly = GameResult::duel('f1', 'chess', $this->at(2), 'ann', 'bob', friendly: true);
        self::assertSame([], $ratings->record($friendly));
        self::assertSame([], $ratings->record($friendly));
    }

    public function testTwoRecordingsOfAResultAtOnceNeverBothCount(): void
    {
        $ratings = new Ratings(new ForgetfulStore($this->store), new EloSystem(), $this->events);
        $duel = GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob');
        $ratings->record($duel);
        $this->events->events = [];

        $this->expectException(ResultAlreadyRecorded::class);

        try {
            $ratings->record($duel);
        } finally {
            self::assertSame([], $this->events->events);
            self::assertSame(1, $this->store->find('chess', ['ann'])['ann']->games);
        }
    }

    public function testAReplayCountsTheResultsItRated(): void
    {
        $spy = new ReplaceSpy($this->store);
        $ratings = new Ratings($spy, new EloSystem(), source: $this->source);
        $ratings->record(GameResult::duel('r0', 'chess', $this->at(1), 'ann', 'bob'));

        $this->source->results = [
            GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'),
            GameResult::duel('r2', 'chess', $this->at(2), 'ann', 'bob', friendly: true),
            GameResult::duel('r3', 'chess', $this->at(3), 'bob', 'ann'),
        ];

        self::assertSame(2, $ratings->replay('chess'));
        self::assertSame(['r1', 'r3'], $spy->results);
        self::assertCount(2, $ratings->record(GameResult::duel('r0', 'chess', $this->at(4), 'ann', 'bob')));

        $this->expectException(ResultAlreadyRecorded::class);
        $ratings->record(GameResult::duel('r3', 'chess', $this->at(4), 'ann', 'bob'));
    }

    public function testARatingIsProvisionalForTheGamesAsked(): void
    {
        $ratings = new Ratings($this->store, new EloSystem(), provisionalGames: 2);

        self::assertTrue($ratings->isProvisional($ratings->rating('chess', 'ann')));
        $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
        self::assertTrue($ratings->isProvisional($ratings->rating('chess', 'ann')));
        $ratings->record(GameResult::duel('r2', 'chess', $this->at(2), 'ann', 'bob'));
        self::assertFalse($ratings->isProvisional($ratings->rating('chess', 'ann')));

        $default = $this->ratings();
        self::assertSame(10, Ratings::PROVISIONAL_GAMES);
        self::assertTrue($default->isProvisional(new MemberRating('chess', 'ann', 'elo', new EloRating(1), 1, 9)));
        self::assertFalse($default->isProvisional(new MemberRating('chess', 'ann', 'elo', new EloRating(1), 1, 10)));
        self::assertFalse(new Ratings($this->store, new EloSystem(), provisionalGames: 0)->isProvisional($ratings->rating('chess', 'cy')));

        $skills = new Ratings($this->store, new OpenSkillSystem(provisionalSigma: 3.0), provisionalGames: 0);
        self::assertTrue($skills->isProvisional(new MemberRating('go', 'ann', 'openskill-pl', new SkillRating(25_000_000, 3_000_001), 1, 50)));
        self::assertFalse($skills->isProvisional(new MemberRating('go', 'ann', 'openskill-pl', new SkillRating(25_000_000, 3_000_000), 1, 50)));

        $this->expectException(RatingRefused::class);
        $this->expectExceptionMessageIsOrContains('The rating of the member ann for the game chess was computed by the system elo, not openskill-pl.');
        $skills->isProvisional($ratings->rating('chess', 'ann'));
    }

    public function testTheStandingsMayLeaveTheProvisionalRatingsOut(): void
    {
        $ratings = new Ratings($this->store, new EloSystem(), provisionalGames: 2);
        $this->store->save([
            new MemberRating('chess', 'ann', 'elo', new EloRating(190_000), 190_000, 1, null, Visibility::Public),
            new MemberRating('chess', 'bob', 'elo', new EloRating(180_000), 180_000, 2, null, Visibility::Public),
            new MemberRating('chess', 'cy', 'elo', new EloRating(170_000), 170_000, 3, null, Visibility::Public),
        ]);

        self::assertSame(['ann', 'bob', 'cy'], $this->members($ratings->standings('chess')));
        self::assertSame(['bob', 'cy'], $this->members($ratings->standings('chess', provisional: false)));
        self::assertSame(['bob'], $this->members($ratings->standings('chess', 1, false)));
        self::assertSame(['ann', 'bob', 'cy'], $this->members(new Ratings($this->store, new EloSystem(), provisionalGames: 0)->standings('chess', provisional: false)));
    }

    public function testTheStandingsReadFurtherForSettledSkills(): void
    {
        $ratings = new Ratings($this->store, new OpenSkillSystem(provisionalSigma: 5.0), provisionalGames: 1);
        $this->store->save([
            $this->skill('ann', 60, 10),
            $this->skill('bob', 40, 4),
            $this->skill('dee', 50, 8),
            $this->skill('cy', 30, 2),
        ]);

        self::assertSame(['ann', 'bob', 'dee', 'cy'], $this->members($ratings->standings('go')));
        self::assertSame(['bob'], $this->members($ratings->standings('go', 1, false)));
        self::assertSame(['bob', 'cy'], $this->members($ratings->standings('go', 2, false)));
        self::assertSame(['bob', 'cy'], $this->members($ratings->standings('go', 3, false)));
    }

    public function testAResultTheStoreCountsIsRefusedBeforeAnyWrite(): void
    {
        $ratings = new Ratings(new SpyStore($this->store, recorded: true), new EloSystem(), $this->events);

        try {
            $ratings->record(GameResult::duel('r1', 'chess', $this->at(1), 'ann', 'bob'));
            self::fail('A result counted already was recorded.');
        } catch (ResultAlreadyRecorded) {
            self::assertSame([], $this->store->find('chess', ['ann', 'bob']));
            self::assertSame([], $this->events->events);
        }
    }

    public function testTheStandingsAskTheStoreForTheGamesTheyNeed(): void
    {
        $spy = new SpyStore($this->store);

        new Ratings($spy, new EloSystem())->standings('chess', 10);
        new Ratings($spy, new EloSystem(), provisionalGames: 0)->standings('chess', 5, false);
        new Ratings($spy, new EloSystem(), provisionalGames: 3)->standings('chess', 7, false);

        self::assertSame([[10, 1], [5, 1], [7, 3]], $spy->reads);
    }

    public function testTheStandingsReadTwiceAsManyEachTimeUntilTheLimitIsSettled(): void
    {
        $spy = new SpyStore($this->store);
        $ratings = new Ratings($spy, new OpenSkillSystem(provisionalSigma: 5.0), provisionalGames: 1);
        $this->store->save([$this->skill('u1', 60, 10), $this->skill('u2', 58, 10), $this->skill('u3', 56, 10), $this->skill('s1', 35, 4)]);

        self::assertSame(['s1'], $this->members($ratings->standings('go', 1, false)));
        self::assertSame([[1, 1], [2, 1], [4, 1]], $spy->reads);
    }

    public function testTheStandingsKeepTheLimitOfTheSettledRatingsTheyRead(): void
    {
        $spy = new SpyStore($this->store);
        $ratings = new Ratings($spy, new OpenSkillSystem(provisionalSigma: 5.0), provisionalGames: 1);
        $this->store->save([$this->skill('u1', 60, 10), $this->skill('u2', 58, 10), $this->skill('s1', 35, 4), $this->skill('s2', 34, 4)]);

        self::assertSame(['s1'], $this->members($ratings->standings('go', 1, false)));
        self::assertSame([[1, 1], [2, 1], [4, 1]], $spy->reads);
    }

    public function testTheStandingsStopWhenTheStoreHasNoMoreToGive(): void
    {
        $spy = new SpyStore($this->store);
        $ratings = new Ratings($spy, new OpenSkillSystem(provisionalSigma: 5.0), provisionalGames: 1);
        $this->store->save([$this->skill('u1', 60, 10), $this->skill('s1', 35, 4)]);

        self::assertSame(['s1'], $this->members($ratings->standings('go', 5, false)));
        self::assertSame([[5, 1]], $spy->reads);
    }

    public function testTheStandingsStopAtTheMostTheyRead(): void
    {
        $ratings = new Ratings($this->store, new OpenSkillSystem(provisionalSigma: 5.0), provisionalGames: 1);
        $unsettled = [];

        for ($member = 0; $member < Ratings::MAX_STANDINGS + 1; ++$member) {
            $unsettled[] = $this->skill(\sprintf('m%04d', $member), 60, 10);
        }

        $this->store->save($unsettled);

        self::assertSame([], $ratings->standings('go', Ratings::MAX_STANDINGS, false));
        self::assertSame([], $ratings->standings('go', 600, false));
    }

    public function testTheGamesOfAProvisionalRatingAreChecked(): void
    {
        foreach ([-1, 1001] as $games) {
            try {
                new Ratings($this->store, new EloSystem(), provisionalGames: $games);
                self::fail('A provisional rating for '.$games.' games was taken.');
            } catch (InvalidRating $invalid) {
                self::assertSame('A rating is provisional for 0 to 1000 rated games.', $invalid->getMessage());
            }
        }

        self::assertInstanceOf(Ratings::class, new Ratings($this->store, new EloSystem(), provisionalGames: 1000));
    }

    public function testTheGamesBeforeACelebrationAreChecked(): void
    {
        foreach ([0, 1001] as $games) {
            try {
                new Ratings($this->store, new EloSystem(), celebrateAfter: $games);
                self::fail('A celebration after '.$games.' games was taken.');
            } catch (InvalidRating $invalid) {
                self::assertSame('A new highest score is celebrated after 1 to 1000 rated games.', $invalid->getMessage());
            }
        }

        self::assertInstanceOf(Ratings::class, new Ratings($this->store, new EloSystem(), celebrateAfter: 1000));
    }

    private function ratings(int $celebrateAfter = 5): Ratings
    {
        return new Ratings($this->store, new EloSystem(), $this->events, $this->source, $celebrateAfter);
    }

    /**
     * @param list<MemberRating> $ratings
     *
     * @return array<string, array<string, mixed>>
     */
    private function values(array $ratings): array
    {
        $values = [];

        foreach ($ratings as $rating) {
            $values[$rating->member] = array_diff_key($rating->toArray(), ['version' => true]);
        }

        ksort($values);

        return $values;
    }

    /**
     * @param list<MemberRating> $ratings
     *
     * @return list<string>
     */
    private function members(array $ratings): array
    {
        return array_map(static fn (MemberRating $rating): string => $rating->member, $ratings);
    }

    private function skill(string $member, int $mu, int $sigma): MemberRating
    {
        $skill = new SkillRating($mu * SkillRating::SCALE, $sigma * SkillRating::SCALE);

        return new MemberRating('go', $member, 'openskill-pl', $skill, ($mu - 3 * $sigma) * 100, 5, null, Visibility::Public);
    }

    private function at(int $hour): DateTimeImmutable
    {
        return new DateTimeImmutable(\sprintf('2026-11-13 %02d:00:00 UTC', $hour));
    }
}
