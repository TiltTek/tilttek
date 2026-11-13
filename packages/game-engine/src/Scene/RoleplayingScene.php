<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Scene;

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
use Tilttek\GameEngine\Transition;
use Tilttek\GameEngine\TurnOrder;

/**
 * The phases of a role-playing scene, with no rules of any system: an exploration where anyone acts, an encounter in
 * initiative order, an aftermath, and back to exploring. The first seat leads the scene — it changes the phase, sets
 * the initiative and ends the scene — unless the table turns the `leader` option off.
 *
 * Its moves are `scene.act` (`{summary}`), an action in the seat's turn, and `scene.note` (`{text}`), a note at any
 * time; `scene.end` ends the scene. Its data counts the actions of each seat, the notes and the encounters, which its
 * outcome reports as the statistics `actions` of each seat, `notes` and `encounters` of the table.
 */
final readonly class RoleplayingScene implements Game
{
    public const string NAME = 'roleplaying-scene';

    private GameDefinition $definition;

    public function __construct()
    {
        $this->definition = new GameDefinition(
            self::NAME,
            [
                new Phase('exploration'),
                new Phase('encounter', TurnOrder::Initiative),
                new Phase('aftermath'),
            ],
            [
                new Transition('start-encounter', ['exploration', 'aftermath'], 'encounter'),
                new Transition('end-encounter', ['encounter'], 'aftermath'),
                new Transition('explore', ['encounter', 'aftermath'], 'exploration'),
            ],
            minimumSeats: 1,
            maximumSeats: 32,
            options: [Option::boolean('leader', true)],
            outOfTurn: ['scene.note', 'scene.end'],
            undo: true,
            statistics: [Statistic::table('encounters'), Statistic::table('notes'), Statistic::seat('actions')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function setup(GameState $state, Randomness $random): array
    {
        return ['actions' => array_fill_keys($state->seats, 0), 'notes' => 0, 'encounters' => 0, 'ended' => false];
    }

    public function allows(GameState $state, Move $move): bool
    {
        $leads = !$state->options->bool('leader') || null === $move->seat || $move->seat === $state->seats[0];

        return match ($move->type) {
            Move::TRANSITION, Move::INITIATIVE, 'scene.end' => $leads,
            'scene.act' => null !== $move->seat,
            'scene.note' => null !== $move->text('text'),
            default => $move->isEngine(),
        };
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        $data = $state->data;

        // `allows()` lets through these three moves of the game only.
        if ('scene.act' === $move->type) {
            $actions = $this->counts($data['actions'] ?? []);
            $seat = (string) $move->seat;
            $actions[$seat] = ($actions[$seat] ?? 0) + 1;
            $data['actions'] = $actions;
        } elseif ('scene.note' === $move->type) {
            $data['notes'] = $this->count($data['notes'] ?? 0) + 1;
        } else {
            $data['ended'] = true;
        }

        return $data;
    }

    public function enter(GameState $state, Randomness $random): array
    {
        $data = $state->data;

        if ('encounter' === $state->phase) {
            $data['encounters'] = $this->count($data['encounters'] ?? 0) + 1;
        }

        return $data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        if (true !== ($state->data['ended'] ?? false)) {
            return null;
        }

        $seats = [];

        foreach ($this->counts($state->data['actions'] ?? []) as $seat => $actions) {
            $seats[$seat] = ['actions' => $actions];
        }

        return Outcome::ended('scene-ended', new Statistics(
            ['encounters' => $this->count($state->data['encounters'] ?? 0), 'notes' => $this->count($state->data['notes'] ?? 0)],
            $seats,
        ));
    }

    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        return $state->data;
    }

    /**
     * @return array<string, int>
     */
    private function counts(mixed $counts): array
    {
        $checked = [];

        foreach (\is_array($counts) ? $counts : [] as $seat => $count) {
            $checked[(string) $seat] = $this->count($count);
        }

        return $checked;
    }

    private function count(mixed $count): int
    {
        return \is_int($count) ? $count : 0;
    }
}
