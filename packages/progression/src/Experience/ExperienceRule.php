<?php

declare(strict_types=1);

namespace Tilttek\Progression\Experience;

use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Verbs;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Names;

/**
 * Experience for a gesture: the verbs it reads, how much experience each unit of the activity's quantity gives — or
 * each activity, when the quantity is not counted —, the cap per subject and period, and the facts the activity's
 * context must hold, such as `role: 'gm'`.
 *
 * *Fifty experience per game run as game master, two hundred at most a day*: `new ExperienceRule('runs',
 * ['game.ran'], 50, new Cap(200, Period::Day))`.
 */
final readonly class ExperienceRule
{
    public const int MAX_EXPERIENCE = 1_000_000;

    public Verbs $verbs;

    public Context $conditions;

    /**
     * @param list<string>                   $verbs   patterns such as `game.played` or `game.*`, at least one
     * @param array<string, bool|int|string> $context
     */
    public function __construct(
        public string $name,
        array $verbs,
        public int $experience,
        public ?Cap $cap = null,
        array $context = [],
        public bool $perQuantity = true,
    ) {
        Names::check($name, 'rule');

        if ([] === $verbs) {
            throw InvalidProgression::because(\sprintf('The rule "%s" reads at least one verb.', $name));
        }

        if ($experience < 1 || $experience > self::MAX_EXPERIENCE) {
            throw InvalidProgression::because(\sprintf('A rule gives 1 to %d experience; "%s" gives %d.', self::MAX_EXPERIENCE, $name, $experience));
        }

        $this->verbs = Verbs::of($verbs);
        $this->conditions = new Context($context);
    }

    /**
     * Whether the activity is one this rule rewards: its verb is read, and its context holds every condition.
     */
    public function matches(Activity $activity): bool
    {
        return $this->verbs->allows($activity->verb)
            && array_all($this->conditions->values, static fn (bool|int|string $value, string $name): bool => $activity->context->get($name) === $value);
    }

    /**
     * The experience the activity gives before the cap.
     */
    public function experienceFor(Activity $activity): int
    {
        return $this->perQuantity ? $this->experience * $activity->quantity : $this->experience;
    }
}
