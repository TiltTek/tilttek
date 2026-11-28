<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

use Psr\Clock\ClockInterface;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameNight\Contract\NightStore;
use Tilttek\GameNight\Contract\PlayJournal;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Exception\NightNotFound;
use Tilttek\GameNight\Exception\NightRefused;

/**
 * Sends the games played at a night to the play log, once the night has started: each player is a confirmed member of
 * the night, and the guests are the guests they brought. It keeps nothing: the play log does.
 */
final readonly class NightPlays
{
    public function __construct(
        private NightStore $store,
        private ClockInterface $clock,
        private Catalog $catalog,
        private PlayJournal $journal,
    ) {
    }

    /**
     * @param list<string> $players the confirmed members who played
     * @param int          $guests  the guests of those members who played
     * @param ?int         $minutes the minutes the game lasted, when known
     *
     * @throws InvalidNight  when a value is out of its shape or its bounds
     * @throws NightNotFound when no night has the identifier
     * @throws EntryNotFound when the game is not in the catalogue
     * @throws NightRefused  when the night is not published or has not started, the table is not one of it, a player
     *                       is not confirmed, or the guests are more than the players brought
     */
    public function record(string $night, string $table, string $game, array $players, int $guests = 0, ?int $minutes = null): PlayedGame
    {
        $kept = $this->store->night($night);
        $now = $this->clock->now();
        $play = new PlayedGame($night, $table, $game, $players, $guests, $now, $minutes);

        if (NightStatus::Published !== $kept->status || $now < $kept->startsAt) {
            throw NightRefused::because('The games of a night are recorded once it has started, unless it is cancelled.');
        }

        if (!$kept->table($table) instanceof Table) {
            throw NightRefused::because(\sprintf('The table %s is not one of the night.', $table));
        }

        $brought = 0;

        foreach ($players as $player) {
            $registration = $kept->registration($player);

            if (!$registration instanceof Registration || !$registration->isConfirmed()) {
                throw NightRefused::because(\sprintf('The member %s holds no place at the night.', $player));
            }

            $brought += $registration->places() - 1;
        }

        if ($guests > $brought) {
            throw NightRefused::because('The guests who played are guests the players brought.');
        }

        $this->catalog->get($game);
        $this->journal->record($play);

        return $play;
    }
}
