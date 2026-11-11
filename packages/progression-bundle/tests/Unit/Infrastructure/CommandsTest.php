<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Tracks;
use Tilttek\ProgressionBundle\Infrastructure\Console\ShowCommand;
use Tilttek\ProgressionBundle\Infrastructure\Console\SweepCommand;
use Tilttek\ProgressionBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(SweepCommand::class)]
#[CoversClass(ShowCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheBrokenStreaksAreSwept(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 10:00');
        $kit->clock->set('2026-11-04 10:00:00 UTC');

        $tester = $this->tester($kit, 'tilttek:progression:sweep');

        self::assertSame(0, $tester->execute([]));
        self::assertSame('2 streaks broke.', trim($tester->getDisplay()));
        self::assertSame(0, $tester->execute([]));
        self::assertSame('0 streaks broke.', trim($tester->getDisplay()));
    }

    public function testASubjectIsShown(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-06 10:00', quantity: 3);
        $kit->record('k2', 'member:1', 'game.played', '2026-11-07 10:00');

        $tester = $this->tester($kit, 'tilttek:progression:show');

        self::assertSame(0, $tester->execute(['subject' => 'member:1']));
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $tester->getDisplay())), static fn (string $line): bool => 1 === preg_match('/^(Track|Streak|player|monthly|daily)\b/', $line)));
        self::assertSame([
            'Track     Season    Level   Experience   Next level at   Lifetime',
            'monthly   2026-11   1       40           100             40',
            'player    —         3       200          —               200',
            'Streak   Current   Best   Grace left',
            'daily    2         2      0',
        ], $lines);
        self::assertSame(1, $tester->execute(['subject' => 'nobody']));
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    private function tester(Kit $kit, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand(new SweepCommand($kit->progression));
        $application->addCommand(new ShowCommand($kit->progression));

        return new CommandTester($application->find($name));
    }

    private function kit(): Kit
    {
        return new Kit(Tracks::fromArray([
            'player' => ['curve' => ['type' => 'table', 'thresholds' => [100, 200]], 'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 50]]],
            'monthly' => ['curve' => ['type' => 'linear', 'step' => 100], 'seasons' => ['length' => 'month'], 'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 10]]],
        ]), StreakRules::fromArray(['daily' => ['verbs' => ['game.played']]]));
    }
}
