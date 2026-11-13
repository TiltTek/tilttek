<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Infrastructure\Console\SetupCommand;
use Tilttek\GameEngineBundle\Infrastructure\Console\VerifyCommand;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

#[CoversClass(SetupCommand::class)]
#[CoversClass(VerifyCommand::class)]
final class CommandsTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testTheTablesOfTheLogsAreCreatedOnceOrTheirSqlShown(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $command = $this->tester(new SetupCommand($connection));

        self::assertSame(Command::SUCCESS, $command->execute(['--dump-sql' => true]));
        self::assertStringContainsString('CREATE TABLE '.GameEngineTables::TABLES, $command->getDisplay());
        self::assertStringContainsString('CREATE TABLE '.GameEngineTables::MOVES, $command->getDisplay());
        self::assertStringContainsString(');', $command->getDisplay());
        self::assertFalse($connection->createSchemaManager()->tablesExist([GameEngineTables::TABLES]));

        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertMatchesRegularExpression('/The tables of the logs are created: [1-9]\d* statements\./', $command->getDisplay());
        self::assertTrue($connection->createSchemaManager()->tablesExist([GameEngineTables::TABLES, GameEngineTables::MOVES]));

        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertSame("The tables of the logs exist.\n", $command->getDisplay());
        self::assertSame(Command::SUCCESS, $command->execute(['--dump-sql' => true]));
        self::assertSame("The tables of the logs exist.\n", $command->getDisplay());
    }

    public function testALogIsReplayedAndVerifiedAgainstItsCommitment(): void
    {
        $games = new Games(new RollRace());
        $tables = new GameTables(new Tables($games, new InMemoryLogStore()));
        $tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 2], self::SEED);
        $tables->play('table:1', new Move('roll.roll', 'ann'));

        $command = $this->tester(new VerifyCommand($tables, $games));

        self::assertSame(Command::SUCCESS, $command->execute(['table' => 'table:1']));
        $display = $command->getDisplay();
        self::assertMatchesRegularExpression('/Table\s+table:1\s*\n/', $display);
        self::assertMatchesRegularExpression('/Game\s+roll-race, version 1\s*\n/', $display);
        self::assertMatchesRegularExpression('/Moves\s+1\s*\n/', $display);
        self::assertMatchesRegularExpression('/Phase\s+race\s*\n/', $display);
        self::assertMatchesRegularExpression('/Outcome\s+target-reached \(ann\)\s*\n/', $display);
        self::assertMatchesRegularExpression('/Commitment\s+'.hash('sha256', self::SEED).'\s*\n/', $display);
        self::assertStringContainsString(hash('sha256', self::SEED), $display);
        self::assertStringContainsString('target-reached (ann)', $display);
        self::assertStringContainsString('The log replays.', $display);

        self::assertSame(Command::SUCCESS, $command->execute(['table' => 'table:1', '--commitment' => hash('sha256', self::SEED)]));
        self::assertStringContainsString('The log replays from the seed committed to.', $command->getDisplay());

        self::assertSame(Command::FAILURE, $command->execute(['table' => 'table:1', '--commitment' => str_repeat('0', 64)]));
        self::assertStringContainsString('The seed of the table "table:1" is not the one it committed to.', $command->getDisplay());

        self::assertSame(Command::FAILURE, $command->execute(['table' => 'table:9']));
        self::assertStringContainsString('No table has the identifier "table:9".', $command->getDisplay());

        $tables->open('roll-race', 'table:2', ['ann', 'bob'], [], self::SEED);
        self::assertSame(Command::SUCCESS, $command->execute(['table' => 'table:2']));
        self::assertMatchesRegularExpression('/Outcome\s+not over\s*\n/', $command->getDisplay());
        self::assertMatchesRegularExpression('/Moves\s+0\s*\n/', $command->getDisplay());
    }

    public function testAGameThatEndsWithoutWinnersSaysSo(): void
    {
        $games = new Games(new RoleplayingScene());
        $tables = new GameTables(new Tables($games, new InMemoryLogStore()));
        $tables->open('roleplaying-scene', 'scene:1', ['gm'], [], self::SEED);
        $tables->play('scene:1', new Move('scene.end', 'gm'));

        $command = $this->tester(new VerifyCommand($tables, $games));

        self::assertSame(Command::SUCCESS, $command->execute(['table' => 'scene:1']));
        self::assertStringContainsString('scene-ended (no winner)', $command->getDisplay());
        self::assertStringContainsString('Moves', $command->getDisplay());
        self::assertMatchesRegularExpression('/Moves\s+1\b/', $command->getDisplay());
    }

    private function tester(SetupCommand|VerifyCommand $command): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find($command instanceof SetupCommand ? 'tilttek:game-engine:setup' : 'tilttek:game-engine:verify'));
    }
}
