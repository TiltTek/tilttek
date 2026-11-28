<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

use Tilttek\TableRules\Exception\InvalidRules;
use Tilttek\TableRules\Internal\Identifier;

/**
 * Where a table's rules stand: the current version, the latest version that required acceptance, the last decision of
 * each player, and the revision the store keeps it at. The earlier versions stay in the store, read one at a time.
 */
final readonly class Rulebook
{
    /** The most players whose decisions a rulebook keeps. */
    public const int MAX_PLAYERS = 500;

    /** @var array<string, Acceptance> by player */
    public array $acceptances;

    /**
     * @param ?RulebookVersion          $current     the version that holds, null before the first is published
     * @param int                       $baseline    the number of the latest version that required acceptance: an
     *                                               acceptance of it or of a later version holds; 0 before the first
     * @param array<string, Acceptance> $acceptances the last decision of each player, by player
     * @param int                       $revision    the revision the store keeps the rulebook at, 0 when never kept
     *
     * @throws InvalidRules when the table is not an identifier, the baseline is not a version up to the current one, a
     *                      decision is kept under another player or answers a later version, or there are too many
     */
    public function __construct(
        public string $table,
        public ?RulebookVersion $current = null,
        public int $baseline = 0,
        array $acceptances = [],
        public int $revision = 0,
    ) {
        Identifier::check($table, 'a table');
        $highest = $current->number ?? 0;
        if ($baseline > $highest || ($current instanceof RulebookVersion) !== $baseline > 0) {
            throw InvalidRules::because('The baseline of a rulebook is a version up to the current one, and 0 before the first.');
        }

        if (\count($acceptances) > self::MAX_PLAYERS) {
            throw InvalidRules::because(\sprintf('A rulebook keeps the decisions of %d players at most.', self::MAX_PLAYERS));
        }

        foreach ($acceptances as $player => $acceptance) {
            if ($player !== $acceptance->player || $acceptance->version > $highest) {
                throw InvalidRules::because(\sprintf('The decision of %s is kept under its player and answers a published version.', $acceptance->player));
            }
        }

        $this->acceptances = $acceptances;
    }

    /**
     * The last decision of the player, or null when the player never answered or was forgotten.
     */
    public function acceptance(string $player): ?Acceptance
    {
        return $this->acceptances[$player] ?? null;
    }

    /**
     * Where the player stands with the rules that hold.
     */
    public function state(string $player): AcceptanceState
    {
        $acceptance = $this->acceptances[$player] ?? null;
        if (null === $acceptance) {
            return AcceptanceState::Pending;
        }

        if ($acceptance->version >= $this->baseline) {
            return Decision::Accepted === $acceptance->decision ? AcceptanceState::Accepted : AcceptanceState::Declined;
        }

        return Decision::Accepted === $acceptance->decision ? AcceptanceState::Outdated : AcceptanceState::Pending;
    }

    /**
     * The rulebook with another current version, baseline and decisions, at the same revision.
     *
     * @param array<string, Acceptance> $acceptances
     */
    public function with(?RulebookVersion $current, int $baseline, array $acceptances): self
    {
        return new self($this->table, $current, $baseline, $acceptances, $this->revision);
    }

    /**
     * The rulebook at another revision, for the store.
     */
    public function withRevision(int $revision): self
    {
        return new self($this->table, $this->current, $this->baseline, $this->acceptances, $revision);
    }
}
