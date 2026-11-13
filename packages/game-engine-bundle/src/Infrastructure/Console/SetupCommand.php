<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;

/**
 * Creates the tables of the logs on their connection when they are missing, or shows the SQL that would: for an
 * application without Doctrine Migrations, whose migrations otherwise write them from the schema the ORM generates.
 */
#[AsCommand('tilttek:game-engine:setup', 'Creates the tables of the logs of the game tables when they are missing')]
final readonly class SetupCommand
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Shows the SQL instead of running it')]
        bool $dumpSql = false,
    ): int {
        $schemaManager = $this->connection->createSchemaManager();
        $current = $schemaManager->introspectSchema();
        $statements = $this->connection->getDatabasePlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($current, GameEngineTables::configure($current)));

        if ([] === $statements) {
            $io->writeln('The tables of the logs exist.');

            return Command::SUCCESS;
        }

        foreach ($statements as $statement) {
            $dumpSql ? $io->writeln($statement.';') : $this->connection->executeStatement($statement);
        }

        if (!$dumpSql) {
            $io->writeln(\sprintf('The tables of the logs are created: %d statements.', \count($statements)));
        }

        return Command::SUCCESS;
    }
}
