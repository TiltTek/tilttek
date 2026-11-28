<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

use DateTimeImmutable;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Internal\Identifier;

/**
 * A game played at a table of a night, as the play log receives it: the members by their identifiers, the guests as a
 * number.
 */
final readonly class PlayedGame
{
    /**
     * @param list<string> $players the members who played, at least one, each once, at most the seats of a table
     * @param int          $guests  the guests who played beside them
     * @param ?int         $minutes the minutes the game lasted, 1 to 1440, when known
     *
     * @throws InvalidNight when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $night,
        public string $table,
        public string $game,
        public array $players,
        public int $guests,
        public DateTimeImmutable $playedAt,
        public ?int $minutes = null,
    ) {
        Identifier::check($night, 'a night');
        Identifier::check($table, 'a table');
        Identifier::game($game);

        foreach ($players as $player) {
            Identifier::check($player, 'a member');
        }

        if ([] === $players || \count(array_unique($players)) !== \count($players)) {
            throw InvalidNight::because('A game is played by at least one member, each once.');
        }

        if ($guests < 0 || \count($players) + $guests > Table::MAX_SEATS) {
            throw InvalidNight::because(\sprintf('A game is played by at most %d players, guests included.', Table::MAX_SEATS));
        }

        if (null !== $minutes && ($minutes < 1 || $minutes > GameNight::MAX_MINUTES)) {
            throw InvalidNight::because(\sprintf('A game lasts 1 to %d minutes.', GameNight::MAX_MINUTES));
        }
    }
}
