<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\Record\HintTaken;
use Tilttek\PuzzleBuilder\Scenario;
use Tilttek\PuzzleBuilder\Unlock;

/**
 * An escape room: its scenario of puzzles, the time a team has, what a hint and a skipped puzzle cost, and the size of
 * a team.
 *
 * The time a hint costs is the hint's own penalty when it has one, the room's otherwise; it is added to the final time,
 * not taken from the countdown.
 */
final readonly class Room
{
    public const int MIN_DURATION = 60;

    public const int MAX_DURATION = 86_400;

    public const int MAX_PENALTY = 3_600;

    public const int MAX_PLAYERS = 100;

    public const int MAX_TITLE = 200;

    private const string IDENTIFIER = '/^[a-z](?:[a-z0-9_-]{0,62}[a-z0-9])?$/D';

    /**
     * @param string $id          1 to 64 characters among `a-z`, `0-9`, inner `-` and `_`, from a letter
     * @param int    $duration    the time a team has, in seconds: 60 to 86 400
     * @param int    $hintPenalty the seconds a hint without a penalty of its own adds to the final time: 0 to 3 600
     * @param int    $skipPenalty the seconds a puzzle skipped by the host adds to the final time: 0 to 3 600
     * @param int    $minPlayers  the smallest team, 1 or more
     * @param int    $maxPlayers  the largest team, up to 100: the seats of a session
     *
     * @throws InvalidRoom when a field is out of bounds
     */
    public function __construct(
        public string $id,
        public string $title,
        public Scenario $scenario,
        public int $duration = 3_600,
        public int $hintPenalty = 0,
        public int $skipPenalty = 0,
        public int $minPlayers = 2,
        public int $maxPlayers = 6,
    ) {
        if (1 !== preg_match(self::IDENTIFIER, $id)) {
            throw InvalidRoom::because(\sprintf('The identifier of a room holds 1 to 64 characters among a-z, 0-9, and inner - and _, from a letter; "%s" does not.', mb_substr($id, 0, 64)));
        }

        if ('' === trim($title) || mb_strlen($title, 'UTF-8') > self::MAX_TITLE) {
            throw InvalidRoom::because(\sprintf('The title of a room holds 1 to %d characters.', self::MAX_TITLE));
        }

        if ($duration < self::MIN_DURATION || $duration > self::MAX_DURATION) {
            throw InvalidRoom::because(\sprintf('A room lasts %d to %d seconds.', self::MIN_DURATION, self::MAX_DURATION));
        }

        if ($hintPenalty < 0 || $hintPenalty > self::MAX_PENALTY || $skipPenalty < 0 || $skipPenalty > self::MAX_PENALTY) {
            throw InvalidRoom::because(\sprintf('A penalty is 0 to %d seconds.', self::MAX_PENALTY));
        }

        if ($minPlayers < 1 || $minPlayers > $maxPlayers || $maxPlayers > self::MAX_PLAYERS) {
            throw InvalidRoom::because(\sprintf('A team holds 1 to %d players, the fewest no more than the most.', self::MAX_PLAYERS));
        }
    }

    /**
     * A room whose puzzles open one after the other: the first is open, each next one opens when the one before is
     * solved, and the last ends the game.
     *
     * @param list<Puzzle> $puzzles in the order they are played, 1 or more
     *
     * @throws InvalidRoom     when a field of the room is out of bounds
     * @throws InvalidScenario when no puzzle is given
     */
    public static function chain(string $id, string $title, array $puzzles, int $duration = 3_600, int $hintPenalty = 0, int $skipPenalty = 0, int $minPlayers = 2, int $maxPlayers = 6): self
    {
        $chained = [];
        $before = null;

        foreach ($puzzles as $puzzle) {
            $chained[] = $puzzle->withUnlock(null === $before ? Unlock::open() : Unlock::after([$before]));
            $before = $puzzle->id;
        }

        return new self($id, $title, new Scenario($id, $chained, $before), $duration, $hintPenalty, $skipPenalty, $minPlayers, $maxPlayers);
    }

    /**
     * The seconds a hint revealed adds to the final time.
     */
    public function penaltyOf(HintTaken $hint): int
    {
        $own = $this->scenario->puzzle($hint->puzzle)->hints[$hint->tier - 1]->penalty;

        return $own > 0 ? $own : $this->hintPenalty;
    }
}
