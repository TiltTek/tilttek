<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\SettleCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\ShowCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\SlideCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\SnapshotCommand;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(SnapshotCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(SettleCommand::class)]
#[CoversClass(SlideCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheSnapshotsDueAreTaken(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-12-01 00:00 UTC');

        $tester = $this->tester($kit, 'tilttek:leaderboards:snapshot');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(['games    2026-11    3        2', 'weekly   2026-w48   0        0', '[OK] 2 snapshot(s).'], $this->lines($tester->getDisplay(), '/^(games|week|\[OK\])/'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(['[OK] 0 snapshot(s).'], $this->lines($tester->getDisplay(), '/^(games|\[OK\])/'));
    }

    public function testOneSnapshotIsTaken(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-12-01 00:00 UTC');

        $tester = $this->tester($kit, 'tilttek:leaderboards:snapshot');

        self::assertSame(0, $tester->execute(['board' => 'games', 'period' => '2026-11']));
        self::assertSame(['games   2026-11   3        2', '[OK] 1 snapshot(s).'], $this->lines($tester->getDisplay(), '/^(games|\[OK\])/'));
        self::assertSame(1, $tester->execute(['board' => 'games', 'period' => '2026-12']));
        self::assertStringContainsString('[ERROR] The period "2026-12" of the board "games" is not over', $tester->getDisplay());
        self::assertSame(2, $tester->execute(['board' => 'games']));
        self::assertStringContainsString('[ERROR] Name a board and its period, or neither.', $tester->getDisplay());
    }

    public function testABoardIsShownAsThePublicSeesIt(): void
    {
        $kit = $this->kit();
        $kit->leaderboards->appear(ResourceReference::fromString('member:1'), Appearance::named());
        $kit->leaderboards->appear(ResourceReference::fromString('member:2'), Appearance::pseudonym('Gris'));

        $tester = $this->tester($kit, 'tilttek:leaderboards:show');

        self::assertSame(0, $tester->execute(['board' => 'games']));
        self::assertSame(['games — 2026-11 — 3 ranked', '1      member:1   7', '2      Gris       5', '3      —          1'], $this->lines($tester->getDisplay(), '/^(games|\d)/'));
        self::assertSame(0, $tester->execute(['board' => 'games', 'period' => '2026-11', '--limit' => '1']));
        self::assertSame(['games — 2026-11 — 3 ranked', '1      member:1   7'], $this->lines($tester->getDisplay(), '/^(games|\d)/'));
        self::assertSame(1, $tester->execute(['board' => 'nothing']));
        self::assertStringContainsString('[ERROR] No board is named "nothing".', $tester->getDisplay());
    }

    public function testTheWeeksDueAreSettled(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-09 00:00 UTC');

        $tester = $this->tester($kit, 'tilttek:leaderboards:settle');

        self::assertSame(0, $tester->execute([]));
        self::assertSame(['weekly   2026-w45   3         1    0', '[OK] 1 week(s) settled.'], $this->lines($tester->getDisplay(), '/^(weekly|\[OK\])/'));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(['[OK] 0 week(s) settled.'], $this->lines($tester->getDisplay(), '/^(weekly|\[OK\])/'));
    }

    public function testOneWeekIsSettled(): void
    {
        $kit = $this->kit();
        $kit->leagueStore->settle(new Settlement('weekly', '2026-w44', new DateTimeImmutable('2026-11-02 UTC'), []), ['member:1' => 'gold', 'member:2' => 'gold', 'member:3' => 'gold']);
        $kit->record('k4', 'member:1', 'game.played', '2026-11-05 10:00', 1);
        $kit->clock->set('2026-11-09 00:00 UTC');

        $tester = $this->tester($kit, 'tilttek:leaderboards:settle');

        self::assertSame(1, $tester->execute(['board' => 'weekly', 'week' => '2026-w46']));
        self::assertStringContainsString('[ERROR] The period "2026-w46" of the board "weekly" is not over', $tester->getDisplay());
        self::assertSame(0, $tester->execute(['board' => 'weekly', 'week' => '2026-w44']));
        self::assertSame(['[OK] 0 week(s) settled.'], $this->lines($tester->getDisplay(), '/^(weekly|\[OK\])/'));
        self::assertSame(2, $tester->execute(['week' => '2026-w45']));
        self::assertStringContainsString('[ERROR] Name a board and its week, or neither.', $tester->getDisplay());
        self::assertSame(2, $tester->execute(['board' => 'weekly']));
    }

    public function testAFullDivisionSendsItsLastDown(): void
    {
        $kit = new Kit(Boards::fromArray(['weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver', 'gold'], 'division_size' => 3, 'promoted' => 1, 'relegated' => 1]]]));
        $kit->leagueStore->settle(new Settlement('weekly', '2026-w44', new DateTimeImmutable('2026-11-02 UTC'), []), ['member:1' => 'silver', 'member:2' => 'silver', 'member:3' => 'silver']);
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 7);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 10:00', 5);
        $kit->record('k3', 'member:3', 'game.played', '2026-11-04 10:00', 1);
        $kit->clock->set('2026-11-09 00:00 UTC');

        $tester = $this->tester($kit, 'tilttek:leaderboards:settle');

        self::assertSame(0, $tester->execute(['board' => 'weekly', 'week' => '2026-w45']));
        self::assertSame(['weekly   2026-w45   3         1    1', '[OK] 1 week(s) settled.'], $this->lines($tester->getDisplay(), '/^(weekly|\[OK\])/'));
    }

    public function testTheWindowsSlide(): void
    {
        $kit = new Kit(Boards::fromArray(['today' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'window', 'window' => 1]]));
        $kit->record('k1', 'member:1', 'game.played', '2026-11-07 10:00', 1);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-07 11:00', 1);
        $kit->clock->set('2026-11-08 01:00 UTC');

        $tester = $this->tester($kit, 'tilttek:leaderboards:slide');

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('[OK] 2 contribution(s) left their window.', $tester->getDisplay());
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('[OK] 0 contribution(s) left their window.', $tester->getDisplay());
    }

    private function tester(Kit $kit, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand(new SnapshotCommand($kit->leaderboards));
        $application->addCommand(new ShowCommand($kit->leaderboards));
        $application->addCommand(new SettleCommand($kit->leagues));
        $application->addCommand(new SlideCommand($kit->projection, $kit->clock));

        return new CommandTester($application->find($name));
    }

    /**
     * @return list<string>
     */
    private function lines(string $display, string $pattern): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $display)), static fn (string $line): bool => 1 === preg_match($pattern, $line)));
    }

    private function kit(): Kit
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month', 'snapshot_size' => 2],
            'level' => ['measure' => 'latest'],
            'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver'], 'promoted' => 1, 'relegated' => 1]],
        ]));
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 7);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 10:00', 5);
        $kit->record('k3', 'member:3', 'game.played', '2026-11-04 10:00', 1);
        $kit->clock->set('2026-11-07 12:00 UTC');

        return $kit;
    }
}
