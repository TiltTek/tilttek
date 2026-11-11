<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Progression\LevelChange;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Standing;
use Tilttek\Progression\Streak\StreakBroken;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Tracks;
use Tilttek\ProgressionBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\ProgressionBundle\Infrastructure\Event\LevelDown;
use Tilttek\ProgressionBundle\Infrastructure\Event\LevelUp;
use Tilttek\ProgressionBundle\Infrastructure\Twig\ProgressionExtension;
use Tilttek\ProgressionBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(DispatchingObserver::class)]
#[CoversClass(LevelUp::class)]
#[CoversClass(LevelDown::class)]
#[CoversClass(ProgressionExtension::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheObserverDispatchesEvents(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /**
             * @var list<object>
             */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };
        $observer = new DispatchingObserver($dispatcher);
        $member = ResourceReference::fromString('member:1');
        $at = new DateTimeImmutable('2026-11-07 UTC');
        $up = new LevelChange('player', $member, '', 1, 2, $at, 'k1');
        $down = new LevelChange('player', $member, '', 2, 1, $at, 'k1');
        $reached = new ReachedMilestone('player', 'first', $member, $member, '', $at, 'k1');
        $broken = new StreakBroken('daily', $member, 3, 20760, $at);

        $observer->leveledUp($up);
        $observer->leveledDown($down);
        $observer->milestoneReached($reached);
        $observer->streakBroken($broken);

        self::assertEquals([new LevelUp($up), new LevelDown($down), $reached, $broken], $dispatcher->events);
        self::assertSame($up, $dispatcher->events[0] instanceof LevelUp ? $dispatcher->events[0]->change : null);
        self::assertSame($down, $dispatcher->events[1] instanceof LevelDown ? $dispatcher->events[1]->change : null);
    }

    public function testTheTwigFunctions(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-07 10:00', quantity: 3);

        $extension = new ProgressionExtension($kit->progression, 'levels');

        self::assertSame('levels', $extension->domain());
        self::assertSame(150, $extension->standing('member:1', 'player')->level->experience);
        self::assertSame(['player'], array_map(static fn (Standing $standing): string => $standing->track->name, $extension->standings('member:1')));
        self::assertSame(1, $extension->streak('member:1', 'daily')->current);
    }

    private function kit(): Kit
    {
        return new Kit(Tracks::fromArray([
            'player' => [
                'curve' => ['type' => 'linear', 'step' => 100],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 50]],
                'milestones' => ['first' => ['experience' => 100]],
            ],
        ]), StreakRules::fromArray(['daily' => ['verbs' => ['game.played']]]));
    }
}
