<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\View;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A clock at a moment, to show the table: whose turn it is, whether it is paused or stopped, when the turn in progress
 * runs out of time — the moment an application checks the clock again — each player's time, and the version read.
 */
final readonly class ClockView
{
    /**
     * @param list<PlayerTime> $players in seat order
     */
    public function __construct(
        public string $clock,
        public ?string $current,
        public bool $paused,
        public bool $stopped,
        public ?DateTimeImmutable $expiresAt,
        public array $players,
        public int $version,
    ) {
    }

    /**
     * The view as plain data, the moment of expiry in RFC 3339 with milliseconds.
     *
     * @return array{clock: string, current: ?string, paused: bool, stopped: bool, expiresAt: ?string, players: list<array{player: string, remaining: int, overtime: int, turns: int, spent: int, running: bool, out: bool, onVacation: bool}>, version: int}
     */
    public function toArray(): array
    {
        return [
            'clock' => $this->clock,
            'current' => $this->current,
            'paused' => $this->paused,
            'stopped' => $this->stopped,
            'expiresAt' => $this->expiresAt?->format(DateTimeInterface::RFC3339_EXTENDED),
            'players' => array_map(static fn (PlayerTime $player): array => $player->toArray(), $this->players),
            'version' => $this->version,
        ];
    }
}
