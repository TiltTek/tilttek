<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * The subject the state machine of Symfony Workflow moves: the phase of one move, made for it and dropped after.
 *
 * @internal
 */
final class PhaseCursor
{
    public function __construct(private string $phase)
    {
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setPhase(string $phase, array $context = []): void
    {
        unset($context);
        $this->phase = $phase;
    }
}
