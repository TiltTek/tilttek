<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

/**
 * The agreement of a table as it is shown to people, gathered without saying who gave which: for each theme, the
 * strictest boundary and the strictest consent level among the answers it holds, and how many participants' answers it
 * holds.
 *
 * It holds answers only in whole groups of `$threshold` participants, taken in the order they were given: under the
 * threshold it holds none and says only that there are not enough answers; past it, the answers of a group that is not
 * full wait for the group to fill. One more answer therefore never changes it by the answers of one person alone.
 *
 * It never filters content: `TableAgreement` keeps every answer for that, even under the threshold.
 */
final readonly class AgreementSummary
{
    /**
     * @param array<string, Boundary>     $boundaries  by theme key, in key order
     * @param array<string, ConsentLevel> $consent     by theme key, in key order
     * @param int                         $respondents how many participants' answers it holds: 0, or a multiple of the threshold
     * @param positive-int                $threshold
     */
    private function __construct(
        private array $boundaries,
        private array $consent,
        public int $respondents,
        public int $threshold,
    ) {
    }

    /**
     * @param list<Participation> $participations in the order they were given; a participation without lines, veils or
     *                                            consent is not an answer and takes no place in a group
     *
     * @throws Exception\InvalidAnswer when the threshold is under 1
     */
    public static function of(array $participations, int $threshold = RespondentThreshold::DEFAULT): self
    {
        RespondentThreshold::assert($threshold);

        $answered = array_filter($participations, static fn (Participation $one): bool => !$one->linesAndVeils->isEmpty() || !$one->consent->isEmpty());
        $respondents = RespondentThreshold::counted(\count($answered), $threshold);
        $boundaries = [];
        $consent = [];

        foreach (\array_slice($answered, 0, $respondents) as $participation) {
            foreach ($participation->linesAndVeils->all() as $theme => $boundary) {
                $kept = $boundaries[$theme] ?? null;
                $boundaries[$theme] = null !== $kept && $kept->isStricterThan($boundary) ? $kept : $boundary;
            }

            foreach ($participation->consent->all() as $theme => $level) {
                $consent[$theme] = ConsentLevel::strictest($level, ...(isset($consent[$theme]) ? [$consent[$theme]] : []));
            }
        }

        ksort($boundaries, \SORT_STRING);
        ksort($consent, \SORT_STRING);

        return new self($boundaries, $consent, $respondents, $threshold);
    }

    /**
     * Whether it holds any answer; false means "not enough answers yet".
     */
    public function hasEnoughAnswers(): bool
    {
        return $this->respondents > 0;
    }

    /**
     * @return list<string> the themes behind a line, in key order
     */
    public function lines(): array
    {
        return array_keys(array_filter($this->boundaries, static fn (Boundary $boundary): bool => Boundary::Line === $boundary));
    }

    /**
     * @return list<string> the themes behind a veil and no line, in key order
     */
    public function veils(): array
    {
        return array_keys(array_filter($this->boundaries, static fn (Boundary $boundary): bool => Boundary::Veil === $boundary));
    }

    public function boundary(string $theme): ?Boundary
    {
        return $this->boundaries[$theme] ?? null;
    }

    /**
     * @return array<string, Boundary> the strictest boundary of each theme answered, by key
     */
    public function boundaries(): array
    {
        return $this->boundaries;
    }

    public function consent(string $theme): ?ConsentLevel
    {
        return $this->consent[$theme] ?? null;
    }

    /**
     * @return array<string, ConsentLevel> the strictest level of each theme answered, by key
     */
    public function checklist(): array
    {
        return $this->consent;
    }

    /**
     * @return list<string> the themes given the level, in key order
     */
    public function themesAt(ConsentLevel $level): array
    {
        return array_keys(array_filter($this->consent, static fn (ConsentLevel $each): bool => $each === $level));
    }
}
