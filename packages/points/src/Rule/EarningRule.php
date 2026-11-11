<?php

declare(strict_types=1);

namespace Tilttek\Points\Rule;

use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Verbs;
use Tilttek\Points\Exception\InvalidPoints;

/**
 * Points for a gesture: the verbs it reads, how many points each unit of the activity's quantity gives — or each
 * activity, when the quantity is not counted —, in which currency (the default one when none is named), the cap per
 * member and period, and the facts the activity's context must hold, such as `system: 'dnd5e'`.
 *
 * *Ten points per game, fifty at most a week*: `new EarningRule('games', ['game.played'], 10, cap: new Cap(50,
 * Period::Week))`.
 */
final readonly class EarningRule
{
    public const int MAX_POINTS = 1_000_000;

    public Verbs $verbs;

    public Context $conditions;

    /**
     * @param list<string>                   $verbs   patterns such as `game.played` or `game.*`, at least one
     * @param array<string, bool|int|string> $context
     */
    public function __construct(
        public string $name,
        array $verbs,
        public int $points,
        public ?string $currency = null,
        public ?Cap $cap = null,
        array $context = [],
        public bool $perQuantity = true,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidPoints::because(\sprintf('"%s" is not a rule name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name));
        }

        if ([] === $verbs) {
            throw InvalidPoints::because(\sprintf('The rule "%s" reads at least one verb.', $name));
        }

        if ($points < 1 || $points > self::MAX_POINTS) {
            throw InvalidPoints::because(\sprintf('A rule gives 1 to %d points; "%s" gives %d.', self::MAX_POINTS, $name, $points));
        }

        $this->verbs = Verbs::of($verbs);
        $this->conditions = new Context($context);
    }

    /**
     * Whether the activity is one this rule rewards: its verb is read, and its context holds every condition.
     */
    public function matches(Activity $activity): bool
    {
        if (!$this->verbs->allows($activity->verb)) {
            return false;
        }

        return array_all($this->conditions->values, static fn (bool|int|string $value, string $name): bool => $activity->context->get($name) === $value);
    }

    /**
     * The points the activity gives before the cap.
     */
    public function pointsFor(Activity $activity): int
    {
        return $this->perQuantity ? $this->points * $activity->quantity : $this->points;
    }
}
