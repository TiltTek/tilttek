<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;

/**
 * When a staged reveal happens: at a moment — the start of the session, a countdown's end —, which a release run by
 * the application's scheduler reveals once it has come; or on a cue the game master gives during play — the party
 * opens the crypt —, under a key the application chooses.
 */
final readonly class Trigger
{
    private function __construct(
        public ?DateTimeImmutable $at,
        public ?string $cue,
    ) {
    }

    public static function at(DateTimeImmutable $at): self
    {
        return new self($at, null);
    }

    /**
     * @throws Exception\InvalidHandout
     */
    public static function cue(string $cue): self
    {
        return new self(null, Text::key($cue, 'a cue'));
    }
}
