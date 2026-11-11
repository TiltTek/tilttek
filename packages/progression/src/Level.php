<?php

declare(strict_types=1);

namespace Tilttek\Progression;

/**
 * Where some experience stands on a curve: the level, the experience, the experience the level started at and the
 * experience the next one starts at — none at the last level.
 */
final readonly class Level
{
    public function __construct(
        public int $number,
        public int $experience,
        public int $floor,
        public ?int $next,
    ) {
    }

    public function isLast(): bool
    {
        return null === $this->next;
    }

    /**
     * The experience earned since the level started.
     */
    public function gained(): int
    {
        return $this->experience - $this->floor;
    }

    /**
     * The experience the level asks for, from its start to the next one; 0 at the last level.
     */
    public function span(): int
    {
        return null === $this->next ? 0 : $this->next - $this->floor;
    }

    /**
     * The experience still missing for the next level; 0 at the last level.
     */
    public function missing(): int
    {
        return null === $this->next ? 0 : $this->next - $this->experience;
    }

    /**
     * How far through the level, from 0 to 100, rounded down; 100 at the last level.
     */
    public function percent(): int
    {
        return null === $this->next ? 100 : intdiv(100 * $this->gained(), $this->span());
    }
}
