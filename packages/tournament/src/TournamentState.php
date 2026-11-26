<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * A tournament as the store keeps it: its declaration, the initial order of its participants fixed when it started, the
 * rounds published, the results kept by table, the conflicts waiting for an organiser, the participants who withdrew,
 * whether it is over, and its version.
 */
final readonly class TournamentState
{
    /**
     * @param list<string>                    $order     the participants in their initial order, fixed at the start
     * @param list<Round>                     $rounds    published, stage after stage, in number order
     * @param array<string, Submission>       $results   the result kept of each table and who entered it, by the key of
     *                                                   the table, `Round::key()`
     * @param array<string, list<Submission>> $conflicts the contrary submissions of a table, by its key
     * @param list<string>                    $withdrawn the participants who withdrew, in the order they did
     * @param list<string>                    $podium    the winner first, once the tournament is over
     */
    public function __construct(
        public Tournament $tournament,
        public array $order,
        public array $rounds = [],
        public array $results = [],
        public array $conflicts = [],
        public array $withdrawn = [],
        public array $podium = [],
        public int $version = 0,
    ) {
    }

    /**
     * @param list<Round>                     $rounds
     * @param array<string, Submission>       $results
     * @param array<string, list<Submission>> $conflicts
     * @param list<string>                    $withdrawn
     * @param list<string>                    $podium
     */
    public function with(?array $rounds = null, ?array $results = null, ?array $conflicts = null, ?array $withdrawn = null, ?array $podium = null): self
    {
        return new self($this->tournament, $this->order, $rounds ?? $this->rounds, $results ?? $this->results, $conflicts ?? $this->conflicts, $withdrawn ?? $this->withdrawn, $podium ?? $this->podium, $this->version);
    }

    public function withVersion(int $version): self
    {
        return new self($this->tournament, $this->order, $this->rounds, $this->results, $this->conflicts, $this->withdrawn, $this->podium, $version);
    }

    /**
     * The rounds published of a stage, in number order.
     *
     * @return list<Round>
     */
    public function roundsOf(string $stage): array
    {
        return array_values(array_filter($this->rounds, static fn (Round $round): bool => $stage === $round->stage));
    }

    /**
     * The round and the table of a key; none for a key unknown.
     *
     * @return array{Round, Pairing}|null
     */
    public function table(string $key): ?array
    {
        foreach ($this->rounds as $round) {
            foreach ($round->pairings as $pairing) {
                if ($key === $round->key($pairing->table)) {
                    return [$round, $pairing];
                }
            }
        }

        return null;
    }

    /**
     * The participants who withdrew, by participant.
     *
     * @return array<string, true>
     */
    public function withdrawnSet(): array
    {
        return array_fill_keys($this->withdrawn, true);
    }

    public function isOver(): bool
    {
        return [] !== $this->podium;
    }
}
