<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Internal\Identifier;

/**
 * A table planned for the night: its seats, and the game and the member who leads it, when they are known.
 */
final readonly class Table
{
    public const int MAX_SEATS = 30;

    /**
     * @param int     $seats  1 to 30
     * @param ?string $game   the identifier of the game in the catalogue
     * @param ?string $leader the member who explains and leads the game
     *
     * @throws InvalidNight when an identifier is not one, or the seats are out of their bounds
     */
    public function __construct(
        public string $id,
        public int $seats,
        public ?string $game = null,
        public ?string $leader = null,
    ) {
        Identifier::check($id, 'a table');

        if ($seats < 1 || $seats > self::MAX_SEATS) {
            throw InvalidNight::because(\sprintf('A table has 1 to %d seats.', self::MAX_SEATS));
        }

        if (null !== $game) {
            Identifier::game($game);
        }

        if (null !== $leader) {
            Identifier::check($leader, 'a member');
        }
    }
}
