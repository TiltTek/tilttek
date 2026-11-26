<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;

/**
 * A member's prediction on a question: their pick, and when they made it last. The moment is kept in UTC.
 */
final readonly class Prediction
{
    public DateTimeImmutable $madeAt;

    public function __construct(
        public PredictionId $id,
        public QuestionKey $question,
        public ResourceReference $member,
        public Pick $pick,
        DateTimeImmutable $madeAt,
    ) {
        $this->madeAt = $madeAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The same prediction with another pick: its identifier stays.
     */
    public function withPick(Pick $pick, DateTimeImmutable $madeAt): self
    {
        return new self($this->id, $this->question, $this->member, $pick, $madeAt);
    }
}
