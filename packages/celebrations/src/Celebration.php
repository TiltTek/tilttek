<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

/**
 * What the window shows next: a group of pending entries, the motion and the sound it plays with, and how many groups
 * wait after it.
 */
final readonly class Celebration
{
    public function __construct(
        public Group $group,
        public Motion $motion,
        public bool $sound,
        public int $remaining,
    ) {
    }
}
