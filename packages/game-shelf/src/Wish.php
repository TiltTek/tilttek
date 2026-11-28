<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Tilttek\GameShelf\Exception\InvalidShelf;
use Tilttek\GameShelf\Internal\Identifier;

/**
 * A game of the catalogue a member wishes: what they wish of it, and how much. A member wishes a game once; its place
 * in their own order is kept by the shelf.
 */
final readonly class Wish
{
    /** @var non-empty-list<WishKind> in the order of the enumeration */
    public array $kinds;

    /**
     * @param list<WishKind> $kinds at least one, each once
     *
     * @throws InvalidShelf when a value is out of its shape
     */
    public function __construct(public string $game, array $kinds = [WishKind::Wishlist], public ?WishLevel $level = null)
    {
        Identifier::game($game);
        $ordered = array_values(array_filter(
            WishKind::cases(),
            static fn (WishKind $kind): bool => \in_array($kind, $kinds, true),
        ));

        if ([] === $ordered || \count($ordered) !== \count($kinds)) {
            throw InvalidShelf::because('A wish holds at least one kind, each once.');
        }

        $this->kinds = $ordered;
    }

    public function has(WishKind $kind): bool
    {
        return \in_array($kind, $this->kinds, true);
    }
}
