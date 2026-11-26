<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * Why two participants meet in a round or not, with the participant and the round the reason refers to.
 */
final readonly class Explanation
{
    public function __construct(
        public ExplanationKind $kind,
        public ?string $participant = null,
        public ?int $round = null,
    ) {
    }

    /**
     * The explanation of a round for two participants, read from its tables and the records of its stage before it.
     */
    public static function of(StageFormat $format, Round $round, Records $before, string $a, string $b): self
    {
        $tableA = $round->pairingOf($a);
        $tableB = $round->pairingOf($b);

        if ($tableA instanceof Pairing && $tableA->involves($b)) {
            return new self(ExplanationKind::Paired, null, $round->number);
        }

        if (!$tableA instanceof Pairing) {
            return new self(ExplanationKind::Absent, $a);
        }

        if ($tableA->isBye()) {
            return new self(ExplanationKind::Bye, $a, $round->number);
        }

        if (!$tableB instanceof Pairing) {
            return new self(ExplanationKind::Absent, $b);
        }

        if ($tableB->isBye()) {
            return new self(ExplanationKind::Bye, $b, $round->number);
        }

        $met = $before->of($a)->met()[$b] ?? [];

        if ([] !== $met) {
            return new self(ExplanationKind::AlreadyMet, null, $met[\count($met) - 1]);
        }

        if ($tableA->pool !== $tableB->pool) {
            return new self(ExplanationKind::DifferentPools);
        }

        if (StageFormat::Swiss !== $format) {
            return new self(ExplanationKind::Scheduled);
        }

        return $before->points($a) === $before->points($b) ? new self(ExplanationKind::OtherPairing) : new self(ExplanationKind::DifferentScores);
    }
}
