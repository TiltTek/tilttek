<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Tilttek\Leaderboards\Boards;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\LeaderboardsSchedule;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDue;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDueHandler;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(LeaderboardsSchedule::class)]
#[CoversClass(TakeDue::class)]
#[CoversClass(TakeDueHandler::class)]
final class ScheduleTest extends TestCase
{
    public function testTheScheduleSendsTakeDueAtItsFrequency(): void
    {
        $messages = new LeaderboardsSchedule('15 minutes')->getSchedule()->getRecurringMessages();

        self::assertCount(1, $messages);
        self::assertSame('every 15 minutes', (string) $messages[0]->getTrigger());
        self::assertInstanceOf(PeriodicalTrigger::class, $messages[0]->getTrigger());
        self::assertEquals([new TakeDue()], iterator_to_array($messages[0]->getMessages(new MessageContext('tilttek_leaderboards', 'id', $messages[0]->getTrigger(), new DateTimeImmutable()))));
        self::assertSame('every 1 hour', (string) new LeaderboardsSchedule()->getSchedule()->getRecurringMessages()[0]->getTrigger());
        self::assertContainsOnlyInstancesOf(RecurringMessage::class, $messages);
    }

    public function testTheHandlerTakesTheSnapshotsAndSettlesTheWeeksDue(): void
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month'],
            'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver']]],
            'today' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'window', 'window' => 1],
        ]), '2026-11-03 12:00 UTC');
        $kit->record('k1', 'member:1', 'game.played', '2026-11-03 10:00');
        $kit->clock->set('2026-11-09 06:00 UTC');

        $handler = new TakeDueHandler($kit->leaderboards, $kit->leagues, $kit->projection, $kit->clock);

        self::assertSame(['snapshots' => 2, 'settlements' => 1, 'expired' => 1], $handler());
        self::assertSame(['snapshots' => 0, 'settlements' => 0, 'expired' => 0], $handler());
    }
}
