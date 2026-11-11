<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use DateTimeImmutable;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Verbs;
use Tilttek\Badges\Exception\InvalidBadge;

/**
 * The activities a leaf reads: verbs by pattern — at least one —, the facts their context must hold, such as
 * `system: 'dnd5e'`, and a period on their moment — from `since`, included, to `until`, excluded —, for a badge of an
 * event's month.
 */
final readonly class Scope
{
    /**
     * @var list<string>
     */
    public array $patterns;

    public Verbs $verbs;

    public Context $conditions;

    /**
     * @param array<array-key, string>       $verbs
     * @param array<string, bool|int|string> $context
     */
    public function __construct(
        array $verbs,
        array $context = [],
        public ?DateTimeImmutable $since = null,
        public ?DateTimeImmutable $until = null,
    ) {
        if ([] === $verbs) {
            throw InvalidBadge::because('A criterion reads at least one verb.');
        }

        try {
            $this->verbs = Verbs::of($verbs);
            $this->conditions = new Context($context);
        } catch (InvalidActivity $invalidActivity) {
            throw InvalidBadge::because($invalidActivity->getMessage());
        }

        if ($since instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $until <= $since) {
            throw InvalidBadge::because('The period of a criterion ends after it starts.');
        }

        $this->patterns = array_values(array_unique($verbs));
    }

    public function accepts(Activity $activity): bool
    {
        return $this->verbs->allows($activity->verb)
            && (!$this->since instanceof DateTimeImmutable || $activity->occurredAt >= $this->since)
            && (!$this->until instanceof DateTimeImmutable || $activity->occurredAt < $this->until)
            && array_all($this->conditions->values, static fn (bool|int|string $value, string $name): bool => $activity->context->get($name) === $value);
    }
}
