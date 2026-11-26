<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Tilttek\TurnClock\Contract\ClockObserver;
use Tilttek\TurnClock\Contract\ClockStore;
use Tilttek\TurnClock\Event\ClockEvent;
use Tilttek\TurnClock\Event\ClockPaused;
use Tilttek\TurnClock\Event\ClockResumed;
use Tilttek\TurnClock\Event\ClockStarted;
use Tilttek\TurnClock\Event\ClockStopped;
use Tilttek\TurnClock\Event\TimeAdjusted;
use Tilttek\TurnClock\Event\TimeExpired;
use Tilttek\TurnClock\Event\TurnEnded;
use Tilttek\TurnClock\Event\TurnStarted;
use Tilttek\TurnClock\Event\VacationDeclared;
use Tilttek\TurnClock\Exception\ClockConflict;
use Tilttek\TurnClock\Exception\ClockNotFound;
use Tilttek\TurnClock\Exception\ClockRefused;
use Tilttek\TurnClock\Exception\InvalidClock;
use Tilttek\TurnClock\Internal\Identifier;
use Tilttek\TurnClock\Internal\Millis;
use Tilttek\TurnClock\View\ClockView;
use Tilttek\TurnClock\View\PlayerTime;

/**
 * The service of the turn clocks: it creates a clock for a table, starts it, passes the turn from one player to the
 * next, pauses and resumes it, records vacations, adds or takes time, checks whether the turn in progress ran out, and
 * stops it. It reads from the store, changes, keeps, then tells the observer what happened, each fact once. It keeps
 * no state between calls: everything is in the store, and the time comes from the PSR-20 clock.
 *
 * The service trusts its caller to say who may pause, adjust or stop a clock; a turn is passed only by naming the player
 * who holds it, so that two presses never pass two turns.
 */
final readonly class TurnClock
{
    public function __construct(
        private ClockStore $store,
        private ClockInterface $clock,
        private ?ClockObserver $observer = null,
    ) {
    }

    /**
     * @throws InvalidClock  when the identifier is not one
     * @throws ClockNotFound when no clock has it
     */
    public function clock(string $id): TableClock
    {
        return $this->store->clock(Identifier::check($id, 'a clock')) ?? throw ClockNotFound::clock($id);
    }

    /**
     * Creates a clock, not started: every player has the control's budget, or the budget given for them.
     *
     * @param list<string>       $players 1 to 20, in seat order
     * @param array<string, int> $budgets the budgets of some players, in milliseconds, by player
     *
     * @throws InvalidClock  when an identifier is not one, a player comes twice, the players are too few or too
     *                       many, or a budget is out of its bounds or for a player not on the clock
     * @throws ClockRefused  when a clock has the identifier
     * @throws ClockConflict when a clock of the identifier was kept meanwhile
     */
    public function create(string $id, array $players, TimeControl $control, array $budgets = []): TableClock
    {
        foreach (array_keys($budgets) as $player) {
            if (!\in_array($player, $players, true)) {
                throw InvalidClock::because(\sprintf('The budget of %s is for a player not on the clock.', $player));
            }
        }

        $clock = new TableClock($id, $control, array_map(static fn (string $player): PlayerClock => new PlayerClock($player, $budgets[$player] ?? $control->budget), $players));
        if ($this->store->clock($id) instanceof TableClock) {
            throw ClockRefused::because(\sprintf('A clock has the identifier %s.', $id));
        }

        return $this->store->save($clock);
    }

    /**
     * Starts the clock: the first player's turn runs, the first in seat order by default.
     *
     * @throws ClockNotFound when no clock or player has the identifier
     * @throws ClockRefused  when the clock is started or stopped, or the player is out of time
     * @throws ClockConflict when the clock changed since it was read
     */
    public function start(string $id, ?string $first = null): TableClock
    {
        $before = $this->clock($id);
        if ($before->isStarted() || $before->stopped) {
            throw ClockRefused::because(\sprintf('The clock %s is started already.', $before->id));
        }

        $player = $before->player($first ?? $before->players[0]->id) ?? throw ClockNotFound::player($before->id, (string) $first);
        if ($before->isOut($player)) {
            throw ClockRefused::because(\sprintf('The player %s is out of time.', $player->id));
        }

        $kept = $this->store->save($this->turnOf($before, $player, $this->clock->now()));
        $this->tellAll([new ClockStarted($kept->id), new TurnStarted($kept->id, $player->id)]);

        return $kept;
    }

    /**
     * Ends the turn of the player holding it and starts the next one's: the player after them in seat order who is not
     * out of time, or the player named. The time of the turn is charged and the increment given; when every player is
     * out of time, the clock stops. A turn passed while the clock is paused starts paused.
     *
     * @throws ClockNotFound when no clock or player has the identifier
     * @throws ClockRefused  when the clock is not running a turn, the player does not hold it, or the player named is
     *                       them or out of time
     * @throws ClockConflict when the clock changed since it was read
     */
    public function pass(string $id, string $player, ?string $next = null): TableClock
    {
        $before = $this->running($id);
        if ($player !== $before->current) {
            throw ClockRefused::because(\sprintf('The player %s does not hold the turn.', $player));
        }

        $named = null === $next ? null : ($before->player($next) ?? throw ClockNotFound::player($before->id, $next));
        if ($named instanceof PlayerClock && ($named->id === $player || $before->isOut($named))) {
            throw ClockRefused::because(\sprintf('The player %s cannot take the turn.', $named->id));
        }

        return $this->passOn($before, $player, Millis::of($this->clock->now()), $named?->id);
    }

    /**
     * Checks whether the turn in progress ran out of time, for an application that calls it at `ClockView::$expiresAt`.
     * In overtime, the expiry is told once, and not to a player whose turn started below zero; when the clock forfeits,
     * the turn passes on, as `pass()` does.
     *
     * @throws ClockNotFound when no clock has the identifier
     * @throws ClockConflict when the clock changed since it was read
     */
    public function check(string $id): TableClock
    {
        $before = $this->clock($id);
        $now = Millis::of($this->clock->now());
        $player = null === $before->current || $before->stopped ? null : $before->player($before->current);
        if (!$player instanceof PlayerClock || $player->remaining - $before->charge($before->elapsed($now)) > 0) {
            return $before;
        }

        if (Overtime::Forfeit === $before->control->overtime) {
            return $this->passOn($before, $player->id, $now, null);
        }

        if ($before->expiryTold || $player->remaining <= 0) {
            return $before;
        }

        $kept = $this->store->save($before->with($before->players, $before->current, $before->turnElapsed, $before->runningSince, true, false));
        $this->tell(new TimeExpired($kept->id, $player->id));

        return $kept;
    }

    /**
     * Pauses the clock: no time counts until it resumes. Pausing it again changes nothing.
     *
     * @throws ClockNotFound when no clock has the identifier
     * @throws ClockRefused  when the clock is not running a turn
     * @throws ClockConflict when the clock changed since it was read
     */
    public function pause(string $id): TableClock
    {
        $before = $this->running($id);
        if ($before->isPaused()) {
            return $before;
        }

        $kept = $this->store->save($before->with($before->players, $before->current, $before->elapsed(Millis::of($this->clock->now())), null, $before->expiryTold, false));
        $this->tell(new ClockPaused($kept->id));

        return $kept;
    }

    /**
     * Runs a paused clock again. Resuming a clock that runs changes nothing.
     *
     * @throws ClockNotFound when no clock has the identifier
     * @throws ClockRefused  when the clock is not running a turn
     * @throws ClockConflict when the clock changed since it was read
     */
    public function resume(string $id): TableClock
    {
        $before = $this->running($id);
        if (!$before->isPaused()) {
            return $before;
        }

        $kept = $this->store->save($before->with($before->players, $before->current, $before->turnElapsed, $this->clock->now(), $before->expiryTold, false));
        $this->tell(new ClockResumed($kept->id));

        return $kept;
    }

    /**
     * Records a player's vacation: while it lasts, their clock does not run. One already begun counts from now; the
     * vacations already over are forgotten.
     *
     * @throws ClockNotFound when no clock or player has the identifier
     * @throws ClockRefused  when the clock is stopped, or the vacation is over
     * @throws InvalidClock  when it overlaps another of the player's vacations, or the player would keep more than 8
     * @throws ClockConflict when the clock changed since it was read
     */
    public function declareVacation(string $id, string $player, Vacation $vacation): TableClock
    {
        $before = $this->open($id);
        $target = $before->player($player) ?? throw ClockNotFound::player($before->id, $player);
        $now = $this->clock->now();
        $millis = Millis::of($now);
        if (Millis::of($vacation->until) <= $millis) {
            throw ClockRefused::because('A vacation is declared before it is over.');
        }

        $kept = array_filter($target->vacations, static fn (Vacation $one): bool => Millis::of($one->until) > $millis);
        $changed = $before->withPlayer($target->withVacations([...$kept, $vacation]));
        if ($target->id === $before->current && !$before->isPaused()) {
            $changed = $changed->with($changed->players, $changed->current, $before->elapsed($millis), $now, $changed->expiryTold, false);
        }

        $saved = $this->store->save($changed);
        $this->tell(new VacationDeclared($saved->id, $target->id));

        return $saved;
    }

    /**
     * Adds time to a player's budget, or takes it away with a negative delta; a player out of time given time back
     * plays again. When the clock forfeits, a budget goes no lower than zero.
     *
     * @param int $delta milliseconds, not zero, at most 31 days either way
     *
     * @throws ClockNotFound when no clock or player has the identifier
     * @throws ClockRefused  when the clock is stopped
     * @throws InvalidClock  when the delta is zero or out of its bounds
     * @throws ClockConflict when the clock changed since it was read
     */
    public function adjust(string $id, string $player, int $delta): TableClock
    {
        if (0 === $delta || abs($delta) > TimeControl::MAX_BUDGET) {
            throw InvalidClock::because('A change of time is not zero, and at most 31 days either way.');
        }

        $before = $this->open($id);
        $target = $before->player($player) ?? throw ClockNotFound::player($before->id, $player);
        $remaining = $target->remaining + $delta;
        $remaining = Overtime::Forfeit === $before->control->overtime ? max(0, $remaining) : $remaining;

        $changed = $before->withPlayer($target->withRemaining($remaining));
        if ($target->id === $before->current && $changed->remaining($target->id, Millis::of($this->clock->now())) > 0) {
            $changed = $changed->with($changed->players, $changed->current, $changed->turnElapsed, $changed->runningSince, false, false);
        }

        $kept = $this->store->save($changed);
        $this->tell(new TimeAdjusted($kept->id, $target->id, $delta));

        return $kept;
    }

    /**
     * Stops the clock: the time of the turn in progress is charged, without ending the turn, and the clock changes no
     * more.
     *
     * @throws ClockNotFound when no clock has the identifier
     * @throws ClockRefused  when the clock is stopped
     * @throws ClockConflict when the clock changed since it was read
     */
    public function stop(string $id): TableClock
    {
        $before = $this->open($id);
        [$charged, $events] = null === $before->current ? [$before, []] : $this->charge($before, Millis::of($this->clock->now()), false);
        $kept = $this->store->save($charged->with($charged->players, $charged->current, 0, null, $charged->expiryTold, true));
        $this->tellAll([...$events, new ClockStopped($kept->id)]);

        return $kept;
    }

    /**
     * The clock now, to show the table.
     *
     * @throws ClockNotFound when no clock has the identifier
     */
    public function view(string $id): ClockView
    {
        $clock = $this->clock($id);
        $now = Millis::of($this->clock->now());
        $running = $clock->isStarted() && !$clock->isPaused() && !$clock->stopped;
        $players = [];
        foreach ($clock->players as $player) {
            $remaining = $clock->remaining($player->id, $now);
            $onVacation = array_any($player->vacations, static fn (Vacation $vacation): bool => Millis::of($vacation->from) <= $now && $now < Millis::of($vacation->until));
            $players[] = new PlayerTime(
                $player->id,
                $remaining,
                max(0, -$remaining),
                $player->turns,
                $player->spent,
                $running && $player->id === $clock->current,
                $clock->isOut($player->withRemaining($remaining)),
                $onVacation,
            );
        }

        return new ClockView($clock->id, $clock->current, $clock->isPaused(), $clock->stopped, $running ? $this->expiresAt($clock, $now) : null, $players, $clock->version);
    }

    /**
     * When the turn in progress runs out of time, the current player's vacations skipped: now when it has run out.
     */
    private function expiresAt(TableClock $clock, int $now): DateTimeImmutable
    {
        $player = $clock->player((string) $clock->current) ?? throw ClockNotFound::player($clock->id, (string) $clock->current);
        $elapsed = $clock->elapsed($now);
        $left = $player->remaining - $clock->charge($elapsed);
        if (Mode::Delay === $clock->control->mode) {
            $left += max(0, $clock->control->increment - $elapsed);
        }

        $left = max(0, $left);
        $at = $now;
        foreach ($player->vacations as $vacation) {
            $gap = max(0, min($left, Millis::of($vacation->from) - $at));
            $at += $gap;
            $left -= $gap;

            if ($left > 0) {
                $at = max($at, Millis::of($vacation->until));
            }
        }

        return Millis::at($at + $left);
    }

    /**
     * Charges the turn in progress, ends it, and starts the next player's, or stops the clock when every one is out.
     */
    private function passOn(TableClock $before, string $player, int $now, ?string $named): TableClock
    {
        [$charged, $events] = $this->charge($before, $now, true);
        $next = null === $named ? $this->nextAfter($charged, $player) : $charged->player($named);
        if (!$next instanceof PlayerClock) {
            $kept = $this->store->save($charged->with($charged->players, $player, 0, null, $charged->expiryTold, true));
            $this->tellAll([...$events, new ClockStopped($kept->id)]);

            return $kept;
        }

        $paused = $before->isPaused();
        $started = $this->turnOf($charged, $next, Millis::at($now));
        if ($paused) {
            $started = $started->with($started->players, $started->current, 0, null, false, false);
        }

        $kept = $this->store->save($started);
        $this->tellAll([...$events, new TurnStarted($kept->id, $next->id)]);

        return $kept;
    }

    /**
     * The clock with the time of the turn in progress charged to the current player, and the events of it.
     *
     * @return array{TableClock, list<ClockEvent>}
     */
    private function charge(TableClock $clock, int $now, bool $ended): array
    {
        $player = $clock->player((string) $clock->current) ?? throw ClockNotFound::player($clock->id, (string) $clock->current);
        $elapsed = $clock->elapsed($now);
        $left = $player->remaining - $clock->charge($elapsed);
        $events = [];
        if ($left <= 0 && $player->remaining > 0 && !$clock->expiryTold) {
            $events[] = new TimeExpired($clock->id, $player->id);
        }

        if (Overtime::Forfeit === $clock->control->overtime && $left <= 0) {
            $left = 0;
        } elseif ($ended) {
            $left += match ($clock->control->mode) {
                Mode::Fischer => $clock->control->increment,
                Mode::Bronstein => min($elapsed, $clock->control->increment),
                Mode::Delay, Mode::PerTurn => 0,
            };
        }

        if ($ended) {
            $events[] = new TurnEnded($clock->id, $player->id, $elapsed);
        }

        return [$clock->withPlayer($player->charged($left, $elapsed, $ended)), $events];
    }

    /**
     * The clock with the player's turn started now: the whole budget again under `Mode::PerTurn`.
     */
    private function turnOf(TableClock $clock, PlayerClock $player, DateTimeImmutable $now): TableClock
    {
        $changed = Mode::PerTurn === $clock->control->mode ? $clock->withPlayer($player->withRemaining($player->budget)) : $clock;

        return $changed->with($changed->players, $player->id, 0, $now, false, false);
    }

    /**
     * The next player in seat order after one, the seats wrapping, who is not out of time: the same player when they are
     * the only one left, null when every one is out.
     */
    private function nextAfter(TableClock $clock, string $after): ?PlayerClock
    {
        $seats = array_map(static fn (PlayerClock $player): string => $player->id, $clock->players);
        $start = array_flip($seats)[$after];
        $count = \count($seats);
        for ($step = 1; $step <= $count; ++$step) {
            $player = $clock->players[($start + $step) % $count];
            if (!$clock->isOut($player)) {
                return $player;
            }
        }

        return null;
    }

    /**
     * @throws ClockNotFound when no clock has the identifier
     * @throws ClockRefused  when the clock is stopped
     */
    private function open(string $id): TableClock
    {
        $clock = $this->clock($id);
        if ($clock->stopped) {
            throw ClockRefused::because(\sprintf('The clock %s is stopped.', $clock->id));
        }

        return $clock;
    }

    /**
     * @throws ClockNotFound when no clock has the identifier
     * @throws ClockRefused  when the clock is not started or is stopped
     */
    private function running(string $id): TableClock
    {
        $clock = $this->open($id);
        if (!$clock->isStarted()) {
            throw ClockRefused::because(\sprintf('The clock %s is not started.', $clock->id));
        }

        return $clock;
    }

    /**
     * @param list<ClockEvent> $events
     */
    private function tellAll(array $events): void
    {
        foreach ($events as $event) {
            $this->tell($event);
        }
    }

    private function tell(ClockEvent $event): void
    {
        $this->observer?->notify($event);
    }
}
