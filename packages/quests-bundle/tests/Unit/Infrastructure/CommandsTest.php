<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\QuestsBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\QuestsBundle\Infrastructure\Console\ListCommand;
use Tilttek\QuestsBundle\Infrastructure\Console\ShowCommand;
use Tilttek\QuestsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(ListCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(ExpireCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheQuestsAreListed(): void
    {
        $tester = $this->tester($this->kit(), 'tilttek:quests:list');

        self::assertSame(0, $tester->execute([]));
        self::assertSame([
            'Quest    Kind             Steps           After   Rewards',
            'path     personal         one 1 → two 3   —       points xp ×50, badge walker ×1',
            'race     duel, accepted   play 2          —',
            'raid     team, accepted   play 3          —',
            'second   personal         play 1          path',
        ], $this->lines($tester, '/^(Quest|path|race|raid|second)\b/'));
    }

    public function testAMembersQuestsAreShown(): void
    {
        $kit = $this->kit();
        $kit->at('2026-11-07 09:00');
        $kit->quests->accept(ResourceReference::fromString('member:1'), 'raid', ResourceReference::fromString('team:red'));
        $kit->record('k1', 'member:1', 'a.done', '2026-11-07 10:00');
        $kit->record('k2', 'member:1', 'game.played', '2026-11-07 11:00');

        $tester = $this->tester($kit, 'tilttek:quests:show');

        self::assertSame(0, $tester->execute(['member' => 'member:1']));
        self::assertSame([
            'Quest    State       Steps              Team',
            'path     under_way   one 1/1, two 1/3   —',
            'raid     under_way   play 1/3           team:red',
            'second   locked      play 0/1           —',
        ], $this->lines($tester, '/^(Quest|path|raid|second)\b/'));
        self::assertSame(1, $tester->execute(['member' => 'nobody']));
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    public function testTheDuelsWhoseTimeIsUpExpire(): void
    {
        $kit = $this->kit();
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', ResourceReference::fromString('member:1'), ResourceReference::fromString('member:2'));
        $kit->at('2026-11-09 09:00');

        $tester = $this->tester($kit, 'tilttek:quests:expire');

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 duel(s) expired.', $tester->getDisplay());
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('0 duel(s) expired.', $tester->getDisplay());
    }

    /**
     * @return list<string>
     */
    private function lines(CommandTester $tester, string $pattern): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $tester->getDisplay())), static fn (string $line): bool => 1 === preg_match($pattern, $line)));
    }

    private function tester(Kit $kit, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand(new ListCommand($kit->catalog));
        $application->addCommand(new ShowCommand($kit->quests));
        $application->addCommand(new ExpireCommand($kit->duelService));

        return new CommandTester($application->find($name));
    }

    private function kit(): Kit
    {
        return Kit::of([
            'path' => [
                'ordered' => true,
                'steps' => [['code' => 'one', 'verbs' => ['a.done']], ['code' => 'two', 'verbs' => ['game.played'], 'count' => 3]],
                'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50], ['kind' => 'badge', 'code' => 'walker']],
            ],
            'second' => ['after' => 'path', 'steps' => [['code' => 'play', 'verbs' => ['game.played']]]],
            'raid' => ['kind' => 'team', 'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 3]]],
            'race' => ['kind' => 'duel', 'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
        ]);
    }
}
