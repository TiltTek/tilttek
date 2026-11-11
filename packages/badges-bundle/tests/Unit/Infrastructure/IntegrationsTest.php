<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badge;
use Tilttek\Badges\Criterion\Manual;
use Tilttek\Badges\Standing;
use Tilttek\BadgesBundle\Infrastructure\Event\BadgeAwarded;
use Tilttek\BadgesBundle\Infrastructure\Event\BadgeRevoked;
use Tilttek\BadgesBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\BadgesBundle\Infrastructure\Twig\BadgesExtension;
use Tilttek\BadgesBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(DispatchingObserver::class)]
#[CoversClass(BadgeAwarded::class)]
#[CoversClass(BadgeRevoked::class)]
#[CoversClass(BadgesExtension::class)]
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
        $award = new Award('gold', ResourceReference::of('member', '1'), 1, new DateTimeImmutable());
        $badge = new Badge('gold', 'Gold', new Manual());

        $observer->awarded($award, $badge);
        $observer->revoked($award, $badge);
        self::assertEquals([new BadgeAwarded($award, $badge), new BadgeRevoked($award, $badge)], $dispatcher->events);
        self::assertSame($award, $dispatcher->events[0] instanceof BadgeAwarded ? $dispatcher->events[0]->award : null);
        self::assertSame($badge, $dispatcher->events[1] instanceof BadgeRevoked ? $dispatcher->events[1]->badge : null);
    }

    public function testTheTwigFunctions(): void
    {
        $badges = Kit::badges();
        $member = ResourceReference::of('member', '1');
        $badges->award($member, 'organiser');
        $badges->show($member, 'organiser');

        $extension = new BadgesExtension($badges, 'trophies');

        self::assertSame('trophies', $extension->domain());
        self::assertSame(['organiser'], array_map(static fn (Badge $badge): string => $badge->code, $extension->showcase('member:1')->shown));
        self::assertSame([['commenter', 0], ['organiser', 1], ['games', 0]], array_map(static fn (Standing $standing): array => [$standing->badge->code, $standing->awards], $extension->overview('member:1')));
    }
}
