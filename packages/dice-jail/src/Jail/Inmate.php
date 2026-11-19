<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Jail;

use DateTimeImmutable;

/**
 * A die in jail: the event that put it there, read as its sentence.
 */
final readonly class Inmate
{
    public function __construct(
        public JailEvent $jailing,
    ) {
    }

    public function die(): string
    {
        return $this->jailing->die;
    }

    public function since(): DateTimeImmutable
    {
        return $this->jailing->at;
    }

    /** Whether the die is still serving its sentence at that moment: it has no end, or ends later. */
    public function isServing(DateTimeImmutable $at): bool
    {
        return !$this->jailing->until instanceof DateTimeImmutable || $at < $this->jailing->until;
    }
}
