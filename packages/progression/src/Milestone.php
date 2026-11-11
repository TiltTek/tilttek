<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Progression\Exception\InvalidProgression;

/**
 * A step of a track worth a reward: an amount of experience, or a level reached. The application gives the reward
 * when told the milestone is reached — points, an object, a title —; a milestone is reached once per subject and
 * season.
 *
 * *A reward at 1000 experience*: `new Milestone('thousand', 1000)`; *at level 10*: `new Milestone('veteran', 10,
 * level: true)`.
 */
final readonly class Milestone
{
    public function __construct(
        public string $code,
        public int $value,
        public bool $level = false,
    ) {
        Names::check($code, 'milestone');

        if ($value < ($level ? 2 : 1)) {
            throw InvalidProgression::because($level ? \sprintf('The milestone "%s" is at level 2 or more; %d is not one.', $code, $value) : \sprintf('The milestone "%s" is at 1 experience or more; %d is not an amount.', $code, $value));
        }
    }

    /**
     * The experience the milestone is reached at, on the track's curve.
     *
     * @throws InvalidProgression when the curve has no such level
     */
    public function threshold(Curve $curve): int
    {
        return $this->level ? $curve->experienceFor($this->value) : $this->value;
    }
}
