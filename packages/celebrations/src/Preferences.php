<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

/**
 * What a member chose: whether a window celebrates what they earn — the inbox keeps every entry either way —, how much
 * it moves, and whether it plays a sound, off unless the member turns it on.
 */
final readonly class Preferences
{
    public function __construct(
        public bool $celebrate = true,
        public Motion $motion = Motion::Full,
        public bool $sound = false,
    ) {
    }

    /**
     * The motion a celebration plays with: the member's choice, reduced when the device asks for reduced motion — the
     * `prefers-reduced-motion` media feature —, and none when the member chose none.
     */
    public function motionFor(bool $deviceReducesMotion): Motion
    {
        if ($deviceReducesMotion && Motion::Full === $this->motion) {
            return Motion::Reduced;
        }

        return $this->motion;
    }
}
