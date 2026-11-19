<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
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
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafetyBundle\Application\NoTableAccess;
use Tilttek\TableSafetyBundle\Application\SignalTopics;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\MercureSignalObserver;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\SignalSubscriptions;
use Tilttek\TableSafetyBundle\Tests\Unit\Fixture\RecordingHub;
use Tilttek\TableSafetyBundle\Tests\Unit\Fixture\RecordingTokenFactory;

#[CoversClass(SignalTopics::class)]
#[CoversClass(MercureSignalObserver::class)]
#[CoversClass(SignalSubscriptions::class)]
#[CoversClass(NoTableAccess::class)]
final class MercureTest extends TestCase
{
    public function testEachTableHasTwoTopics(): void
    {
        $topics = new SignalTopics();
        $table = ResourceReference::of('table', '7/b');

        self::assertSame('urn:tilttek:table-safety:table%3A7%2Fb', $topics->topic($table, Audience::Table));
        self::assertSame('urn:tilttek:table-safety:table%3A7%2Fb/game-master', $topics->topic($table, Audience::GameMaster));
        self::assertSame(['urn:tilttek:table-safety:table%3A7%2Fb'], $topics->readBy($table, false));
        self::assertSame(['urn:tilttek:table-safety:table%3A7%2Fb', 'urn:tilttek:table-safety:table%3A7%2Fb/game-master'], $topics->readBy($table, true));
        self::assertSame('urn:x:table%3A7%2Fb', new SignalTopics('urn:x:')->topic($table, Audience::Table));
    }

    public function testASignalIsPushedToItsAudiencesTopic(): void
    {
        $hub = new RecordingHub();
        $observer = new MercureSignalObserver($hub, new SignalTopics());
        $table = $this->signal(Audience::Table);
        $discreet = $this->signal(Audience::GameMaster);

        $observer->raised($table);
        $observer->acknowledged($discreet);

        self::assertCount(2, $hub->updates);
        self::assertSame(['urn:tilttek:table-safety:table%3A7'], $hub->updates[0]->getTopics());
        self::assertTrue($hub->updates[0]->isPrivate());
        self::assertSame(json_encode($table->toArray(), \JSON_THROW_ON_ERROR), $hub->updates[0]->getData());
        self::assertSame(['urn:tilttek:table-safety:table%3A7/game-master'], $hub->updates[1]->getTopics());
        self::assertStringNotContainsString('member', $hub->updates[0]->getData());
    }

    public function testAHubThatCannotBeReachedIsLogged(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string|Stringable, array<array-key, mixed>}> */
            public array $logs = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, $message, $context];
            }
        };
        $signal = $this->signal(Audience::Table);

        new MercureSignalObserver(new RecordingHub(failing: true), new SignalTopics(), $logger)->raised($signal);
        new MercureSignalObserver(new RecordingHub(failing: true), new SignalTopics())->raised($signal);

        self::assertCount(1, $logger->logs);
        self::assertSame('warning', $logger->logs[0][0]);
        self::assertSame($signal->id, $logger->logs[0][2]['id']);
        self::assertSame('table:7', $logger->logs[0][2]['table']);
        self::assertSame('The hub is down.', $logger->logs[0][2]['message']);
    }

    public function testAViewerSubscribesToTheTopicsTheyRead(): void
    {
        $table = ResourceReference::of('table', '7');

        self::assertNull(new SignalSubscriptions(new SignalTopics())->subscribe($table, true));
        self::assertSame(
            'https://hub.test/.well-known/mercure?match=urn%3Atilttek%3Atable-safety%3Atable%253A7',
            new SignalSubscriptions(new SignalTopics(), new RecordingHub())->subscribe($table, false),
        );
        self::assertSame(
            'https://hub.test/.well-known/mercure?a=1&topic=urn%3Atilttek%3Atable-safety%3Atable%253A7&topic=urn%3Atilttek%3Atable-safety%3Atable%253A7%2Fgame-master',
            new SignalSubscriptions(new SignalTopics(), new RecordingHub('https://hub.test/.well-known/mercure?a=1', ProtocolVersion::Legacy))->subscribe($table, true),
        );
    }

    public function testTheCookieIsGivenTheTopicsOnly(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $request = Request::create('https://hub.test/table');
        $requests = new RequestStack([$request]);
        $subscriptions = new SignalSubscriptions(new SignalTopics(), $hub, new Authorization(new HubRegistry($hub)), $requests);

        $subscriptions->subscribe(ResourceReference::of('table', '7'), true);

        $cookies = $request->attributes->get('_mercure_authorization_cookies');
        self::assertIsArray($cookies);
        $cookie = array_first($cookies);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('urn:tilttek:table-safety:table%3A7 urn:tilttek:table-safety:table%3A7/game-master', $cookie->getValue());
    }

    public function testTheCookieNeedsBothTheAuthorizationAndARequest(): void
    {
        $hub = new RecordingHub(factory: new RecordingTokenFactory());
        $table = ResourceReference::of('table', '7');
        $url = 'https://hub.test/.well-known/mercure?match=urn%3Atilttek%3Atable-safety%3Atable%253A7';

        self::assertSame($url, new SignalSubscriptions(new SignalTopics(), $hub, new Authorization(new HubRegistry($hub)))->subscribe($table, false));
        self::assertSame($url, new SignalSubscriptions(new SignalTopics(), $hub, null, new RequestStack([Request::create('https://hub.test/table')]))->subscribe($table, false));
        self::assertSame($url, new SignalSubscriptions(new SignalTopics(), $hub, new Authorization(new HubRegistry($hub)), new RequestStack())->subscribe($table, false));
    }

    public function testNobodyTakesPartWithoutTheApplicationsAccess(): void
    {
        $access = new NoTableAccess();

        self::assertNull($access->participant(ResourceReference::of('table', '7')));
        self::assertFalse($access->isGameMaster(ResourceReference::of('table', '7')));
    }

    private function signal(Audience $audience): Signal
    {
        return new Signal(Signal::newId(), ResourceReference::of('table', '7'), SignalKind::XCard, $audience, new DateTimeImmutable('2026-11-12 20:00:00 UTC'));
    }
}
