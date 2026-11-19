<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\ProtocolVersion;
use Tilttek\DiceTower\Application\NoTableAccess;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingHub;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingTokenFactory;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tables;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(TowerSubscriptions::class)]
#[CoversClass(NoTableAccess::class)]
final class TowerSubscriptionsTest extends TestCase
{
    public function testAnAllowedViewerIsGivenTheTopicOfTheTableOnly(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $request = Request::create('https://play.hub.test/tables/7');
        $requests = new RequestStack([$request]);
        $subscriptions = new TowerSubscriptions(new Tables('table:7'), new TowerTopics(), $hub, new Authorization(new HubRegistry($hub), 3600), $requests);

        self::assertSame('https://hub.test/.well-known/mercure?match=urn%3Atilttek%3Adice-tower%3Atable%253A7', $subscriptions->subscribe(Tower::table()));

        $cookies = $request->attributes->get('_mercure_authorization_cookies');
        self::assertIsArray($cookies);
        $cookie = array_first($cookies);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('urn:tilttek:dice-tower:table%3A7', $cookie->getValue());
    }

    public function testTheLegacyProtocolAndAQueryInTheHubsAddress(): void
    {
        $hub = new RecordingHub('https://hub.test/.well-known/mercure?app=1', ProtocolVersion::Legacy);
        $subscriptions = new TowerSubscriptions(new Tables('table:7'), new TowerTopics('t/'), $hub);

        self::assertSame('https://hub.test/.well-known/mercure?app=1&topic=t%2Ftable%253A7', $subscriptions->subscribe(Tower::table()));
    }

    public function testWithoutRequestNoCookieIsSet(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $subscriptions = new TowerSubscriptions(new Tables('table:7'), new TowerTopics(), $hub, new Authorization(new HubRegistry($hub)), new RequestStack());

        self::assertNotNull($subscriptions->subscribe(Tower::table()));
    }

    public function testAViewerWhoMayNotWatchOrAnApplicationWithoutHubGetsNothing(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $request = Request::create('https://play.hub.test/tables/8');
        $requests = new RequestStack([$request]);

        self::assertNull(new TowerSubscriptions(new Tables('table:7'), new TowerTopics(), $hub, new Authorization(new HubRegistry($hub)), $requests)->subscribe(Tower::table('8')));
        self::assertNull(new TowerSubscriptions(new NoTableAccess(), new TowerTopics(), $hub)->subscribe(Tower::table()));
        self::assertNull(new TowerSubscriptions(new Tables('table:7'), new TowerTopics())->subscribe(Tower::table()));
        self::assertFalse($request->attributes->has('_mercure_authorization_cookies'));
    }
}
