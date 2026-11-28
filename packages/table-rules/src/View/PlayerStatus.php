<?php

declare(strict_types=1);

namespace Tilttek\TableRules\View;

use DateTimeImmutable;
use Tilttek\TableRules\AcceptanceState;
use Tilttek\TableRules\Decision;
use Tilttek\TableRules\Diff\RulebookDiff;

/**
 * Where a player stands with a table's rules, to show the player or the table: the state, the version the player last
 * answered and how, and what changed since that version.
 */
final readonly class PlayerStatus
{
    /**
     * @param ?int          $currentVersion  the number of the version that holds, null before the first
     * @param ?int          $answeredVersion the number of the version the player last answered, null when never
     * @param ?RulebookDiff $changes         what changed from the version answered to the one that holds, null when the
     *                                       player answered none or answered the one that holds
     */
    public function __construct(
        public string $player,
        public AcceptanceState $state,
        public ?int $currentVersion = null,
        public ?int $answeredVersion = null,
        public ?Decision $decision = null,
        public ?DateTimeImmutable $decidedAt = null,
        public ?RulebookDiff $changes = null,
    ) {
    }

    /**
     * Whether the player has the rules that hold to answer: pending, or accepted an earlier version.
     */
    public function mustAnswer(): bool
    {
        return AcceptanceState::Pending === $this->state || AcceptanceState::Outdated === $this->state;
    }

    /**
     * @return array{player: string, state: string, mustAnswer: bool, currentVersion: ?int, answeredVersion: ?int, decision: ?string, decidedAt: ?string, changes: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'player' => $this->player,
            'state' => $this->state->value,
            'mustAnswer' => $this->mustAnswer(),
            'currentVersion' => $this->currentVersion,
            'answeredVersion' => $this->answeredVersion,
            'decision' => $this->decision?->value,
            'decidedAt' => $this->decidedAt?->format(\DATE_ATOM),
            'changes' => $this->changes?->toArray(),
        ];
    }
}
