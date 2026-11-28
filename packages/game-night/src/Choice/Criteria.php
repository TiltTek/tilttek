<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Choice;

use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\GameNight;
use Tilttek\GameNight\Internal\Identifier;
use Tilttek\GameNight\Registration;

/**
 * What a game is chosen for: the players at the table, the minutes there are, the members whose games can be brought,
 * and the games the club or the store keeps.
 */
final readonly class Criteria
{
    public const int MAX_OWNERS = GameNight::MAX_PLACES;

    public const int MAX_LIBRARY = 500;

    public const int MAX_SUGGESTIONS = 50;

    /**
     * @param int          $players the players, guests included, 1 to 1500
     * @param int          $minutes the minutes there are, 1 to 1440
     * @param list<string> $owners  the members whose games can be brought, at most 1500, each once
     * @param list<string> $library the games of the catalogue the club or the store keeps, at most 500, each once
     * @param int          $limit   the most suggestions to give, 1 to 50
     *
     * @throws InvalidNight when a value is out of its shape or its bounds
     */
    public function __construct(
        public int $players,
        public int $minutes,
        public array $owners = [],
        public array $library = [],
        public int $limit = 10,
    ) {
        if ($players < 1 || $players > GameNight::MAX_PLACES) {
            throw InvalidNight::because(\sprintf('A game is chosen for 1 to %d players.', GameNight::MAX_PLACES));
        }

        if ($minutes < 1 || $minutes > GameNight::MAX_MINUTES) {
            throw InvalidNight::because(\sprintf('A game is chosen for 1 to %d minutes.', GameNight::MAX_MINUTES));
        }

        if ($limit < 1 || $limit > self::MAX_SUGGESTIONS) {
            throw InvalidNight::because(\sprintf('A choice gives 1 to %d suggestions.', self::MAX_SUGGESTIONS));
        }

        $this->checkList($owners, self::MAX_OWNERS, 'members whose games can be brought');
        $this->checkList($library, self::MAX_LIBRARY, 'games of the library');

        foreach ($owners as $owner) {
            Identifier::check($owner, 'a member');
        }

        foreach ($library as $game) {
            Identifier::game($game);
        }
    }

    /**
     * For the whole night: its confirmed places, its minutes and the games of its confirmed members.
     *
     * @param list<string> $library
     *
     * @throws InvalidNight when the night has no confirmed place, or the library is out of its bounds
     */
    public static function forNight(GameNight $night, array $library = [], int $limit = 10): self
    {
        return new self(
            $night->confirmedPlaces(),
            $night->minutes(),
            self::members($night->confirmed()),
            $library,
            $limit,
        );
    }

    /**
     * For one table: the places taken at it, or its seats while nobody sits there, the minutes of the night and the
     * games of the members seated at it.
     *
     * @param list<string> $library
     *
     * @throws InvalidNight when the table is not one of the night, or the library is out of its bounds
     */
    public static function forTable(GameNight $night, string $table, array $library = [], int $limit = 10): self
    {
        $seats = $night->table($table)->seats ?? throw InvalidNight::because(\sprintf('The table %s is not one of the night.', $table));
        $seated = array_values(array_filter($night->confirmed(), static fn (Registration $registration): bool => $registration->table === $table));
        $occupancy = $night->occupancy($table);

        return new self(0 === $occupancy ? $seats : $occupancy, $night->minutes(), self::members($seated), $library, $limit);
    }

    /**
     * @param list<Registration> $registrations
     *
     * @return list<string>
     */
    private static function members(array $registrations): array
    {
        return array_map(static fn (Registration $registration): string => $registration->member, $registrations);
    }

    /**
     * @param list<string> $values
     */
    private function checkList(array $values, int $max, string $what): void
    {
        if (\count($values) > $max) {
            throw InvalidNight::because(\sprintf('A choice reads at most %d %s.', $max, $what));
        }

        if (\count(array_unique($values)) !== \count($values)) {
            throw InvalidNight::because(\sprintf('A choice reads each of the %s once.', $what));
        }
    }
}
