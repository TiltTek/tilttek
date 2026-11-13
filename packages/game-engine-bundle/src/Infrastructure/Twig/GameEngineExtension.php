<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Twig;

use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;
use Twig\Attribute\AsTwigFunction;

/**
 * The tables in the application's templates: `tilttek_game_table('table:42')` what anyone at the table may know of it —
 * its game, number of moves, phase, round, the seats that may act, its outcome once over, the commitment to its seed
 * and its Mercure topic —, never the game's data, which each seat reads through its view; `tilttek_game_engine_kit(
 * 'Card')` the name a recipe of the kit is installed under; `tilttek_game_engine_domain()` the translation domain of
 * the template.
 */
final readonly class GameEngineExtension
{
    public function __construct(
        private GameTables $tables,
        private string $domain = 'tilttek_game_engine',
        private KitComponents $kit = new KitComponents(),
        private ?TableTopic $topic = null,
    ) {
    }

    /**
     * What anyone at a table may know of it.
     *
     * @return array{table: string, game: string, sequence: int, phase: string, round: int, to_act: list<string>, over: bool, outcome: ?array{winners: list<string>, reason: string, statistics: array{table: array<string, int>, seats: array<string, array<string, int>>}}, commitment: string, topic: ?string}
     *
     * @throws UnknownTable
     */
    #[AsTwigFunction('tilttek_game_table')]
    public function table(string $gameId): array
    {
        $state = $this->tables->state($gameId);

        return [
            'table' => $state->gameId,
            'game' => $state->game,
            'sequence' => $state->sequence,
            'phase' => $state->phase,
            'round' => $state->turn->round,
            'to_act' => $state->isOver() ? [] : $state->turn->toAct(),
            'over' => $state->isOver(),
            'outcome' => $state->outcome?->toArray(),
            'commitment' => $this->tables->commitment($gameId),
            'topic' => $this->topic?->of($gameId),
        ];
    }

    /**
     * The name under which the recipe of the kit is installed in the application.
     */
    #[AsTwigFunction('tilttek_game_engine_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    /**
     * The translation domain of the template's texts.
     */
    #[AsTwigFunction('tilttek_game_engine_domain')]
    public function domain(): string
    {
        return $this->domain;
    }
}
