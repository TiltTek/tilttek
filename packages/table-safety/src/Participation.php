<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\SessionZero\Answers;

/**
 * A participant's answers at one table: their lines and veils, their consent checklist and their session zero. Each
 * changes at any moment, even during play. They are sensitive: they say what hurts a person, so they are kept for
 * this table only and leave with the participant's account.
 *
 * `$firstAnsweredAt` is the moment of the participant's first answer at the table and stays when they change their
 * answers; `$updatedAt` is that of their last change. The summaries shown to people take the participations in the
 * order of their first answer, so that changing one's answers never moves a person from one group to another.
 */
final readonly class Participation
{
    /** The moment of the participant's first answer at the table, kept when they change their answers. */
    public DateTimeImmutable $firstAnsweredAt;

    /**
     * @param ?DateTimeImmutable $firstAnsweredAt the moment of the first answer; that of the last change when null
     */
    public function __construct(
        public ResourceReference $table,
        public ResourceReference $participant,
        public LinesAndVeils $linesAndVeils,
        public ConsentChecklist $consent,
        public Answers $sessionZero,
        public DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $firstAnsweredAt = null,
    ) {
        $this->firstAnsweredAt = $firstAnsweredAt ?? $updatedAt;
    }

    public static function empty(ResourceReference $table, ResourceReference $participant, DateTimeImmutable $at): self
    {
        return new self($table, $participant, LinesAndVeils::none(), ConsentChecklist::none(), Answers::none(), $at, $at);
    }

    public function withLinesAndVeils(LinesAndVeils $linesAndVeils, DateTimeImmutable $at): self
    {
        return new self($this->table, $this->participant, $linesAndVeils, $this->consent, $this->sessionZero, $at, $this->firstAnsweredAt);
    }

    public function withConsent(ConsentChecklist $consent, DateTimeImmutable $at): self
    {
        return new self($this->table, $this->participant, $this->linesAndVeils, $consent, $this->sessionZero, $at, $this->firstAnsweredAt);
    }

    public function withSessionZero(Answers $answers, DateTimeImmutable $at): self
    {
        return new self($this->table, $this->participant, $this->linesAndVeils, $this->consent, $answers, $at, $this->firstAnsweredAt);
    }

    /**
     * The order of the summaries shown to people: by first answer, then by participant for answers given at the same
     * moment.
     */
    public static function inOrderOfFirstAnswer(self $a, self $b): int
    {
        return [$a->firstAnsweredAt, $a->participant->toString()] <=> [$b->firstAnsweredAt, $b->participant->toString()];
    }

    public function isEmpty(): bool
    {
        return $this->linesAndVeils->isEmpty() && $this->consent->isEmpty() && $this->sessionZero->isEmpty();
    }
}
