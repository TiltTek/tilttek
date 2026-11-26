<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * A single-elimination bracket, made from its seeds before they are all known: the places of the first round follow
 * the seed order, the missing seeds are byes, so that the first seeds go through the first round without playing, and
 * each later match takes the winners of two matches before it. The matches are numbered round by round, from 1.
 */
final readonly class Bracket
{
    /**
     * @param list<BracketMatch> $matches by number
     */
    private function __construct(
        public array $matches,
        public int $rounds,
    ) {
    }

    /**
     * @param list<Slot> $seeds the first seed first: participants, or qualifiers not known yet
     *
     * @throws InvalidTournament when there are fewer than 2 or more than 256 seeds
     */
    public static function seeded(array $seeds): self
    {
        if (\count($seeds) < 2 || \count($seeds) > SeedOrder::MAX_SIZE) {
            throw InvalidTournament::because(\sprintf('A bracket has 2 to %d seeds.', SeedOrder::MAX_SIZE));
        }

        $size = SeedOrder::sizeFor(\count($seeds));
        $order = SeedOrder::of($size);
        $matches = [];

        for ($place = 0; $place < $size; $place += 2) {
            $one = $order[$place];
            $two = $order[$place + 1];
            $matches[] = new BracketMatch(\count($matches) + 1, 1, $seeds[$one - 1] ?? Slot::bye(), $seeds[$two - 1] ?? Slot::bye(), $one, $two);
        }

        $previous = range(1, \count($matches));
        $round = 1;

        while (\count($previous) > 1) {
            ++$round;
            $next = [];

            for ($i = 0; $i < \count($previous); $i += 2) {
                $match = new BracketMatch(\count($matches) + 1, $round, Slot::winnerOf($previous[$i]), Slot::winnerOf($previous[$i + 1]));
                $matches[] = $match;
                $next[] = $match->id;
            }

            $previous = $next;
        }

        return new self($matches, $round);
    }

    public function match(int $id): ?BracketMatch
    {
        return $this->matches[$id - 1] ?? null;
    }

    /**
     * The matches of a round.
     *
     * @return list<BracketMatch>
     */
    public function round(int $round): array
    {
        return array_values(array_filter($this->matches, static fn (BracketMatch $match): bool => $round === $match->round));
    }

    /**
     * The match the winner of a match plays next; none after the final.
     */
    public function next(int $id): ?BracketMatch
    {
        foreach ($this->matches as $match) {
            if (SlotKind::WinnerOf === $match->first->kind && $id === $match->first->match
                || SlotKind::WinnerOf === $match->second->kind && $id === $match->second->match) {
                return $match;
            }
        }

        return null;
    }

    public function final(): BracketMatch
    {
        return $this->matches[\count($this->matches) - 1];
    }
}
