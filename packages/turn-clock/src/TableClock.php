<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

use DateTimeImmutable;
use Tilttek\TurnClock\Exception\InvalidClock;
use Tilttek\TurnClock\Internal\Identifier;
use Tilttek\TurnClock\Internal\Millis;

/**
 * The clock of a table: its time control, its players in seat order, the player whose turn it is, the time counted in
 * that turn before the clock last started running, since when it runs — null while paused — whether the expiry of the
 * turn was told, whether it is stopped, and its version in the store.
 *
 * A player's `remaining` is what was left when their turn started: the time of the turn in progress is counted from
 * `turnElapsed` and `runningSince`, and charged when the turn ends.
 */
final readonly class TableClock
{
    /** The most players of a clock. */
    public const int MAX_PLAYERS = 20;

    /** @var non-empty-list<PlayerClock> */
    public array $players;

    /**
     * @param list<PlayerClock> $players     1 to 20, in seat order, each identifier once
     * @param ?string           $current     the player whose turn it is, or null before the start
     * @param int               $turnElapsed the milliseconds counted in the turn before `runningSince`
     *
     * @throws InvalidClock when an identifier is not one, a player comes twice, the players are too few or too many,
     *                      the current player is not one of them, or a count is below 0
     */
    public function __construct(
        public string $id,
        public TimeControl $control,
        array $players,
        public ?string $current = null,
        public int $turnElapsed = 0,
        public ?DateTimeImmutable $runningSince = null,
        public bool $expiryTold = false,
        public bool $stopped = false,
        public int $version = 0,
    ) {
        Identifier::check($id, 'a clock');
        if ([] === $players || \count($players) > self::MAX_PLAYERS) {
            throw InvalidClock::because(\sprintf('A clock has 1 to %d players.', self::MAX_PLAYERS));
        }

        $seen = [];
        foreach ($players as $player) {
            if (isset($seen[$player->id])) {
                throw InvalidClock::because(\sprintf('The player %s comes twice on the clock.', $player->id));
            }

            $seen[$player->id] = $player->id;
        }

        if (null !== $current && !isset($seen[$current])) {
            throw InvalidClock::because(\sprintf('The current player %s is not on the clock.', $current));
        }

        if ($turnElapsed < 0 || $version < 0) {
            throw InvalidClock::because('The time of a turn and the version of a clock are 0 or more.');
        }

        $this->players = $players;
    }

    /**
     * The player, or null when the clock has none of the identifier.
     */
    public function player(string $id): ?PlayerClock
    {
        foreach ($this->players as $player) {
            if ($player->id === $id) {
                return $player;
            }
        }

        return null;
    }

    public function isStarted(): bool
    {
        return null !== $this->current;
    }

    /**
     * Whether a turn is in progress and the clock does not run.
     */
    public function isPaused(): bool
    {
        return null !== $this->current && !$this->stopped && !$this->runningSince instanceof DateTimeImmutable;
    }

    /**
     * Whether the player is out of time: the clock forfeits, and their budget is spent.
     */
    public function isOut(PlayerClock $player): bool
    {
        return Overtime::Forfeit === $this->control->overtime && $player->remaining <= 0;
    }

    /**
     * The milliseconds counted in the turn in progress at a moment: its time before the clock last started, and the
     * time since, less the current player's vacations.
     */
    public function elapsed(int $now): int
    {
        $player = null === $this->current ? null : $this->player($this->current);
        if (!$player instanceof PlayerClock || !$this->runningSince instanceof DateTimeImmutable || $this->stopped) {
            return $this->turnElapsed;
        }

        return $this->turnElapsed + $player->counted(Millis::of($this->runningSince), $now);
    }

    /**
     * What the turn in progress takes from the budget once so many milliseconds are counted: all of them, or under
     * `Mode::Delay` those past the delay.
     */
    public function charge(int $elapsed): int
    {
        return Mode::Delay === $this->control->mode ? max(0, $elapsed - $this->control->increment) : $elapsed;
    }

    /**
     * What is left of a player's budget at a moment, the turn in progress counted; below zero in overtime, and at zero
     * at most for a player out of time.
     */
    public function remaining(string $player, int $now): int
    {
        $clock = $this->player($player);
        if (!$clock instanceof PlayerClock) {
            return 0;
        }

        if ($player !== $this->current || $this->stopped) {
            return $clock->remaining;
        }

        $left = $clock->remaining - $this->charge($this->elapsed($now));

        return Overtime::Forfeit === $this->control->overtime ? max(0, $left) : $left;
    }

    /**
     * @param list<PlayerClock> $players
     */
    public function with(array $players, ?string $current, int $turnElapsed, ?DateTimeImmutable $runningSince, bool $expiryTold, bool $stopped): self
    {
        return new self($this->id, $this->control, $players, $current, $turnElapsed, $runningSince, $expiryTold, $stopped, $this->version);
    }

    /**
     * The clock with one player changed.
     */
    public function withPlayer(PlayerClock $changed): self
    {
        $players = array_map(static fn (PlayerClock $one): PlayerClock => $one->id === $changed->id ? $changed : $one, $this->players);

        return $this->with($players, $this->current, $this->turnElapsed, $this->runningSince, $this->expiryTold, $this->stopped);
    }

    public function withVersion(int $version): self
    {
        return new self($this->id, $this->control, $this->players, $this->current, $this->turnElapsed, $this->runningSince, $this->expiryTold, $this->stopped, $version);
    }
}
