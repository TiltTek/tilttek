<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Elimination\BracketMatch;
use Tilttek\Tournament\Event\TournamentFinished;
use Tilttek\Tournament\Exception\InvalidResult;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Exception\RoundRefused;
use Tilttek\Tournament\Exception\TournamentConflict;
use Tilttek\Tournament\Exception\TournamentNotFound;
use Tilttek\Tournament\Explanation;
use Tilttek\Tournament\ExplanationKind;
use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Progress;
use Tilttek\Tournament\Recording;
use Tilttek\Tournament\RecordingStatus;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\ScheduledGame;
use Tilttek\Tournament\Seeding\FixedSeeding;
use Tilttek\Tournament\Stage;
use Tilttek\Tournament\Standings\Standing;
use Tilttek\Tournament\Standings\StandingChange;
use Tilttek\Tournament\Standings\Standings;
use Tilttek\Tournament\Store\InMemoryTournamentStore;
use Tilttek\Tournament\Submission;
use Tilttek\Tournament\Tournament;
use Tilttek\Tournament\Tournaments;

#[CoversClass(Tournaments::class)]
#[CoversClass(Progress::class)]
#[CoversClass(Recording::class)]
#[CoversClass(RecordingStatus::class)]
#[CoversClass(ScheduledGame::class)]
#[CoversClass(Explanation::class)]
#[CoversClass(ExplanationKind::class)]
#[CoversClass(TournamentFinished::class)]
#[CoversClass(InMemoryTournamentStore::class)]
#[CoversClass(StandingChange::class)]
#[CoversClass(Standings::class)]
final class TournamentsTest extends TestCase
{
    public function testStartFixesTheInitialOrderFromTheSeeding(): void
    {
        $service = $this->service(['d', 'c', 'b', 'a']);
        $state = $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::swiss('swiss', 2)]));

        self::assertSame(['d', 'c', 'b', 'a'], $state->order);
        self::assertSame(1, $state->version);
        self::assertSame($state->order, $service->get('cup')->order);
    }

    public function testStartRefusesAnUnknownTiebreaker(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->service()->start(new Tournament('cup', 'seed', ['a', 'b'], [Stage::swiss('swiss', 1)], tiebreakers: ['nope']));
    }

    public function testStartRefusesATournamentAlreadyStarted(): void
    {
        $service = $this->service();
        $service->start($this->swiss(['a', 'b']));

        $this->expectException(TournamentConflict::class);
        $service->start($this->swiss(['a', 'b']));
    }

    public function testAnUnknownTournamentIsNotFound(): void
    {
        $this->expectException(TournamentNotFound::class);
        $this->service()->get('cup');
    }

    public function testASwissTournamentFromStartToPodium(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::swiss('swiss', 2)], tiebreakers: ['buchholz']));

        $first = $service->prepareRound('cup');
        self::assertSame(['a-c', 'd-b'], $this->tables($first));
        self::assertSame($first, $service->publishRound('cup', $first));
        self::assertSame(RecordingStatus::Recorded, $service->record('cup', 'swiss#1#1', Result::firstWins(), 'a')->status);
        self::assertSame([], $service->record('cup', 'swiss#1#2', Result::secondWins(), 'b')->events);

        $second = $service->prepareRound('cup');
        self::assertSame(2, $second->number);
        self::assertSame(['b-a', 'c-d'], $this->tables($second));
        $service->publishRound('cup', $second);
        $service->record('cup', 'swiss#2#1', Result::secondWins(), 'org');

        $last = $service->record('cup', 'swiss#2#2', Result::draw(), 'org');

        self::assertEquals([new TournamentFinished('cup', 'a', ['a', 'b', 'c'])], $last->events);
        self::assertSame('cup/finished/a,b,c', $last->events[0]->key());
        self::assertSame(['a', 'b', 'c'], $service->get('cup')->podium);
        self::assertSame(['a', 'b', 'c', 'd'], $service->standings('cup')->participants());
        self::assertSame(['buchholz' => 5], $service->standings('cup')->of('c')?->tiebreaks);

        $this->expectException(RoundRefused::class);
        $this->expectExceptionMessageIs('The tournament is over.');
        $service->prepareRound('cup');
    }

    public function testTheNextRoundWaitsForEveryResult(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd']);
        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'org');

        $this->expectException(RoundRefused::class);
        $this->expectExceptionMessageIs('The round 1 of the stage swiss has results missing or conflicts to settle.');
        $service->prepareRound('cup');
    }

    public function testAPublishedRoundIsTheNextOne(): void
    {
        $service = $this->service();
        $service->start($this->swiss(['a', 'b']));

        $this->expectException(RoundRefused::class);
        $this->expectExceptionMessageIs('The next round is the round 1 of the stage swiss.');
        $service->publishRound('cup', new Round('swiss', 2, [new Pairing(1, 'a', 'b')]));
    }

    public function testAPublishedRoundIsOfTheStageUnderWay(): void
    {
        $service = $this->service();
        $service->start($this->swiss(['a', 'b']));

        $this->expectException(RoundRefused::class);
        $service->publishRound('cup', new Round('other', 1, [new Pairing(1, 'a', 'b')]));
    }

    public function testAnOrganiserSeatsTheSameParticipantsOtherwise(): void
    {
        $service = $this->service();
        $service->start($this->swiss(['a', 'b', 'c', 'd']));

        $round = new Round('swiss', 1, [new Pairing(1, 'd', 'a'), new Pairing(2, 'b', 'c')]);

        self::assertSame($round, $service->publishRound('cup', $round));
        self::assertSame(['d-a', 'b-c'], $this->tables($service->get('cup')->rounds[0]));
    }

    public function testAPublishedRoundSeatsEveryoneExpected(): void
    {
        $service = $this->service();
        $service->start($this->swiss(['a', 'b', 'c']));

        $this->expectException(RoundRefused::class);
        $this->expectExceptionMessageIs('A round seats each participant of the stage still in, once.');
        $service->publishRound('cup', new Round('swiss', 1, [new Pairing(1, 'a', 'b')]));
    }

    public function testARoundIsRescheduledWithoutLosingItsPlace(): void
    {
        $service = $this->started(['a', 'b']);
        $date = new DateTimeImmutable('2026-12-05 13:00');
        $service->reschedule('cup', 'swiss', 1, $date);
        $round = $service->get('cup')->rounds[0];

        self::assertSame(1, $round->number);
        self::assertSame($date, $round->startsAt);
        self::assertSame(['a-b'], $this->tables($round));

        $service->reschedule('cup', 'swiss', 1, null);
        self::assertNull($service->get('cup')->rounds[0]->startsAt);
    }

    public function testAnUnpublishedRoundIsNotRescheduled(): void
    {
        $service = $this->started(['a', 'b']);

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The round 2 of the stage swiss is not published.');
        $service->reschedule('cup', 'swiss', 2, null);
    }

    public function testAnUnpublishedStageRoundIsNotRescheduled(): void
    {
        $service = $this->started(['a', 'b']);

        $this->expectException(InvalidTournament::class);
        $service->reschedule('cup', 'other', 1, null);
    }

    public function testAResultEnteredTwiceCountsOnce(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd']);
        $version = $service->get('cup')->version;

        self::assertSame(RecordingStatus::Recorded, $service->record('cup', 'swiss#1#1', Result::firstWins(3, 1), 'a')->status);
        self::assertSame(RecordingStatus::Unchanged, $service->record('cup', 'swiss#1#1', Result::firstWins(3, 1), 'c')->status);
        self::assertSame($version + 1, $service->get('cup')->version);
        self::assertSame('a', $service->get('cup')->results['swiss#1#1']->by);
        self::assertSame(2, $service->standings('cup')->of('a')?->points);
    }

    public function testAContraryResultWaitsForAnOrganiser(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd'], 2);
        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'a');

        self::assertSame(RecordingStatus::Conflict, $service->record('cup', 'swiss#1#1', Result::secondWins(), 'c')->status);
        $state = $service->get('cup');
        self::assertArrayNotHasKey('swiss#1#1', $state->results);
        self::assertSame(['a', 'c'], array_map(static fn (Submission $submission): string => $submission->by, $state->conflicts['swiss#1#1']));
        self::assertSame(0, $service->standings('cup')->of('a')?->points);

        $version = $state->version;
        self::assertSame(RecordingStatus::Conflict, $service->record('cup', 'swiss#1#1', Result::secondWins(), 'c')->status);
        self::assertSame($version, $service->get('cup')->version);

        self::assertSame(RecordingStatus::Conflict, $service->record('cup', 'swiss#1#1', Result::draw(), 'judge')->status);
        self::assertCount(3, $service->get('cup')->conflicts['swiss#1#1']);

        $service->record('cup', 'swiss#1#2', Result::firstWins(), 'b');

        try {
            $service->prepareRound('cup');
            self::fail('A conflict holds the next round.');
        } catch (RoundRefused) {
        }

        self::assertSame(RecordingStatus::Recorded, $service->resolve('cup', 'swiss#1#1', Result::secondWins(), 'judge')->status);
        $state = $service->get('cup');
        self::assertSame([], $state->conflicts);
        self::assertSame('judge', $state->results['swiss#1#1']->by);
        self::assertSame(Outcome::SecondWins, $state->results['swiss#1#1']->result->outcome);
        self::assertSame(2, $service->prepareRound('cup')->number);
    }

    public function testOnlyAConflictIsResolved(): void
    {
        $service = $this->started(['a', 'b']);

        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('The table swiss#1#1 has no conflict to settle.');
        $service->resolve('cup', 'swiss#1#1', Result::draw(), 'judge');
    }

    public function testWhoEntersAResultIsAnIdentifier(): void
    {
        $service = $this->started(['a', 'b']);

        $this->expectException(InvalidTournament::class);
        $service->record('cup', 'swiss#1#1', Result::draw(), 'Jane Doe');
    }

    public function testWhoResolvesIsAnIdentifier(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->started(['a', 'b'])->resolve('cup', 'swiss#1#1', Result::draw(), '');
    }

    public function testWhoCorrectsIsAnIdentifier(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->started(['a', 'b'])->correct('cup', 'swiss#1#1', Result::draw(), ' ');
    }

    public function testAnUnknownTableHasNoResult(): void
    {
        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('No published table has the key swiss#1#9.');
        $this->started(['a', 'b'])->record('cup', 'swiss#1#9', Result::draw(), 'org');
    }

    public function testAByeHasNoResult(): void
    {
        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('A bye has no result to enter.');
        $this->started(['a', 'b', 'c'])->record('cup', 'swiss#1#2', Result::draw(), 'org');
    }

    public function testACorrectionShowsHowTheStandingsMoved(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd'], 2);
        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'org');
        $service->record('cup', 'swiss#1#2', Result::firstWins(), 'org');
        $service->publishRound('cup', $service->prepareRound('cup'));

        self::assertSame(RecordingStatus::Unchanged, $service->correct('cup', 'swiss#1#1', Result::firstWins(), 'org')->status);

        $correction = $service->correct('cup', 'swiss#1#1', Result::secondWins(), 'judge');

        self::assertSame(RecordingStatus::Recorded, $correction->status);
        $moved = [];

        foreach ($correction->changes as $change) {
            $moved[$change->participant] = [$change->rankBefore, $change->rankAfter, $change->pointsBefore, $change->pointsAfter];
        }

        ksort($moved);
        self::assertSame(['a' => [1, 3, 2, 0], 'c' => [3, 2, 0, 2], 'd' => [2, 1, 2, 2]], $moved);
        self::assertSame('judge', $service->get('cup')->results['swiss#1#1']->by);
        self::assertSame(['d-a', 'b-c'], $this->tables($service->get('cup')->rounds[1]));
    }

    public function testOnlyAKeptResultIsCorrected(): void
    {
        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('The table swiss#1#1 has no result to correct.');
        $this->started(['a', 'b'])->correct('cup', 'swiss#1#1', Result::draw(), 'org');
    }

    public function testAnEliminationMatchHasAWinner(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b'], [Stage::singleElimination('cut')]));
        $service->publishRound('cup', $service->prepareRound('cup'));

        $this->expectException(InvalidResult::class);
        $service->record('cup', 'cut#1#1', Result::draw(), 'org');
    }

    public function testAnEliminationFromSeedsToChampion(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e'], [Stage::singleElimination('cut')]));

        $first = $service->prepareRound('cup');
        self::assertSame(['a', 'd-e', 'b', 'c'], $this->tables($first));
        $service->publishRound('cup', $first);
        $this->playAll($service, $first, Result::secondWins());

        $second = $service->prepareRound('cup');
        self::assertSame(['a-e', 'b-c'], $this->tables($second));
        $service->publishRound('cup', $second);
        $service->record('cup', $second->key($second->pairings[0]->table), Result::firstWins(), 'org');
        $service->record('cup', $second->key($second->pairings[1]->table), Result::secondWins(), 'org');

        $final = $service->prepareRound('cup');
        self::assertSame(['a-c'], $this->tables($final));
        $service->publishRound('cup', $final);
        $done = $service->record('cup', $final->key($final->pairings[0]->table), Result::secondWins(), 'org');

        self::assertSame('c', $done->events[0]->winner);
        self::assertSame(['c', 'a'], \array_slice($done->events[0]->podium, 0, 2));
        self::assertSame(['c', 'a', 'b', 'e', 'd'], $service->standings('cup')->participants());
        self::assertSame('c', $service->bracket('cup')->champion());
    }

    public function testACorrectionWaitsForTheNextMatchToBeCorrectedFirst(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::singleElimination('cut')]));

        $first = $service->prepareRound('cup');
        $service->publishRound('cup', $first);
        $service->record('cup', $first->key($first->pairings[0]->table), Result::firstWins(), 'org');
        $service->record('cup', $first->key($first->pairings[1]->table), Result::firstWins(), 'org');

        $corrected = $service->correct('cup', $first->key($first->pairings[1]->table), Result::secondWins(), 'org');
        self::assertSame(RecordingStatus::Recorded, $corrected->status);

        $final = $service->prepareRound('cup');
        self::assertSame(['a-c'], $this->tables($final));
        $service->publishRound('cup', $final);
        $service->record('cup', $final->key($final->pairings[0]->table), Result::firstWins(), 'org');

        $this->expectException(RoundRefused::class);
        $this->expectExceptionMessageIs('The winner of this match has played the next one already: correct that one first.');
        $service->correct('cup', $first->key($first->pairings[0]->table), Result::secondWins(), 'org');
    }

    public function testAWithdrawnParticipantForfeitsAndIsNotPairedAgain(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd'], 3);
        $service->record('cup', 'swiss#1#2', Result::firstWins(), 'org');

        self::assertSame(RecordingStatus::Recorded, $service->withdraw('cup', 'c')->status);
        self::assertSame(2, $service->standings('cup')->of('a')?->points);
        self::assertTrue($service->standings('cup')->of('c')?->withdrawn);

        $second = $service->prepareRound('cup');
        self::assertSame(['a-d', 'b'], $this->tables($second));
        self::assertNull($second->pairingOf('c'));

        $service->publishRound('cup', $second);
        $explanation = $service->explain('cup', 'swiss', 2, 'a', 'c');
        self::assertSame(ExplanationKind::Absent, $explanation->kind);
        self::assertSame('c', $explanation->participant);
    }

    public function testAWithdrawalAfterPairingIsAForfeitOfBoth(): void
    {
        $service = $this->started(['a', 'b'], 2);
        $service->withdraw('cup', 'a');
        $service->withdraw('cup', 'b');

        self::assertSame([0, 0], [$service->standings('cup')->of('a')?->points, $service->standings('cup')->of('b')?->points]);
    }

    public function testAWithdrawalOfTheFirstPlayerIsTheirForfeit(): void
    {
        $service = $this->started(['a', 'b'], 2);
        $service->withdraw('cup', 'a');

        self::assertSame([0, 2], [$service->standings('cup')->of('a')?->points, $service->standings('cup')->of('b')?->points]);
    }

    public function testAParticipantWithdrawsOnce(): void
    {
        $service = $this->started(['a', 'b']);
        $service->withdraw('cup', 'a');

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The participant a is not in the tournament, or withdrew already.');
        $service->withdraw('cup', 'a');
    }

    public function testOnlyAParticipantWithdraws(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->started(['a', 'b'])->withdraw('cup', 'z');
    }

    public function testAWithdrawalMayDecideTheTournament(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b'], [Stage::singleElimination('cut')]));
        $service->publishRound('cup', $service->prepareRound('cup'));

        $withdrawal = $service->withdraw('cup', 'b');

        self::assertSame('a', $withdrawal->events[0]->winner);
        self::assertTrue($service->get('cup')->isOver());
    }

    public function testPoolsThenABracketPreparedBeforeTheQualifiersAreKnown(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e', 'f'], [Stage::roundRobin('pools', 2, qualifiers: 2), Stage::singleElimination('cut')]));

        $bracket = $service->bracket('cup');
        self::assertSame(['pools/A#1', 'pools/B#2', 'pools/B#1', 'pools/A#2'], $this->labels($service->bracket('cup', 'cut')->bracket->round(1)));
        self::assertNull($bracket->champion());

        $schedule = $service->schedule('cup', 'a');
        self::assertCount(3, $schedule);
        self::assertSame([false, false, false], array_map(static fn (ScheduledGame $game): bool => $game->published, $schedule));
        self::assertCount(1, array_filter($schedule, static fn (ScheduledGame $game): bool => $game->rest));
        self::assertSame(['A', 'A', 'A'], array_map(static fn (ScheduledGame $game): ?string => $game->pool, $schedule));

        for ($number = 1; $number <= 3; ++$number) {
            $round = $service->prepareRound('cup');
            self::assertSame($number, $round->number);
            $service->publishRound('cup', $round);

            foreach ($round->pairings as $pairing) {
                if (!$pairing->isBye()) {
                    $service->record('cup', $round->key($pairing->table), Result::firstWins(), 'org');
                }
            }
        }

        $published = $service->schedule('cup', 'a');
        self::assertSame([true, true, true], array_map(static fn (ScheduledGame $game): bool => $game->published, $published));

        $poolA = $service->standings('cup', 'pools', 'A');
        self::assertCount(3, $poolA->standings);
        $cut = $service->prepareRound('cup');
        self::assertSame('cut', $cut->stage);
        self::assertCount(2, $cut->pairings);
        self::assertSame($poolA->participants()[0], $cut->pairings[0]->first);
        $winner = $poolA->participants()[0];
        $before = $service->schedule('cup', $winner);
        self::assertCount(3, $before);
        $service->publishRound('cup', $cut);
        $games = $service->schedule('cup', $winner);
        self::assertCount(4, $games);
        self::assertSame(['cut', 1, true, true], [$games[3]->stage, $games[3]->round, $games[3]->first, $games[3]->published]);
    }

    public function testAPoolIsKnownToItsStage(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::roundRobin('pools', 2)]));

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The stage pools has no pool C.');
        $service->standings('cup', 'pools', 'C');
    }

    public function testOnlyAnEliminationHasABracket(): void
    {
        $service = $this->service();
        $service->start($this->swiss(['a', 'b']));

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The stage swiss is not an elimination.');
        $service->bracket('cup');
    }

    public function testARoundRobinEndsWithItsPodium(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c'], [Stage::roundRobin('pool', legs: 2)]));

        $events = [];

        for ($number = 1; $number <= 6; ++$number) {
            $round = $service->prepareRound('cup');
            $service->publishRound('cup', $round);

            foreach ($round->pairings as $pairing) {
                if (!$pairing->isBye()) {
                    $winner = 'a' === $pairing->first || 'a' === $pairing->second ? ('a' === $pairing->first ? Result::firstWins() : Result::secondWins()) : Result::draw();
                    $events = [...$events, ...$service->record('cup', $round->key($pairing->table), $winner, 'org')->events];
                }
            }
        }

        self::assertCount(1, $events);
        self::assertSame('a', $events[0]->winner);
        self::assertSame('a', $service->get('cup')->podium[0]);
    }

    public function testACorrectionThatChangesThePodiumTellsItAgain(): void
    {
        $service = $this->started(['a', 'b']);
        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'org');

        $correction = $service->correct('cup', 'swiss#1#1', Result::secondWins(), 'org');

        self::assertSame(['b', 'a'], $correction->events[0]->podium);
        self::assertSame(['b', 'a'], $service->get('cup')->podium);
    }

    public function testTheExplanationsOfARound(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd', 'e'], 3);
        $round = $service->get('cup')->rounds[0];
        self::assertSame(['a-c', 'd-b', 'e'], $this->tables($round));
        self::assertEquals(new Explanation(ExplanationKind::Paired, null, 1), $service->explain('cup', 'swiss', 1, 'a', 'c'));
        self::assertEquals(new Explanation(ExplanationKind::Paired, null, 1), $service->explain('cup', 'swiss', 1, 'b', 'd'));
        self::assertEquals(new Explanation(ExplanationKind::Bye, 'e', 1), $service->explain('cup', 'swiss', 1, 'a', 'e'));
        self::assertEquals(new Explanation(ExplanationKind::Bye, 'e', 1), $service->explain('cup', 'swiss', 1, 'e', 'a'));
        self::assertEquals(new Explanation(ExplanationKind::OtherPairing), $service->explain('cup', 'swiss', 1, 'a', 'b'));
        self::assertEquals(new Explanation(ExplanationKind::Absent, 'z'), $service->explain('cup', 'swiss', 1, 'a', 'z'));
        self::assertEquals(new Explanation(ExplanationKind::Absent, 'z'), $service->explain('cup', 'swiss', 1, 'z', 'a'));

        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'org');
        $service->record('cup', 'swiss#1#2', Result::secondWins(), 'org');

        $second = $service->prepareRound('cup');
        $service->publishRound('cup', $second);

        self::assertNull('a' === $second->pairingOf('a')?->opponentOf('c') ? 'met again' : null);
        self::assertEquals(new Explanation(ExplanationKind::AlreadyMet, null, 1), $service->explain('cup', 'swiss', 2, 'a', 'c'));
        self::assertEquals(new Explanation(ExplanationKind::AlreadyMet, null, 1), $service->explain('cup', 'swiss', 2, 'c', 'a'));
    }

    public function testTheExplanationOfAnUnpublishedRound(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The round 3 of the stage swiss is not published.');
        $this->started(['a', 'b'])->explain('cup', 'swiss', 3, 'a', 'b');
    }

    public function testTheExplanationsOfPools(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], [Stage::roundRobin('pools', 2)]));

        $round = $service->prepareRound('cup');
        $service->publishRound('cup', $round);
        $a = $round->pairingOf('a');
        self::assertNotNull($a);
        $sameTable = (string) $a->opponentOf('a');
        $samePool = array_find(['b', 'c', 'd', 'e', 'f', 'g', 'h'], static fn (string $p): bool => $p !== $sameTable && $round->pairingOf($p)?->pool === $a->pool);
        $otherPool = array_find(['b', 'c', 'd', 'e', 'f', 'g', 'h'], static fn (string $p): bool => $round->pairingOf($p)?->pool !== $a->pool);
        self::assertNotNull($samePool);
        self::assertNotNull($otherPool);

        self::assertSame(ExplanationKind::Scheduled, $service->explain('cup', 'pools', 1, 'a', $samePool)->kind);
        self::assertSame(ExplanationKind::DifferentPools, $service->explain('cup', 'pools', 1, 'a', $otherPool)->kind);
    }

    public function testTheExplanationOfDifferentScores(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd', 'e', 'f'], 3);
        foreach ($service->get('cup')->rounds[0]->pairings as $pairing) {
            $service->record('cup', 'swiss#1#'.$pairing->table, Result::firstWins(), 'org');
        }

        $round = $service->prepareRound('cup');
        $service->publishRound('cup', $round);
        $winner = $round->pairings[0]->first;
        $loser = $round->pairings[\count($round->pairings) - 1]->first;

        self::assertSame(ExplanationKind::DifferentScores, $service->explain('cup', 'swiss', 2, $winner, $loser)->kind);
    }

    public function testTheInMemoryStoreRefusesAStaleState(): void
    {
        $store = new InMemoryTournamentStore();
        $service = new Tournaments($store);
        $stale = $service->start($this->swiss(['a', 'b']));
        $service->publishRound('cup', $service->prepareRound('cup'));

        $this->expectException(TournamentConflict::class);
        $store->save($stale);
    }

    public function testTwoConflictsAreKeptSideBySide(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd']);
        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'a');
        $service->record('cup', 'swiss#1#2', Result::firstWins(), 'c');
        $service->record('cup', 'swiss#1#1', Result::secondWins(), 'b');
        $service->record('cup', 'swiss#1#2', Result::secondWins(), 'd');

        self::assertSame(['swiss#1#1', 'swiss#1#2'], array_keys($service->get('cup')->conflicts));

        $service->record('cup', 'swiss#1#1', Result::draw(), 'judge');

        $conflicts = $service->get('cup')->conflicts;
        self::assertSame(['swiss#1#1', 'swiss#1#2'], array_keys($conflicts));
        self::assertCount(3, $conflicts['swiss#1#1']);
        self::assertCount(2, $conflicts['swiss#1#2']);
    }

    public function testAResolutionChecksItsResult(): void
    {
        $service = $this->started(['a', 'b', 'c']);

        try {
            $service->resolve('cup', 'swiss#1#9', Result::firstWins(), 'judge');
            self::fail('A table must exist.');
        } catch (InvalidResult $invalidResult) {
            self::assertSame('No published table has the key swiss#1#9.', $invalidResult->getMessage());
        }

        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('A bye has no result to enter.');
        $service->resolve('cup', 'swiss#1#2', Result::firstWins(), 'judge');
    }

    public function testABracketIsOfTheStageAsked(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::swiss('swiss', 2, 4), Stage::singleElimination('cut')]));

        self::assertSame(2, $service->bracket('cup')->bracket->rounds);

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The stage swiss is not an elimination.');
        $service->bracket('cup', 'swiss');
    }

    public function testTheFinalIsCorrectedWhateverTheEarlierMatchesHold(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::singleElimination('cut')]));

        $first = $service->prepareRound('cup');
        $service->publishRound('cup', $first);
        $this->playAll($service, $first, Result::firstWins());
        $final = $service->prepareRound('cup');
        $service->publishRound('cup', $final);
        $key = $final->key($final->pairings[0]->table);
        $service->record('cup', $key, Result::firstWins(), 'org');

        self::assertSame(RecordingStatus::Recorded, $service->correct('cup', $key, Result::secondWins(), 'org')->status);
        self::assertSame('b', $service->bracket('cup')->champion());
    }

    public function testAMatchIsCorrectedWhileTheNextOneIsStillToPlay(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::singleElimination('cut')]));

        $first = $service->prepareRound('cup');
        $service->publishRound('cup', $first);
        $this->playAll($service, $first, Result::firstWins());
        $service->publishRound('cup', $service->prepareRound('cup'));

        self::assertSame(RecordingStatus::Recorded, $service->correct('cup', $first->key(1), Result::secondWins(), 'org')->status);
    }

    public function testTheScheduleNumbersTheRoundsStillToPublishAfterThePublishedOnes(): void
    {
        $service = $this->service();
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::roundRobin('pool')]));

        $first = $service->prepareRound('cup');
        $service->publishRound('cup', $first);
        $this->playAll($service, $first, Result::firstWins());
        $second = $service->prepareRound('cup');
        $service->publishRound('cup', $second);

        $schedule = $service->schedule('cup', 'a');

        self::assertSame([1, 2, 3], array_map(static fn (ScheduledGame $game): int => $game->round, $schedule));
        self::assertSame([true, true, false], array_map(static fn (ScheduledGame $game): bool => $game->published, $schedule));
    }

    public function testThreeStagesPassTheirQualifiersOn(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], [
            Stage::swiss('open', 2, 4),
            Stage::roundRobin('pool', qualifiers: 2),
            Stage::singleElimination('cut'),
        ]));

        $unpublished = $service->schedule('cup', 'a');
        self::assertSame([], $unpublished, 'Nothing is paired before the first round is published.');
        self::assertNull($service->bracket('cup')->state(1)?->first);

        $first = $service->prepareRound('cup');
        $service->publishRound('cup', $first);
        $published = $service->schedule('cup', 'a');
        self::assertCount(1, $published);

        $stages = [];
        $tables = [];

        for ($guard = 0; $guard < 12 && [] === $service->get('cup')->podium; ++$guard) {
            $round = 0 === $guard ? $first : $service->prepareRound('cup');

            if (0 !== $guard) {
                $service->publishRound('cup', $round);
            }

            $stages[] = $round->stage.$round->number;
            $tables[] = array_map(static fn (Pairing $pairing): int => $pairing->table, $round->pairings);
            $this->playAll($service, $round, Result::firstWins());
        }

        self::assertSame(['open1', 'open2', 'pool1', 'pool2', 'pool3', 'cut1'], $stages);
        self::assertSame([[1, 2, 3, 4], [1, 2, 3, 4], [1, 2], [1, 2], [1, 2], [1]], $tables);
        self::assertCount(4, $service->standings('cup', 'pool')->standings);
        self::assertCount(2, $service->standings('cup', 'cut')->standings);
        self::assertSame([1, 2], array_map(static fn (Standing $standing): int => $standing->rank, $service->standings('cup', 'cut')->standings));
        self::assertCount(2, $service->get('cup')->podium);
        self::assertSame($service->get('cup')->podium[0], $service->bracket('cup')->champion());
        self::assertSame($service->standings('cup', 'pool')->qualifiers(2), \array_slice(array_map(static fn (Standing $standing): string => $standing->participant, $service->standings('cup', 'pool')->standings), 0, 2));
    }

    public function testASinglePoolHasNoLetter(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd']);
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::roundRobin('pool')]));

        self::assertSame([null, null], array_map(static fn (Pairing $pairing): ?string => $pairing->pool, $service->prepareRound('cup')->pairings));
        self::assertSame([null, null, null], array_map(static fn (ScheduledGame $game): ?string => $game->pool, $service->schedule('cup', 'a')));

        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The stage pool has no pool A.');
        $service->standings('cup', 'pool', 'A');
    }

    public function testPoolsNumberTheirTablesOnFromOneToTheNext(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], [Stage::roundRobin('pools', 2)]));

        $round = $service->prepareRound('cup');

        self::assertSame([1, 2, 3, 4], array_map(static fn (Pairing $pairing): int => $pairing->table, $round->pairings));
        self::assertSame(['A', 'A', 'B', 'B'], array_map(static fn (Pairing $pairing): ?string => $pairing->pool, $round->pairings));
    }

    public function testAParticipantWhoLeftBeforePlayingRanksBelowTheOthersOfTheBracket(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd']);
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::singleElimination('cut')]));
        $service->withdraw('cup', 'a');

        $round = $service->prepareRound('cup');
        $service->publishRound('cup', $round);

        self::assertSame(['d', 'b', 'c', 'a'], array_map(static fn (Standing $standing): string => $standing->participant, $service->standings('cup')->standings));
        self::assertSame([1, 2, 3, 4], array_map(static fn (Standing $standing): int => $standing->rank, $service->standings('cup')->standings));
    }

    public function testAResultIsKeptWhenAParticipantLeavesAfterwards(): void
    {
        $service = $this->started(['a', 'b', 'c', 'd']);
        $service->record('cup', 'swiss#1#1', Result::firstWins(), 'org');
        $service->withdraw('cup', 'a');

        self::assertSame(Outcome::FirstWins, $service->get('cup')->results['swiss#1#1']->result->outcome);
        self::assertSame(2, $service->standings('cup')->of('a')?->points);
    }

    public function testEveryTableWithoutResultOfAWithdrawnParticipantIsAForfeit(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd', 'e']);
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e'], [Stage::singleElimination('cut')]));
        $service->publishRound('cup', $service->prepareRound('cup'));
        $service->withdraw('cup', 'd');
        $service->withdraw('cup', 'b');

        self::assertSame(2, $service->prepareRound('cup')->number);
    }

    public function testTheTableAfterAByeIsForfeitedToo(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd', 'e']);
        $service->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd', 'e'], [Stage::singleElimination('cut')]));

        $first = $service->prepareRound('cup');
        $service->publishRound('cup', $first);
        self::assertTrue($first->pairings[0]->isBye());

        $service->withdraw('cup', 'e');

        self::assertSame(2, $service->standings('cup')->of('d')?->points);
    }

    public function testAWithdrawnParticipantLeavesThePodium(): void
    {
        $service = $this->service(['a', 'b', 'c', 'd']);
        $service->start($this->swiss(['a', 'b', 'c', 'd']));

        $round = $service->prepareRound('cup');
        $service->publishRound('cup', $round);
        $this->playAll($service, $round, Result::firstWins());
        $podium = $service->get('cup')->podium;
        self::assertCount(3, $podium);

        $event = $service->withdraw('cup', $podium[0])->events[0];

        self::assertSame(\array_slice($podium, 1, 2), \array_slice($service->get('cup')->podium, 0, 2));
        self::assertNotContains($podium[0], $event->podium);
        self::assertCount(3, $event->podium);
    }

    public function testWhenEveryoneLeftNoRoundIsLeft(): void
    {
        $service = $this->started(['a', 'b'], 2);
        $service->withdraw('cup', 'a');
        $service->withdraw('cup', 'b');
        $service->publishRound('cup', $service->prepareRound('cup'));

        $this->expectException(RoundRefused::class);
        $this->expectExceptionMessageIs('The tournament has no round left to play.');
        $service->prepareRound('cup');
    }

    /**
     * @param list<string>|null $order
     */
    private function service(?array $order = null): Tournaments
    {
        return new Tournaments(new InMemoryTournamentStore(), seeding: FixedSeeding::inOrder($order ?? ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']));
    }

    /**
     * @param list<string> $participants
     */
    private function swiss(array $participants, int $rounds = 1): Tournament
    {
        return new Tournament('cup', 'seed', $participants, [Stage::swiss('swiss', $rounds)]);
    }

    /**
     * A Swiss tournament started, its first round published.
     *
     * @param list<string> $participants
     */
    private function started(array $participants, int $rounds = 1): Tournaments
    {
        $service = $this->service();
        $service->start($this->swiss($participants, $rounds));
        $service->publishRound('cup', $service->prepareRound('cup'));

        return $service;
    }

    private function playAll(Tournaments $service, Round $round, Result $result): void
    {
        foreach ($round->pairings as $pairing) {
            if (!$pairing->isBye()) {
                $service->record('cup', $round->key($pairing->table), $result, 'org');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tables(Round $round): array
    {
        return array_map(static fn (Pairing $pairing): string => null === $pairing->second ? $pairing->first : $pairing->first.'-'.$pairing->second, $round->pairings);
    }

    /**
     * @param list<BracketMatch> $matches
     *
     * @return list<string>
     */
    private function labels(array $matches): array
    {
        $labels = [];

        foreach ($matches as $match) {
            $labels[] = $match->first->label();
            $labels[] = $match->second->label();
        }

        return $labels;
    }
}
