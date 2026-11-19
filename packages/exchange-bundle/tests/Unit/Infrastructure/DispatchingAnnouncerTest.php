<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Exchange\Event\PointsTransferred;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\ExchangeBundle\Infrastructure\Event\DispatchingAnnouncer;

#[CoversClass(DispatchingAnnouncer::class)]
final class DispatchingAnnouncerTest extends TestCase
{
    public function testTheEventsReachTheApplicationsListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $heard = [];
        $dispatcher->addListener(PointsTransferred::class, static function (PointsTransferred $event) use (&$heard): void {
            $heard[] = $event->transfer->key->value;
        });
        $event = new PointsTransferred(new Transfer(ExchangeKey::fromString('gift:1'), ResourceReference::of('member', '1'), ResourceReference::of('member', '2'), 5, 'points', new DateTimeImmutable()));

        new DispatchingAnnouncer($dispatcher)->announce($event);

        self::assertSame(['gift:1'], $heard);
    }
}
