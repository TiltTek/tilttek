<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Vote;

/**
 * The vote on the games of a night, counted and explained: how many members voted, what each game received and its
 * share, and how the vote ended. A tie is shown as a tie: the host chooses between the games that share the most
 * votes.
 */
final readonly class VoteResult
{
    /**
     * @param int          $votes   the members who voted
     * @param list<Tally>  $tallies every game proposed, the most votes first, then in the order they were proposed
     * @param list<string> $leaders the games with the most votes, in the order they were proposed; none without a vote
     */
    private function __construct(
        public int $votes,
        public array $tallies,
        public array $leaders,
        public VoteOutcome $outcome,
    ) {
    }

    /**
     * @param list<string>          $candidates the games proposed, in their order
     * @param array<string, string> $ballots    the game each member voted for, by member
     */
    public static function count(array $candidates, array $ballots): self
    {
        $votes = \count($ballots);
        $received = array_fill_keys($candidates, 0);

        foreach ($ballots as $game) {
            if (isset($received[$game])) {
                ++$received[$game];
            }
        }

        $tallies = [];

        foreach ($candidates as $game) {
            $tallies[] = new Tally($game, $received[$game], self::percent($received[$game], $votes));
        }

        usort($tallies, static fn (Tally $a, Tally $b): int => $b->votes <=> $a->votes);

        $best = $tallies[0] ?? null;
        $leaders = [];

        if ($best instanceof Tally && 0 !== $best->votes) {
            foreach ($tallies as $tally) {
                if ($tally->votes === $best->votes) {
                    $leaders[] = $tally->game;
                }
            }
        }

        $outcome = match (\count($leaders)) {
            0 => VoteOutcome::NoVotes,
            1 => VoteOutcome::Winner,
            default => VoteOutcome::Tie,
        };

        return new self($votes, $tallies, $leaders, $outcome);
    }

    /**
     * The game that won, or `null` without a vote or on a tie.
     */
    public function winner(): ?string
    {
        return VoteOutcome::Winner === $this->outcome ? $this->leaders[0] : null;
    }

    private static function percent(int $part, int $whole): int
    {
        return 0 === $whole ? 0 : intdiv(200 * $part + $whole, 2 * $whole);
    }
}
