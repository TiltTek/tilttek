<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\ActivityBundle\Infrastructure\Console\CatchUpCommand;
use Tilttek\ActivityBundle\Infrastructure\Console\RebuildCommand;
use Tilttek\ActivityBundle\Infrastructure\Console\StatusCommand;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Counter;

#[CoversClass(CatchUpCommand::class)]
#[CoversClass(RebuildCommand::class)]
#[CoversClass(StatusCommand::class)]
final class CommandsTest extends TestCase
{
    private InMemoryActivityLog $log;

    private InMemoryCheckpoints $checkpoints;

    private Counter $points;

    private Counter $games;

    protected function setUp(): void
    {
        $this->log = new InMemoryActivityLog();
        $this->log->append(Activity::of('a', 'member:1', 'game.played', new DateTimeImmutable('2026-10-01 00:00:00 UTC')), new DateTimeImmutable());
        $this->log->append(Activity::of('b', 'member:1', 'news.read', new DateTimeImmutable('2026-11-02 00:00:00 UTC')), new DateTimeImmutable());
        $this->log->append(Activity::of('c', 'member:2', 'game.won', new DateTimeImmutable('2026-11-03 00:00:00 UTC')), new DateTimeImmutable());

        $this->checkpoints = new InMemoryCheckpoints();
        $this->points = new Counter('points');
        $this->games = new Counter('games', Filter::all()->verbs('game.*'));
    }

    public function testTheProjectionsCatchUp(): void
    {
        $tester = $this->tester('tilttek:activity:catch-up');

        self::assertSame(0, $tester->execute(['projections' => ['points']]));
        self::assertSame('points: 3 entries applied.', trim($tester->getDisplay()));
        self::assertSame([], $this->games->applied);

        self::assertSame(0, $tester->execute([]));
        self::assertSame(['games: 2 entries applied.', 'points: 0 entries applied.'], $this->lines($tester));

        self::assertSame(1, $tester->execute(['projections' => ['points', 'quests']]));
        self::assertStringContainsString('No projection is named "quests".', $tester->getDisplay());
    }

    public function testAProjectionIsRebuilt(): void
    {
        $tester = $this->tester('tilttek:activity:rebuild');

        self::assertSame(0, $tester->execute(['projection' => 'points']));
        self::assertStringContainsString('points: 3 entries applied.', $tester->getDisplay());
        self::assertSame([null], $this->points->resets);

        self::assertSame(0, $tester->execute(['projection' => 'points', '--since' => '2026-11-02']));
        self::assertStringContainsString('points: 2 entries applied.', $tester->getDisplay());
        self::assertSame([null, '2026-11-02'], $this->points->resets);

        self::assertSame(1, $tester->execute(['projection' => 'quests']));
        self::assertStringContainsString('No projection is named "quests".', $tester->getDisplay());

        self::assertSame(1, $tester->execute(['projection' => 'points', '--since' => 'not a date']));
        self::assertStringContainsString('Failed to parse time string (not a date)', $tester->getDisplay());
        self::assertSame([null, '2026-11-02'], $this->points->resets);
    }

    public function testTheStatusListsWhatEachProjectionHasYetToRead(): void
    {
        $this->tester('tilttek:activity:catch-up')->execute(['projections' => ['games']]);
        $tester = $this->tester('tilttek:activity:status');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(['------------ ----------------', 'Projection   Entries behind', '------------ ----------------', 'games        0', 'points       3', '------------ ----------------'], $this->lines($tester));

        $empty = new CommandTester($this->application(new Projections())->find('tilttek:activity:status'));
        self::assertSame(0, $empty->execute([]));
        self::assertSame('No projection is registered.', trim($empty->getDisplay()));
    }

    /**
     * @return list<string>
     */
    private function lines(CommandTester $tester): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $tester->getDisplay())), static fn (string $line): bool => '' !== $line));
    }

    private function tester(string $command): CommandTester
    {
        return new CommandTester($this->application(new Projections([$this->points, $this->games]))->find($command));
    }

    private function application(Projections $projections): Application
    {
        $projector = new Projector($this->log, $this->checkpoints);
        $application = new Application();
        $application->addCommand(new CatchUpCommand($projector, $projections));
        $application->addCommand(new RebuildCommand($projector, $projections));
        $application->addCommand(new StatusCommand($projector, $projections));

        return $application;
    }
}
