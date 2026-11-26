<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * A tournament as it is declared: its participants, by opaque identifiers, its stages in order, its scoring, the codes
 * of its tie-breakers in the order they apply, and the published seed that settles the initial order without a
 * seeding, the last ties, and nothing else. Anyone holding the seed and the results computes the same pairings and
 * standings again.
 */
final readonly class Tournament
{
    /** The most participants of a tournament. */
    public const int MAX_PARTICIPANTS = 512;

    /** The most stages of a tournament. */
    public const int MAX_STAGES = 4;

    /** The most tie-breakers a tournament declares. */
    public const int MAX_TIEBREAKERS = 8;

    /**
     * @param list<string> $participants
     * @param list<Stage>  $stages       in order; an elimination comes last
     * @param list<string> $tiebreakers  the codes of the tie-breakers, in the order they apply
     *
     * @throws InvalidTournament when an identifier, the seed, the participants, the stages or the tie-breakers are out
     *                           of range
     */
    public function __construct(
        public string $id,
        public string $seed,
        public array $participants,
        public array $stages,
        public Scoring $scoring = new Scoring(),
        public array $tiebreakers = [],
    ) {
        Identifier::check($id, 'tournament');

        if (1 !== preg_match('/^[\x21-\x7E]{1,128}$/D', $seed)) {
            throw InvalidTournament::because('The seed of a tournament is 1 to 128 printable ASCII characters, without spaces.');
        }

        if (\count($participants) < 2 || \count($participants) > self::MAX_PARTICIPANTS) {
            throw InvalidTournament::because(\sprintf('A tournament has 2 to %d participants.', self::MAX_PARTICIPANTS));
        }

        foreach ($participants as $participant) {
            Identifier::check($participant, 'participant');
        }

        if (\count(array_unique($participants)) !== \count($participants)) {
            throw InvalidTournament::because('A participant is given once.');
        }

        if ([] === $stages || \count($stages) > self::MAX_STAGES) {
            throw InvalidTournament::because(\sprintf('A tournament has 1 to %d stages.', self::MAX_STAGES));
        }

        $this->checkStages($stages, \count($participants));

        if (\count($tiebreakers) > self::MAX_TIEBREAKERS || \count(array_unique($tiebreakers)) !== \count($tiebreakers)) {
            throw InvalidTournament::because(\sprintf('A tournament declares %d tie-breakers at most, each once.', self::MAX_TIEBREAKERS));
        }
    }

    /**
     * The stage of an identifier, and its place from 0.
     *
     * @return array{Stage, int}
     *
     * @throws InvalidTournament when no stage has the identifier
     */
    public function stage(string $id): array
    {
        foreach ($this->stages as $index => $stage) {
            if ($id === $stage->id) {
                return [$stage, $index];
            }
        }

        throw InvalidTournament::because(\sprintf('The tournament has no stage %s.', $id));
    }

    /**
     * @param list<Stage> $stages
     */
    private function checkStages(array $stages, int $entrants): void
    {
        $ids = [];
        $last = \count($stages) - 1;

        foreach ($stages as $index => $stage) {
            if (\in_array($stage->id, $ids, true)) {
                throw InvalidTournament::because(\sprintf('Two stages have the identifier %s.', $stage->id));
            }

            $ids[] = $stage->id;

            if (StageFormat::RoundRobin === $stage->format && $entrants < 2 * $stage->pools) {
                throw InvalidTournament::because(\sprintf('The stage %s has pools of 2 participants at least.', $stage->id));
            }

            if ($index === $last) {
                return;
            }

            if (StageFormat::SingleElimination === $stage->format) {
                throw InvalidTournament::because('An elimination ends the tournament: it is the last stage.');
            }

            $advancing = $stage->advancing();

            if ($advancing < 2 || $advancing > $entrants) {
                throw InvalidTournament::because(\sprintf('The stage %s sends on 2 participants or more, and no more than it has.', $stage->id));
            }

            $entrants = $advancing;
        }
    }
}
