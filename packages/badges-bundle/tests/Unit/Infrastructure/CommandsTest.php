<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badges;
use Tilttek\BadgesBundle\Infrastructure\Console\AwardCommand;
use Tilttek\BadgesBundle\Infrastructure\Console\ListCommand;
use Tilttek\BadgesBundle\Infrastructure\Console\RevokeCommand;
use Tilttek\BadgesBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(AwardCommand::class)]
#[CoversClass(RevokeCommand::class)]
#[CoversClass(ListCommand::class)]
final class CommandsTest extends TestCase
{
    private Badges $badges;

    #[Override]
    protected function setUp(): void
    {
        $this->badges = Kit::badges();
    }

    public function testABadgeIsAwardedByHand(): void
    {
        $tester = $this->tester('tilttek:badges:award');

        self::assertSame(0, $tester->execute(['member' => 'member:1', 'badge' => 'games', '--reason' => 'Ran the convention']));
        self::assertStringContainsString('member:1 holds the badge "games" (#1).', $tester->getDisplay());
        self::assertSame(0, $tester->execute(['member' => 'member:1', 'badge' => 'games']));
        self::assertStringContainsString('(#2)', $tester->getDisplay());
        self::assertSame([['games', 1, 'Ran the convention'], ['games', 2, null]], array_map(static fn (Award $award): array => [$award->badge, $award->number, $award->cause], $this->badges->awards(ResourceReference::of('member', '1'))));
    }

    public function testAnAwardOfAnUnknownBadgeFails(): void
    {
        $tester = $this->tester('tilttek:badges:award');

        self::assertSame(1, $tester->execute(['member' => 'member:1', 'badge' => 'gold']));
        self::assertStringContainsString('No badge is named "gold".', $tester->getDisplay());
        self::assertSame(1, $tester->execute(['member' => 'nobody', 'badge' => 'games']));
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    public function testAnAwardMadeByHandIsWithdrawn(): void
    {
        $this->badges->award(ResourceReference::of('member', '1'), 'games');
        $this->badges->award(ResourceReference::of('member', '1'), 'games');

        $tester = $this->tester('tilttek:badges:revoke');

        self::assertSame(0, $tester->execute(['member' => 'member:1', 'badge' => 'games']));
        self::assertStringContainsString('2 award(s) of "games" withdrawn from member:1.', $tester->getDisplay());
        self::assertSame([], $this->badges->awards(ResourceReference::of('member', '1')));
        self::assertSame(1, $tester->execute(['member' => 'member:1', 'badge' => 'gold']));
        self::assertStringContainsString('No badge is named "gold".', $tester->getDisplay());
        self::assertSame(1, $tester->execute(['member' => 'nobody', 'badge' => 'games']));
    }

    public function testAMembersBadgesAreListed(): void
    {
        $this->badges->award(ResourceReference::of('member', '1'), 'organiser');
        $tester = $this->tester('tilttek:badges:list');

        self::assertSame(0, $tester->execute(['member' => 'member:1']));
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $tester->getDisplay())), static fn (string $line): bool => 1 === preg_match('/^(Badge|commenter|organiser|games|secret)\b/', $line)));
        self::assertSame(['Badge       Awards   Progress', 'commenter   0        0 / 3', 'organiser   1        0 / 1', 'games       0        0 / 1'], $lines);
        self::assertSame(1, $tester->execute(['member' => 'nobody']));
        self::assertStringContainsString('[ERROR]', $tester->getDisplay());
    }

    private function tester(string $command): CommandTester
    {
        $application = new Application();
        $application->addCommand(new AwardCommand($this->badges));
        $application->addCommand(new RevokeCommand($this->badges));
        $application->addCommand(new ListCommand($this->badges));

        return new CommandTester($application->find($command));
    }
}
