<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngineBundle\Application\GameTables;

/**
 * Replays the log of a table from its first move, against the commitment to its seed when one is given, and tells
 * what it leads to: a log that no longer replays, or whose seed is not the one committed to, fails the command.
 */
#[AsCommand('tilttek:game-engine:verify', 'Replays the log of a game table and verifies it against the commitment to its seed')]
final readonly class VerifyCommand
{
    public function __construct(
        private GameTables $tables,
        private Games $games,
        private Engine $engine = new Engine(),
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of the table')]
        string $table,
        #[Option('The commitment to the seed the players were shown, 64 hexadecimal digits')]
        ?string $commitment = null,
    ): int {
        try {
            $log = $this->tables->log($table);
            $game = $this->games->get($log->game);
            $state = null === $commitment ? $this->engine->replay($game, $log) : $this->engine->verify($game, $log, $commitment);
        } catch (InvalidGame|UnknownTable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Table' => $table],
            ['Game' => \sprintf('%s, version %s', $log->game, $log->version)],
            ['Moves' => (string) $log->sequence()],
            ['Phase' => $state->phase],
            ['Outcome' => $state->outcome instanceof Outcome ? \sprintf('%s (%s)', $state->outcome->reason, $state->outcome->hasWinners() ? implode(', ', $state->outcome->winners) : 'no winner') : 'not over'],
            ['Commitment' => $log->commitment()],
        );
        $io->success(null === $commitment ? 'The log replays.' : 'The log replays from the seed committed to.');

        return Command::SUCCESS;
    }
}
