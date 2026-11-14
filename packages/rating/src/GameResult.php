<?php

declare(strict_types=1);

namespace Tilttek\Rating;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Tilttek\Rating\Exception\InvalidRating;

/**
 * The result of one game played: its stable identifier, the game, when it was played, the sides — a player alone or a
 * team — and the rank of each side. Rank 1 is first; sides with the same rank drew. A friendly result changes no
 * rating.
 *
 * A duel is two sides of one player; a game for several players, one side per player; a team game, one side per team.
 */
final readonly class GameResult
{
    /** The most sides of one result. */
    public const int MAX_SIDES = 64;

    /** The most players of one side. */
    public const int MAX_TEAM = 32;

    /** The most players of one result. */
    public const int MAX_PLAYERS = 256;

    /** @var non-empty-list<non-empty-list<string>> */
    public array $sides;

    /** @var non-empty-list<int> */
    public array $ranks;

    /**
     * @param array<array<string>> $sides the members of each side, in order
     * @param array<int>           $ranks the rank of each side, in the same order: 1 to the number of sides
     *
     * @throws InvalidRating when an identifier, a side or a rank is out of its range, or a member plays twice
     */
    public function __construct(
        public string $id,
        public string $game,
        public DateTimeImmutable $playedAt,
        array $sides,
        array $ranks,
        public bool $friendly = false,
    ) {
        Identifier::check($id, 'result');
        Identifier::check($game, 'game');

        $sides = array_values(array_map(array_values(...), $sides));
        $ranks = array_values($ranks);

        if (\count($sides) < 2 || \count($sides) > self::MAX_SIDES) {
            throw InvalidRating::because(\sprintf('A result has 2 to %d sides.', self::MAX_SIDES));
        }

        if (\count($ranks) !== \count($sides)) {
            throw InvalidRating::because('A result gives one rank to each of its sides.');
        }

        $seen = [];

        foreach ($sides as $side) {
            if ([] === $side || \count($side) > self::MAX_TEAM) {
                throw InvalidRating::because(\sprintf('A side has 1 to %d players.', self::MAX_TEAM));
            }

            foreach ($side as $member) {
                if (isset($seen[Identifier::check($member, 'member')])) {
                    throw InvalidRating::because(\sprintf('The member %s plays on one side only, once.', $member));
                }

                $seen[$member] = true;
            }
        }

        if (\count($seen) > self::MAX_PLAYERS) {
            throw InvalidRating::because(\sprintf('A result has at most %d players.', self::MAX_PLAYERS));
        }

        foreach ($ranks as $rank) {
            if ($rank < 1 || $rank > \count($sides)) {
                throw InvalidRating::because(\sprintf('A rank is 1 to the %d sides of the result.', \count($sides)));
            }
        }

        /** @var non-empty-list<non-empty-list<string>> $sides */
        $this->sides = $sides;
        $this->ranks = $ranks;
    }

    /**
     * A game of two players: the first won, unless it is a draw.
     *
     * @throws InvalidRating when an identifier is out of its range, or the two players are one
     */
    public static function duel(string $id, string $game, DateTimeImmutable $playedAt, string $first, string $second, bool $draw = false, bool $friendly = false): self
    {
        return new self($id, $game, $playedAt, [[$first], [$second]], [1, $draw ? 1 : 2], $friendly);
    }

    /**
     * A game of several players or teams, given by placings from the first: each placing lists the members — or the
     * team — who finished there together.
     *
     * @param array<array<string>> $placings
     *
     * @throws InvalidRating when a placing, a side or an identifier is out of its range
     */
    public static function placings(string $id, string $game, DateTimeImmutable $playedAt, array $placings, bool $friendly = false): self
    {
        $sides = [];
        $ranks = [];

        foreach (array_values($placings) as $place => $members) {
            foreach ($members as $member) {
                $sides[] = [$member];
                $ranks[] = $place + 1;
            }
        }

        return new self($id, $game, $playedAt, $sides, $ranks, $friendly);
    }

    /**
     * The members of every side.
     *
     * @return list<string>
     */
    public function members(): array
    {
        return array_merge(...$this->sides);
    }

    /**
     * A result as `toArray()` wrote it: what a source of results reads from its storage.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidRating when a field is missing or out of its range
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;
        $game = $data['game'] ?? null;
        $playedAt = $data['played_at'] ?? null;
        $sides = $data['sides'] ?? null;
        $ranks = $data['ranks'] ?? null;
        $friendly = $data['friendly'] ?? false;

        if (!\is_string($id) || !\is_string($game) || !\is_string($playedAt) || !\is_array($sides) || !\is_array($ranks) || !\is_bool($friendly)) {
            throw InvalidRating::because('A result has an id, a game, a played_at time, sides and ranks, and may be friendly.');
        }

        $read = [];

        foreach ($sides as $side) {
            if (!\is_array($side) || [] !== array_filter($side, static fn (mixed $member): bool => !\is_string($member))) {
                throw InvalidRating::because('A side is a list of member identifiers.');
            }

            /** @var array<string> $side */
            $read[] = $side;
        }

        if ([] !== array_filter($ranks, static fn (mixed $rank): bool => !\is_int($rank))) {
            throw InvalidRating::because('A rank is a whole number.');
        }

        /** @var array<int> $ranks */
        return new self($id, $game, self::time($playedAt), $read, $ranks, $friendly);
    }

    /**
     * The result as plain values; `fromArray()` reads them back.
     *
     * @return array{id: string, game: string, played_at: string, sides: list<list<string>>, ranks: list<int>, friendly: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'game' => $this->game,
            'played_at' => $this->playedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'sides' => $this->sides,
            'ranks' => $this->ranks,
            'friendly' => $this->friendly,
        ];
    }

    private static function time(string $value): DateTimeImmutable
    {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw InvalidRating::because('The time a game was played is an ISO 8601 date and time with its offset.');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw InvalidRating::because('The time a game was played is an ISO 8601 date and time with its offset.');
        }
    }
}
