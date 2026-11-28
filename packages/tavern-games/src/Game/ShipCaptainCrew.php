<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Game;

use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\Statistics;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\DiceCup;
use Tilttek\TavernGames\Odds\CargoChase;
use Tilttek\TavernGames\Turns;

/**
 * Ship, Captain and Crew, the old tavern dice game: each seat in turn rolls five dice, up to three times, and must set
 * aside a 6 — the ship —, then a 5 — the captain —, then a 4 — the crew —, each only once the one before it is set
 * aside. With all three, the two other dice are the cargo, which the seat may roll again while rolls remain. Once
 * every seat has played, the most cargo wins; equal cargoes share the win; a seat without its crew carries none.
 *
 * Its moves are `ship-captain-crew.roll` and `ship-captain-crew.stop`, which keeps the cargo; the timeout of a turn
 * stops it. Its outcome reports the cargo of each seat as the statistic `cargo`.
 */
final readonly class ShipCaptainCrew implements TavernGame
{
    public const string NAME = 'ship-captain-crew';

    public const string ROLL = 'ship-captain-crew.roll';

    public const string STOP = 'ship-captain-crew.stop';

    /** The rolls of a turn. */
    public const int ROLLS = 3;

    /** The faces set aside, in their order: the ship, the captain, the crew. */
    public const array CREW = [6, 5, 4];

    private GameDefinition $definition;

    public function __construct(
        private DiceCup $dice = new DiceCup(),
    ) {
        $this->definition = new GameDefinition(
            self::NAME,
            [new Phase('playing')],
            minimumSeats: 2,
            maximumSeats: 8,
            statistics: [Statistic::seat('cargo')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function setup(GameState $state, Randomness $random): array
    {
        return $this->data(Turns::start($state->seats), 0, 0, [], []);
    }

    public function allows(GameState $state, Move $move): bool
    {
        [$turns, $rolls, $held] = $this->read($state->data);

        return match ($move->type) {
            self::ROLL => $turns->isTurnOf($move->seat),
            self::STOP => $turns->isTurnOf($move->seat) && 3 === $held && $rolls > 0,
            self::TIMEOUT => null === $move->seat && $turns->isTurnOf($move->text('seat')),
            default => false,
        };
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        [$turns, $rolls, $held, $dice, $cargoes] = $this->read($state->data);

        if (self::ROLL === $move->type) {
            [$held, $dice] = self::setAside($held, $this->dice->roll(5 - $held, $random));
            ++$rolls;

            if ($rolls < self::ROLLS) {
                return $this->data($turns, $rolls, $held, $dice, $cargoes);
            }
        }

        $cargoes[$turns->seat()] = 3 === $held ? array_sum($dice) : 0;

        return $this->data($turns->next(), 0, 0, [], $cargoes);
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        [, , , , $cargoes] = $this->read($state->data);

        if (\count($cargoes) < \count($state->seats)) {
            return null;
        }

        $statistics = new Statistics([], array_map(static fn (int $cargo): array => ['cargo' => $cargo], $cargoes));
        $most = max(0, ...array_values($cargoes));

        if (0 === $most) {
            return Outcome::ended('no-cargo', $statistics);
        }

        $winners = array_keys(array_filter($cargoes, static fn (int $cargo): bool => $cargo === $most));

        return Outcome::won($winners, 1 === \count($winners) ? 'most-cargo' : 'tie', $statistics);
    }

    public function toAct(GameState $state): ?string
    {
        return $state->isOver() ? null : $this->read($state->data)[0]->seat();
    }

    /**
     * Everything: the game has no secret. The seat playing, its rolls, how many of ship, captain and crew it has set
     * aside, the dice it rolled last, and the cargo of each seat that has played.
     */
    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        [$turns, $rolls, $held, $dice, $cargoes] = $this->read($state->data);

        return [
            'turn' => $state->isOver() ? null : $turns->seat(),
            'rolls' => $rolls,
            'held' => $held,
            'dice' => $dice,
            'cargo' => 3 === $held ? array_sum($dice) : null,
            'cargoes' => $cargoes,
        ];
    }

    /**
     * The chase of the seat playing — its ship, captain, crew and dice, the rolls left, and the best cargo of the seats
     * before it — to estimate its odds with `Odds\Estimator`; null once the game is over.
     */
    public function chase(GameState $state): ?CargoChase
    {
        if ($state->isOver()) {
            return null;
        }

        [, $rolls, $held, $dice, $cargoes] = $this->read($state->data);

        return new CargoChase($held, $dice, self::ROLLS - $rolls, [] === $cargoes ? 0 : max($cargoes));
    }

    /**
     * The ship, the captain and the crew set aside from a roll, each in its order, and the dice left.
     *
     * @param list<int> $roll
     *
     * @return array{int, list<int>}
     */
    public static function setAside(int $held, array $roll): array
    {
        while ($held < 3) {
            $place = array_search(self::CREW[$held], $roll, true);

            if (!\is_int($place)) {
                break;
            }

            array_splice($roll, $place, 1);
            ++$held;
        }

        return [$held, $roll];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{Turns, int, int, list<int>, array<string, int>}
     *
     * @throws InvalidGame
     */
    private function read(array $data): array
    {
        $rolls = $data['rolls'] ?? null;
        $held = $data['held'] ?? null;
        $dice = $data['dice'] ?? null;
        $cargoes = $data['cargoes'] ?? null;

        if (!\is_int($rolls) || !\is_int($held) || !\is_array($dice) || !\is_array($cargoes)) {
            throw InvalidGame::because('The data of Ship, Captain and Crew is turns, rolls, the dice set aside and rolled, and the cargoes.');
        }

        $faces = [];

        foreach ($dice as $face) {
            $faces[] = \is_int($face) ? $face : throw InvalidGame::because('A die of Ship, Captain and Crew is a whole number.');
        }

        $loaded = [];

        foreach ($cargoes as $seat => $cargo) {
            $loaded[(string) $seat] = \is_int($cargo) ? $cargo : throw InvalidGame::because('A cargo of Ship, Captain and Crew is a whole number.');
        }

        return [Turns::fromData($data['turns'] ?? null), $rolls, $held, $faces, $loaded];
    }

    /**
     * @param list<int>          $dice
     * @param array<string, int> $cargoes
     *
     * @return array<string, mixed>
     */
    private function data(Turns $turns, int $rolls, int $held, array $dice, array $cargoes): array
    {
        return ['turns' => $turns->toData(), 'rolls' => $rolls, 'held' => $held, 'dice' => $dice, 'cargoes' => $cargoes];
    }
}
