<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\ProtocolVersion;
use Tilttek\EscapeRoomBundle\Application\EscapeRoomTopics;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\MercureScreenPublisher;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\ScreenSubscriptions;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\RecordingHub;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\RecordingTokenFactory;

#[CoversClass(MercureScreenPublisher::class)]
#[CoversClass(ScreenSubscriptions::class)]
final class MercureTest extends TestCase
{
    public function testAMoveIsAPrivateUpdateThatNamesTheMoveOnly(): void
    {
        $hub = new RecordingHub();
        new MercureScreenPublisher($hub, new EscapeRoomTopics())->moved('g-1', 'hint');

        self::assertCount(1, $hub->updates);
        self::assertSame(['tilttek-escape-room/games/g-1'], $hub->updates[0]->getTopics());
        self::assertSame('{"move":"hint"}', $hub->updates[0]->getData());
        self::assertTrue($hub->updates[0]->isPrivate());
    }

    public function testAHubThatCannotBeReachedIsLogged(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };
        new MercureScreenPublisher(new RecordingHub(failing: true), new EscapeRoomTopics(), $logger)->moved('g-1', 'pause');
        new MercureScreenPublisher(new RecordingHub(failing: true), new EscapeRoomTopics())->moved('g-1', 'pause');

        self::assertCount(1, $logger->logs);
        self::assertSame('warning', $logger->logs[0][0]);
        self::assertSame('The move {move} of the game "{game}" could not be pushed to Mercure: {message}', $logger->logs[0][1]);
        self::assertSame(['move' => 'pause', 'game' => 'g-1', 'message' => 'The hub is down.'], \array_slice($logger->logs[0][2], 0, 3));
    }

    public function testASubscriptionNamesTheTopicOfTheGame(): void
    {
        self::assertSame('https://hub.test/.well-known/mercure?match=tilttek-escape-room%2Fgames%2Fg-1', new ScreenSubscriptions(new EscapeRoomTopics(), new RecordingHub())->subscribe('g-1'));
        self::assertSame('https://hub.test/mercure?x=1&topic=tilttek-escape-room%2Fgames%2Fg-1', new ScreenSubscriptions(new EscapeRoomTopics(), new RecordingHub('https://hub.test/mercure?x=1', ProtocolVersion::Legacy))->subscribe('g-1'));
        self::assertNull(new ScreenSubscriptions(new EscapeRoomTopics())->subscribe('g-1'));
    }

    public function testTheViewerIsGivenTheCookieOfTheTopicOnly(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $request = Request::create('https://hub.test/escape-room/screen/g-1');
        new ScreenSubscriptions(new EscapeRoomTopics(), $hub, new Authorization(new HubRegistry($hub)), new RequestStack([$request]))->subscribe('g-1');

        $cookies = $request->attributes->get('_mercure_authorization_cookies');
        self::assertIsArray($cookies);
        $cookie = array_first($cookies);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('tilttek-escape-room/games/g-1', $cookie->getValue());
    }

    public function testTheCookieNeedsBothTheAuthorizationAndARequest(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $url = 'https://hub.test/.well-known/mercure?match=tilttek-escape-room%2Fgames%2Fg-1';

        self::assertSame($url, new ScreenSubscriptions(new EscapeRoomTopics(), $hub, new Authorization(new HubRegistry($hub)))->subscribe('g-1'));
        self::assertSame($url, new ScreenSubscriptions(new EscapeRoomTopics(), $hub, null, new RequestStack([Request::create('https://example.test/')]))->subscribe('g-1'));
        self::assertSame($url, new ScreenSubscriptions(new EscapeRoomTopics(), $hub, new Authorization(new HubRegistry($hub)), new RequestStack())->subscribe('g-1'));
    }
}
