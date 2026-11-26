<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use DateTimeImmutable;
use Tilttek\Tournament\Contract\SeedingSource;
use Tilttek\Tournament\Contract\TournamentStore;
use Tilttek\Tournament\Elimination\BracketMatch;
use Tilttek\Tournament\Elimination\BracketState;
use Tilttek\Tournament\Exception\InvalidResult;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Exception\RoundRefused;
use Tilttek\Tournament\Exception\TournamentConflict;
use Tilttek\Tournament\Exception\TournamentNotFound;
use Tilttek\Tournament\Seeding\InitialOrder;
use Tilttek\Tournament\Standings\Standings;
use Tilttek\Tournament\Swiss\SwissPairer;
use Tilttek\Tournament\Tiebreak\Tiebreakers;

/**
 * Runs tournaments: starts them, prepares each round with the reasons of its pairings for an organiser to read before
 * publishing it, takes each result once, settles conflicts, corrects past results with the standings recomputed and
 * the difference shown, takes withdrawals, and tells when the tournament has its podium.
 *
 * A stage starts when the one before it ends: its participants are the qualifiers of the one before, in the order of
 * its standings. A participant who withdraws is not paired again; a table of theirs left without a result is a forfeit.
 *
 * The service keeps nothing between calls: the store holds the state, at a version, so that two changes made at once
 * never overwrite each other. The events it returns are the caller's to dispatch.
 */
final readonly class Tournaments
{
    public function __construct(
        private TournamentStore $store,
        private Tiebreakers $tiebreakers = new Tiebreakers(),
        private ?SeedingSource $seeding = null,
        private SwissPairer $swiss = new SwissPairer(),
    ) {
    }

    /**
     * Starts a tournament: the initial order of its participants is fixed now, from the seeding source, then the seed.
     *
     * @throws InvalidTournament  when a tie-breaker is unknown
     * @throws TournamentConflict when a tournament has the identifier already
     */
    public function start(Tournament $tournament): TournamentState
    {
        $this->tiebreakers->get($tournament->tiebreakers);
        $this->store->save(new TournamentState($tournament, InitialOrder::of($tournament->participants, $tournament->seed, $this->seeding)));

        return $this->store->get($tournament->id);
    }

    /**
     * @throws TournamentNotFound when no tournament has the identifier
     */
    public function get(string $id): TournamentState
    {
        return $this->store->get($id);
    }

    /**
     * The next round, as the library pairs it, not published yet: its tables show their reasons — the bye, the sides,
     * the rematches avoided — for an organiser to read and show before publishing.
     *
     * @throws RoundRefused       when the current round has results missing or conflicts, or the tournament is over
     * @throws TournamentNotFound when no tournament has the identifier
     */
    public function prepareRound(string $id): Round
    {
        return $this->progress($this->store->get($id))->next();
    }

    /**
     * Publishes the next round: the one prepared, or the same participants seated otherwise by an organiser.
     *
     * @throws RoundRefused       when the round is not the next one, or does not seat the participants expected
     * @throws TournamentConflict when the tournament changed since it was read
     */
    public function publishRound(string $id, Round $round): Round
    {
        $state = $this->store->get($id);
        $expected = $this->progress($state)->next();

        if ($round->stage !== $expected->stage || $round->number !== $expected->number) {
            throw RoundRefused::because(\sprintf('The next round is the round %d of the stage %s.', $expected->number, $expected->stage));
        }

        if ($this->seated($round) !== $this->seated($expected)) {
            throw RoundRefused::because('A round seats each participant of the stage still in, once.');
        }

        $this->store->save($state->with(rounds: [...$state->rounds, $round]));

        return $round;
    }

    /**
     * Sets the date a published round starts, or clears it: its number and its tables stay.
     *
     * @throws InvalidTournament when the round is not published
     */
    public function reschedule(string $id, string $stage, int $number, ?DateTimeImmutable $startsAt): void
    {
        $state = $this->store->get($id);
        $rounds = $state->rounds;

        foreach ($rounds as $index => $round) {
            if ($stage === $round->stage && $number === $round->number) {
                $rounds[$index] = $round->at($startsAt);
                $this->store->save($state->with(rounds: $rounds));

                return;
            }
        }

        throw InvalidTournament::because(\sprintf('The round %d of the stage %s is not published.', $number, $stage));
    }

    /**
     * Takes a result entered for a table: the first entry is kept; the same result again changes nothing; a contrary
     * result sets the table aside until an organiser settles it with `resolve()`.
     *
     * @param string $by who entered the result: a participant or an organiser, by an opaque identifier
     *
     * @throws InvalidResult      when the table is unknown, is a bye, or a draw ends an elimination match
     * @throws TournamentConflict when the tournament changed since it was read
     */
    public function record(string $id, string $key, Result $result, string $by): Recording
    {
        Identifier::check($by, 'person');
        $state = $this->store->get($id);
        $this->check($state, $key, $result);
        $submission = new Submission($by, $result);
        $conflict = $state->conflicts[$key] ?? null;

        if (null !== $conflict) {
            foreach ($conflict as $entered) {
                if ($entered->result->equals($result)) {
                    return new Recording(RecordingStatus::Conflict);
                }
            }

            $this->store->save($state->with(conflicts: [...$state->conflicts, $key => [...$conflict, $submission]]));

            return new Recording(RecordingStatus::Conflict);
        }

        $kept = $state->results[$key] ?? null;

        if (null === $kept) {
            return $this->keep($state, $key, $submission);
        }

        if ($kept->result->equals($result)) {
            return new Recording(RecordingStatus::Unchanged);
        }

        $results = $state->results;
        unset($results[$key]);
        $this->store->save($state->with(results: $results, conflicts: [...$state->conflicts, $key => [$kept, $submission]]));

        return new Recording(RecordingStatus::Conflict);
    }

    /**
     * Settles a conflict with the result an organiser decides.
     *
     * @throws InvalidResult when the table has no conflict, or the result does not fit it
     */
    public function resolve(string $id, string $key, Result $result, string $by): Recording
    {
        Identifier::check($by, 'person');
        $state = $this->store->get($id);
        $this->check($state, $key, $result);

        if (!isset($state->conflicts[$key])) {
            throw InvalidResult::because(\sprintf('The table %s has no conflict to settle.', $key));
        }

        $conflicts = $state->conflicts;
        unset($conflicts[$key]);

        return $this->keep($state->with(conflicts: $conflicts), $key, new Submission($by, $result));
    }

    /**
     * Corrects the result of a table, of the current round or a past one: the standings of its stage are computed
     * again, and the changes are returned. The rounds paired since stay as they were published.
     *
     * @throws InvalidResult when the table has no result to correct, or the result does not fit it
     * @throws RoundRefused  when the winner of an elimination match has played the next one already
     */
    public function correct(string $id, string $key, Result $result, string $by): Recording
    {
        Identifier::check($by, 'person');
        $state = $this->store->get($id);
        [$round, $pairing] = $this->check($state, $key, $result);
        $kept = $state->results[$key] ?? throw InvalidResult::because(\sprintf('The table %s has no result to correct.', $key));

        if ($kept->result->equals($result)) {
            return new Recording(RecordingStatus::Unchanged);
        }

        [$stage, $index] = $state->tournament->stage($round->stage);
        $progress = $this->progress($state);

        if (StageFormat::SingleElimination === $stage->format) {
            $next = $progress->bracketOf($index)->next($pairing->table);

            if ($next instanceof BracketMatch) {
                foreach ($state->roundsOf($stage->id) as $later) {
                    if (null !== $later->pairing($next->id) && isset($state->results[$later->key($next->id)])) {
                        throw RoundRefused::because('The winner of this match has played the next one already: correct that one first.');
                    }
                }
            }
        }

        $before = $progress->standingsOf($stage, $index);
        $recording = $this->keep($state, $key, new Submission($by, $result));
        $after = $this->progress($this->store->get($id))->standingsOf($stage, $index);

        return new Recording($recording->status, $after->since($before), $recording->events);
    }

    /**
     * Withdraws a participant: they are not paired again, and a table of theirs left without a result is a forfeit. A
     * withdrawal may decide an elimination, and the tournament with it.
     *
     * @throws InvalidTournament when the participant is not in the tournament, or withdrew already
     */
    public function withdraw(string $id, string $participant): Recording
    {
        $state = $this->store->get($id);

        if (!\in_array($participant, $state->tournament->participants, true) || \in_array($participant, $state->withdrawn, true)) {
            throw InvalidTournament::because(\sprintf('The participant %s is not in the tournament, or withdrew already.', $participant));
        }

        [$settled, $events] = $this->progress($state->with(withdrawn: [...$state->withdrawn, $participant]))->settle();
        $this->store->save($settled);

        return new Recording(RecordingStatus::Recorded, [], $events);
    }

    /**
     * The standings of a stage — the current one by default — or of one of its pools. An elimination ranks by the round
     * each participant reached, the champion first, then by seed.
     *
     * @throws InvalidTournament when the stage or the pool is unknown
     */
    public function standings(string $id, ?string $stage = null, ?string $pool = null): Standings
    {
        $progress = $this->progress($this->store->get($id));
        [$declared, $index] = null === $stage ? $progress->current() : $progress->state->tournament->stage($stage);

        return $progress->standingsOf($declared, $index, $pool);
    }

    /**
     * The bracket of an elimination stage, the last one by default, filled with what is known: before its qualifiers
     * are known, its places show the ranks of the stage before.
     *
     * @throws InvalidTournament when the stage is not an elimination
     */
    public function bracket(string $id, ?string $stage = null): BracketState
    {
        $progress = $this->progress($this->store->get($id));
        $stages = $progress->state->tournament->stages;
        [$elimination, $index] = $progress->state->tournament->stage($stage ?? $stages[\count($stages) - 1]->id);

        if (StageFormat::SingleElimination !== $elimination->format) {
            throw InvalidTournament::because(\sprintf('The stage %s is not an elimination.', $elimination->id));
        }

        return $progress->bracketState($index);
    }

    /**
     * A participant's schedule: every round published, then the rounds of their pool the Berger table sets already,
     * rests included.
     *
     * @return list<ScheduledGame>
     */
    public function schedule(string $id, string $participant): array
    {
        $progress = $this->progress($this->store->get($id));
        $games = [];

        foreach ($progress->state->tournament->stages as $index => $stage) {
            $published = $progress->state->roundsOf($stage->id);
            $rounds = $published;
            $participants = $progress->participantsOf($index);

            if (StageFormat::RoundRobin === $stage->format && [] !== $participants) {
                foreach (\array_slice(Progress::poolRounds($stage, $participants), \count($published)) as $offset => $pairings) {
                    $rounds[] = new Round($stage->id, \count($published) + $offset + 1, $pairings);
                }
            }

            foreach ($rounds as $position => $round) {
                $pairing = $round->pairingOf($participant);

                if (null !== $pairing) {
                    $games[] = new ScheduledGame($stage->id, $round->number, $pairing->opponentOf($participant), $participant === $pairing->first, $pairing->rest, $pairing->pool, $round->startsAt, $position < \count($published));
                }
            }
        }

        return $games;
    }

    /**
     * Why two participants meet in a published round, or why they do not.
     *
     * @throws InvalidTournament when the round is not published
     */
    public function explain(string $id, string $stage, int $number, string $a, string $b): Explanation
    {
        $progress = $this->progress($this->store->get($id));
        [$declared, $index] = $progress->state->tournament->stage($stage);
        $before = [];

        foreach ($progress->state->roundsOf($stage) as $round) {
            if ($number === $round->number) {
                $records = Records::build($progress->participantsOf($index), $before, $progress->results, $progress->state->tournament->scoring);

                return Explanation::of($declared->format, $round, $records, $a, $b);
            }

            $before[] = $round;
        }

        throw InvalidTournament::because(\sprintf('The round %d of the stage %s is not published.', $number, $stage));
    }

    private function progress(TournamentState $state): Progress
    {
        return new Progress($state, $this->tiebreakers, $this->swiss);
    }

    /**
     * The round and the table of a key, once checked against the result.
     *
     * @return array{Round, Pairing}
     */
    private function check(TournamentState $state, string $key, Result $result): array
    {
        $table = $state->table($key) ?? throw InvalidResult::because(\sprintf('No published table has the key %s.', $key));

        if ($table[1]->isBye()) {
            throw InvalidResult::because('A bye has no result to enter.');
        }

        [$stage] = $state->tournament->stage($table[0]->stage);

        if (StageFormat::SingleElimination === $stage->format && Outcome::Draw === $result->outcome) {
            throw InvalidResult::because("An elimination match has a winner: a draw is decided by the game's own rules first.");
        }

        return $table;
    }

    /**
     * Keeps a result, then saves the tournament with its podium when it is over.
     */
    private function keep(TournamentState $state, string $key, Submission $submission): Recording
    {
        [$settled, $events] = $this->progress($state->with(results: [...$state->results, $key => $submission]))->settle();
        $this->store->save($settled);

        return new Recording(RecordingStatus::Recorded, [], $events);
    }

    /**
     * The participants a round seats, sorted.
     *
     * @return list<string>
     */
    private function seated(Round $round): array
    {
        $seated = [];

        foreach ($round->pairings as $pairing) {
            $seated[] = $pairing->first;

            if (null !== $pairing->second) {
                $seated[] = $pairing->second;
            }
        }

        sort($seated);

        return $seated;
    }
}
