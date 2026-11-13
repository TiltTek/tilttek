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
 */
final readonly class Participation
{
    public function __construct(
        public ResourceReference $table,
        public ResourceReference $participant,
        public LinesAndVeils $linesAndVeils,
        public ConsentChecklist $consent,
        public Answers $sessionZero,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function empty(ResourceReference $table, ResourceReference $participant, DateTimeImmutable $at): self
    {
        return new self($table, $participant, LinesAndVeils::none(), ConsentChecklist::none(), Answers::none(), $at);
    }

    public function withLinesAndVeils(LinesAndVeils $linesAndVeils, DateTimeImmutable $at): self
    {
        return new self($this->table, $this->participant, $linesAndVeils, $this->consent, $this->sessionZero, $at);
    }

    public function withConsent(ConsentChecklist $consent, DateTimeImmutable $at): self
    {
        return new self($this->table, $this->participant, $this->linesAndVeils, $consent, $this->sessionZero, $at);
    }

    public function withSessionZero(Answers $answers, DateTimeImmutable $at): self
    {
        return new self($this->table, $this->participant, $this->linesAndVeils, $this->consent, $answers, $at);
    }

    public function isEmpty(): bool
    {
        return $this->linesAndVeils->isEmpty() && $this->consent->isEmpty() && $this->sessionZero->isEmpty();
    }
}
