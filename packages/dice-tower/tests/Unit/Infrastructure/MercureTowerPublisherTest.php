<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Application\TowerTopics;
use Tilttek\DiceTower\Infrastructure\Mercure\MercureTowerPublisher;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingHub;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingLogger;

#[CoversClass(MercureTowerPublisher::class)]
#[CoversClass(TowerTopics::class)]
final class MercureTowerPublisherTest extends TestCase
{
    public function testAnEntryIsPushedPrivatelyToTheTopicOfItsTable(): void
    {
        $hub = new RecordingHub();
        $entry = $this->entry();

        new MercureTowerPublisher($hub, new TowerTopics())->publish($entry);

        self::assertCount(1, $hub->updates);
        self::assertSame(['urn:tilttek:dice-tower:table%3Agrand%20hall'], $hub->updates[0]->getTopics());
        self::assertTrue($hub->updates[0]->isPrivate());
        self::assertSame(json_encode($entry->toArray(), \JSON_THROW_ON_ERROR), $hub->updates[0]->getData());
    }

    public function testTheTopicsHaveTheConfiguredPrefix(): void
    {
        $hub = new RecordingHub();
        new MercureTowerPublisher($hub, new TowerTopics('https://example.com/towers/'))->publish($this->entry());

        self::assertSame(['https://example.com/towers/table%3Agrand%20hall'], $hub->updates[0]->getTopics());
    }

    public function testAHubThatCannotBeReachedIsLogged(): void
    {
        $logger = new RecordingLogger();
        new MercureTowerPublisher(new RecordingHub(failing: true), new TowerTopics(), $logger)->publish($this->entry());

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0][0]);
        self::assertSame('The entry 3 of the tower of "table:grand hall" could not be pushed to Mercure: The hub is down.', $logger->records[0][1]);
        self::assertInstanceOf(RuntimeException::class, $logger->records[0][2]['exception']);
        self::assertSame('The hub is down.', $logger->records[0][2]['message']);

        new MercureTowerPublisher(new RecordingHub(failing: true), new TowerTopics())->publish($this->entry());
    }

    public function testWithoutHubNothingIsPushed(): void
    {
        $logger = new RecordingLogger();
        new MercureTowerPublisher(null, new TowerTopics(), $logger)->publish($this->entry());

        self::assertSame([], $logger->records);
    }

    private function entry(): TowerEntry
    {
        return new TowerEntry(3, 'table:grand hall', 'abc', 'member:1', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), []);
    }
}
