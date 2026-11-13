<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Fixture;

use Tilttek\GameEngine\Contract\Game;
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
 * A race of dice, one move per turn: `roll.roll` adds a die to the seat's total, `roll.wait` adds nothing and draws
 * nothing. The first seat to reach the target wins. Each seat draws a secret number at the setup, which only it sees;
 * a player may prefer to see the totals from the leader down.
 */
final readonly class RollRace implements Game
{
    private GameDefinition $definition;

    public function __construct()
    {
        $this->definition = new GameDefinition(
            'roll-race',
            [new Phase('race', TurnOrder::Sequential, 1)],
            minimumSeats: 2,
            maximumSeats: 4,
            options: [Option::integer('target', 10, 2, 100)],
            undo: true,
            preferences: [Option::boolean('leader-first', false)],
            statistics: [Statistic::table('rounds'), Statistic::seat('total')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function setup(GameState $state, Randomness $random): array
    {
        $secrets = [];

        foreach ($state->seats as $seat) {
            $secrets[$seat] = $random->int(1, 100);
        }

        return ['totals' => array_fill_keys($state->seats, 0), 'secrets' => $secrets];
    }

    public function allows(GameState $state, Move $move): bool
    {
        return \in_array($move->type, ['roll.roll', 'roll.wait'], true) || $move->isEngine();
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        $data = $state->data;
        $totals = $this->totals($data);
        $seat = (string) $move->seat;

        if ('roll.roll' === $move->type) {
            $totals[$seat] = ($totals[$seat] ?? 0) + $random->int(1, 6);
        }

        $data['totals'] = $totals;

        return $data;
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        $totals = $this->totals($state->data);
        $winners = array_keys(array_filter($totals, static fn (int $total): bool => $total >= $state->options->int('target')));

        if ([] === $winners) {
            return null;
        }

        $seats = [];

        foreach ($totals as $seat => $total) {
            $seats[$seat] = ['total' => $total];
        }

        return Outcome::won(array_map(strval(...), $winners), 'target-reached', new Statistics(['rounds' => $state->turn->round], $seats));
    }

    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        $totals = $this->totals($state->data);
        $secrets = \is_array($state->data['secrets'] ?? null) ? $state->data['secrets'] : [];

        if ($preferences->bool('leader-first')) {
            arsort($totals);
        }

        return ['totals' => $totals, 'secret' => null === $seat ? null : ($secrets[$seat] ?? null)];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, int>
     */
    private function totals(array $data): array
    {
        $totals = [];

        foreach (\is_array($data['totals'] ?? null) ? $data['totals'] : [] as $seat => $total) {
            $totals[(string) $seat] = \is_int($total) ? $total : 0;
        }

        return $totals;
    }
}
