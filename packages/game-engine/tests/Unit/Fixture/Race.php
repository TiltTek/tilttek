<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit\Fixture;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\Statistics;
use Tilttek\GameEngine\TurnOrder;

/**
 * A race to a target, one move per turn: `race.roll` advances by a die, `race.step` by 1 to 3 steps without chance,
 * `race.taunt` at any time. Each seat draws a secret at the setup, which only it sees. A player may prefer to see the
 * positions from the leader down, and the outcome reports the rounds played and each seat's position.
 */
final class Race implements Game
{
    public int $setups = 0;

    private readonly GameDefinition $definition;

    public function __construct(private readonly bool $stepsDraw = false, string $version = '1', bool $undo = true)
    {
        $this->definition = new GameDefinition(
            'race',
            [new Phase('race', TurnOrder::Sequential, 1)],
            minimumSeats: 2,
            maximumSeats: 4,
            options: [Option::integer('target', 20, 10, 100), Option::choice('track', 'short', ['short', 'long'])],
            outOfTurn: ['race.taunt'],
            undo: $undo,
            version: $version,
            preferences: [Option::boolean('leader-first', false)],
            statistics: [Statistic::table('rounds'), Statistic::seat('position')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function setup(GameState $state, Randomness $random): array
    {
        ++$this->setups;
        $secrets = [];

        foreach ($state->seats as $seat) {
            $secrets[$seat] = $random->int(1, 1000);
        }

        return ['positions' => array_fill_keys($state->seats, 0), 'secrets' => $secrets, 'taunts' => 0];
    }

    public function allows(GameState $state, Move $move): bool
    {
        return \in_array($move->type, ['race.roll', 'race.step', 'race.taunt', Move::END_TURN], true);
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        $data = $state->data;
        $positions = $this->positions($data);

        if ('race.taunt' === $move->type) {
            $data['taunts'] = (\is_int($data['taunts'] ?? null) ? $data['taunts'] : 0) + 1;

            return $data;
        }

        $steps = 'race.roll' === $move->type ? $random->int(1, 6) : ($move->int('steps') ?? 0);

        if ('race.step' === $move->type && ($steps < 1 || $steps > 3)) {
            throw IllegalMove::because('A step is 1 to 3.');
        }

        if ($this->stepsDraw) {
            $random->int(1, 2);
        }

        $seat = $move->seat ?? $state->turn->toAct()[0];
        $positions[$seat] += $steps;
        $data['positions'] = $positions;

        return $data;
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        $winners = array_keys(array_filter($this->positions($state->data), static fn (int $position): bool => $position >= $state->options->int('target')));

        if ([] === $winners) {
            return null;
        }

        $seats = [];

        foreach ($this->positions($state->data) as $seat => $position) {
            $seats[$seat] = ['position' => $position];
        }

        return Outcome::won(array_map(strval(...), $winners), 'target-reached', new Statistics(['rounds' => $state->turn->round], $seats));
    }

    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        $data = $state->data;
        $secrets = \is_array($data['secrets'] ?? null) ? $data['secrets'] : [];
        $data['secrets'] = null === $seat ? [] : array_intersect_key($secrets, [$seat => true]);

        if ($preferences->bool('leader-first')) {
            $positions = $this->positions($data);
            arsort($positions);
            $data['positions'] = $positions;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, int>
     */
    private function positions(array $data): array
    {
        $positions = [];

        foreach (\is_array($data['positions'] ?? null) ? $data['positions'] : [] as $seat => $position) {
            $positions[(string) $seat] = \is_int($position) ? $position : 0;
        }

        return $positions;
    }
}
