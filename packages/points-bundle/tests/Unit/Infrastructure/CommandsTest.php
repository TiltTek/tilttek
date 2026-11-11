<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\PointsBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\PointsBundle\Infrastructure\Console\HistoryCommand;
use Tilttek\PointsBundle\Tests\Unit\Fixture\Clock;

#[CoversClass(ExpireCommand::class)]
#[CoversClass(HistoryCommand::class)]
final class CommandsTest extends TestCase
{
    private Clock $clock;

    private Points $points;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new Clock('2026-01-01 08:00:00 UTC');
        $this->points = new Points(new InMemoryLedger(), $this->clock, new Currencies(new Currency('points'), new Currency('stars', 'P1M')));
        $member = ResourceReference::of('member', '1');
        $this->points->grant($member, 30, 'welcome');
        $this->points->grant($member, 12, 'contest', currency: 'stars');

        $this->clock->set('2026-01-02 09:30:00 UTC');
        $this->points->spend($member, 5, 'order:1', 'order:1');
        $this->points->transfer($member, ResourceReference::of('member', '2'), 3, 'gift:1');
    }

    public function testTheCreditsDueExpire(): void
    {
        $tester = $this->tester('tilttek:points:expire');
        $this->clock->set('2026-02-01 08:00:00 UTC');

        self::assertSame(0, $tester->execute([]));
        self::assertSame('12 points expired.', trim($tester->getDisplay()));
        self::assertSame(0, $tester->execute([]));
        self::assertSame('0 points expired.', trim($tester->getDisplay()));
    }

    public function testAMembersHistory(): void
    {
        $tester = $this->tester('tilttek:points:history');

        self::assertSame(0, $tester->execute(['member' => 'member:1']));
        self::assertSame([
            'Balance of member:1: 22.',
            '---------- --------------------- ------------- -------- ---------- ---------',
            'Position   Moment                Kind          Points   Currency   Key',
            '---------- --------------------- ------------- -------- ---------- ---------',
            '1          2026-01-01 08:00:00   granted       +30      points     welcome',
            '2          2026-01-01 08:00:00   granted       +12      stars      contest',
            '3          2026-01-02 09:30:00   spent         -5       points     order:1',
            '4          2026-01-02 09:30:00   transferred   -3       points     gift:1',
            '---------- --------------------- ------------- -------- ---------- ---------',
        ], $this->lines($tester));

        self::assertSame(0, $tester->execute(['member' => 'member:1', '--currency' => 'stars']));
        self::assertSame('Balance of member:1: 12.', $this->lines($tester)[0]);
        self::assertCount(6, $this->lines($tester));

        self::assertSame(0, $tester->execute(['member' => 'member:1', '--after' => '1', '--limit' => '2']));
        self::assertSame(['2', '3'], array_map(static fn (string $line): string => explode(' ', $line)[0], \array_slice($this->lines($tester), 4, 2)));
        self::assertCount(7, $this->lines($tester));

        self::assertSame(0, $tester->execute(['member' => 'member:2']));
        self::assertStringContainsString('4          2026-01-02 09:30:00   transferred   +3', $tester->getDisplay());
    }

    public function testAHistoryShowsFiftyMovementsByDefault(): void
    {
        foreach (range(1, 50) as $key) {
            $this->points->grant(ResourceReference::of('member', '1'), 1, 'bonus:'.$key);
        }

        $tester = $this->tester('tilttek:points:history');
        self::assertSame(0, $tester->execute(['member' => 'member:1']));
        self::assertCount(55, $this->lines($tester));
        self::assertStringContainsString('bonus:46', $tester->getDisplay());
        self::assertStringNotContainsString('bonus:47', $tester->getDisplay());
    }

    public function testAHistoryThatCannotBeRead(): void
    {
        $tester = $this->tester('tilttek:points:history');

        foreach ([['member' => 'nobody'], ['member' => 'member:1', '--currency' => 'crowns'], ['member' => 'member:1', '--limit' => '1001']] as $input) {
            self::assertSame(1, $tester->execute($input));
            self::assertStringContainsString('[ERROR]', $tester->getDisplay());
            self::assertStringNotContainsString('Balance', $tester->getDisplay());
        }

        self::assertStringContainsString('A history holds at most 1000 movements', $tester->getDisplay());
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
        $application = new Application();
        $application->addCommand(new ExpireCommand($this->points));
        $application->addCommand(new HistoryCommand($this->points));

        return new CommandTester($application->find($command));
    }
}
