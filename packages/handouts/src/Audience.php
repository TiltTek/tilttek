<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use Phil\Resource\ResourceReference;
use Tilttek\Handouts\Exception\InvalidHandout;

/**
 * Whom a handout is revealed or staged to: everyone at its table, or players named by the application's references —
 * 1 to 500 of them, each kept once, in the order given.
 *
 * Who sits at the table is the application's to know: a handout revealed to everyone is visible to each player the
 * application asks about.
 */
final readonly class Audience
{
    public const int MAX_PLAYERS = 500;

    /** The key of everyone, beside the players' references written `kind:identifier`. */
    public const string EVERYONE = '*';

    /**
     * @param list<ResourceReference> $players
     */
    private function __construct(
        public array $players,
    ) {
    }

    public static function everyone(): self
    {
        return new self([]);
    }

    /**
     * @throws InvalidHandout
     */
    public static function players(ResourceReference ...$players): self
    {
        $kept = [];

        foreach ($players as $player) {
            $kept[$player->toString()] ??= $player;
        }

        if ([] === $kept || \count($kept) > self::MAX_PLAYERS) {
            throw InvalidHandout::because(\sprintf('An audience names 1 to %d players; %d are given.', self::MAX_PLAYERS, \count($kept)));
        }

        return new self(array_values($kept));
    }

    public function isEveryone(): bool
    {
        return [] === $this->players;
    }

    /**
     * The keys the audience is kept under: `*` for everyone, or each player's reference.
     *
     * @return non-empty-list<string>
     */
    public function keys(): array
    {
        if ([] === $this->players) {
            return [self::EVERYONE];
        }

        return array_map(static fn (ResourceReference $player): string => $player->toString(), $this->players);
    }

    /**
     * The players the audience is kept for, null standing for everyone.
     *
     * @return non-empty-list<?ResourceReference>
     */
    public function members(): array
    {
        return [] === $this->players ? [null] : $this->players;
    }
}
