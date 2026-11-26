<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\View;

/**
 * One player's time at a moment, in milliseconds: what is left — below zero in overtime — the overtime, the turns ended
 * and the time spent, and whether their clock runs, they are out of time, or on vacation.
 */
final readonly class PlayerTime
{
    public function __construct(
        public string $player,
        public int $remaining,
        public int $overtime,
        public int $turns,
        public int $spent,
        public bool $running,
        public bool $out,
        public bool $onVacation,
    ) {
    }

    /**
     * @return array{player: string, remaining: int, overtime: int, turns: int, spent: int, running: bool, out: bool, onVacation: bool}
     */
    public function toArray(): array
    {
        return [
            'player' => $this->player,
            'remaining' => $this->remaining,
            'overtime' => $this->overtime,
            'turns' => $this->turns,
            'spent' => $this->spent,
            'running' => $this->running,
            'out' => $this->out,
            'onVacation' => $this->onVacation,
        ];
    }
}
