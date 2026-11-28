<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Game;

use Tilttek\GameEngine\Exception\InvalidGame;
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
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\DiceCup;
use Tilttek\TavernGames\Turns;

/**
 * Pig, the old dice game of push-your-luck: in their turn, a seat rolls one die as often as they like, adding each face
 * to the turn's total, and holds to bank it; a 1 loses the turn's total and ends the turn. The first seat to bank the
 * target wins.
 *
 * Its moves are `pig.roll` and `pig.hold`; the timeout of a turn holds. Its option is `target`, the points to bank
 * (100, from 20 to 200). Its outcome reports the banked points as the statistic `score` of each seat.
 */
final readonly class Pig implements TavernGame
{
    public const string NAME = 'pig';

    public const string ROLL = 'pig.roll';

    public const string HOLD = 'pig.hold';

    private GameDefinition $definition;

    public function __construct(
        private DiceCup $dice = new DiceCup(),
    ) {
        $this->definition = new GameDefinition(
            self::NAME,
            [new Phase('playing')],
            minimumSeats: 2,
            maximumSeats: 8,
            options: [Option::integer('target', 100, 20, 200)],
            statistics: [Statistic::seat('score')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function setup(GameState $state, Randomness $random): array
    {
        return ['turns' => Turns::start($state->seats)->toData(), 'scores' => array_fill_keys($state->seats, 0), 'total' => 0, 'last' => null];
    }

    public function allows(GameState $state, Move $move): bool
    {
        $turns = Turns::fromData($state->data['turns'] ?? null);

        return match ($move->type) {
            self::ROLL, self::HOLD => $turns->isTurnOf($move->seat),
            self::TIMEOUT => null === $move->seat && $turns->isTurnOf($move->text('seat')),
            default => false,
        };
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        [$turns, $scores, $total] = $this->read($state->data);

        if (self::ROLL !== $move->type) {
            $scores[$turns->seat()] += $total;

            return $this->data($turns->next(), $scores, 0, null);
        }

        $face = $this->dice->roll(1, $random)[0];

        return 1 === $face ? $this->data($turns->next(), $scores, 0, 1) : $this->data($turns, $scores, $total + $face, $face);
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        [, $scores] = $this->read($state->data);
        $winners = array_keys(array_filter($scores, static fn (int $score): bool => $score >= $state->options->int('target')));

        if ([] === $winners) {
            return null;
        }

        return Outcome::won($winners, 'target-reached', new Statistics([], array_map(static fn (int $score): array => ['score' => $score], $scores)));
    }

    public function toAct(GameState $state): ?string
    {
        return $state->isOver() ? null : Turns::fromData($state->data['turns'] ?? null)->seat();
    }

    /**
     * Everything: Pig has no secret. The seat playing, the banked points, the turn's total and the last face rolled.
     */
    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        [$turns, $scores, $total] = $this->read($state->data);

        return [
            'turn' => $state->isOver() ? null : $turns->seat(),
            'scores' => $scores,
            'total' => $total,
            'last' => $state->data['last'] ?? null,
            'target' => $state->options->int('target'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{Turns, array<string, int>, int}
     *
     * @throws InvalidGame
     */
    private function read(array $data): array
    {
        $scores = $data['scores'] ?? null;
        $total = $data['total'] ?? null;

        if (!\is_array($scores) || !\is_int($total)) {
            throw InvalidGame::because('The data of Pig is turns, banked points and the total of a turn.');
        }

        $banked = [];

        foreach ($scores as $seat => $score) {
            $banked[(string) $seat] = \is_int($score) ? $score : throw InvalidGame::because('The points of a seat of Pig are a whole number.');
        }

        return [Turns::fromData($data['turns'] ?? null), $banked, $total];
    }

    /**
     * @param array<string, int> $scores
     *
     * @return array<string, mixed>
     */
    private function data(Turns $turns, array $scores, int $total, ?int $last): array
    {
        return ['turns' => $turns->toData(), 'scores' => $scores, 'total' => $total, 'last' => $last];
    }
}
