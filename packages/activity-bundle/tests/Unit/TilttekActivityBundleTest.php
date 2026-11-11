<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit;

use DateInterval;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Journal;
use Tilttek\Activity\JournalObserver;
use Tilttek\Activity\Log\ActivityLog;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\Checkpoints;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Activity\Verbs;
use Tilttek\ActivityBundle\Infrastructure\Console\CatchUpCommand;
use Tilttek\ActivityBundle\Infrastructure\Console\RebuildCommand;
use Tilttek\ActivityBundle\Infrastructure\Console\StatusCommand;
use Tilttek\ActivityBundle\Infrastructure\DependencyInjection\ActivityServices;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivitySchemaListener;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalActivityLog;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalCheckpoints;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjectionHandler;
use Tilttek\ActivityBundle\Infrastructure\Messenger\MessengerProjections;
use Tilttek\ActivityBundle\Infrastructure\Privacy\ActivityPrivacy;
use Tilttek\ActivityBundle\TilttekActivityBundle;

#[CoversClass(TilttekActivityBundle::class)]
#[CoversClass(ActivityServices::class)]
final class TilttekActivityBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_activity', $this->extension()->getAlias());
        self::assertSame(['service_container', DbalActivityLog::class, DbalCheckpoints::class, ActivitySchemaListener::class, Verbs::class, Projections::class, Projector::class, Journal::class, LiveProjections::class, CatchUpCommand::class, RebuildCommand::class, StatusCommand::class, ...$this->privacy()], array_keys($container->getDefinitions()));
        self::assertEquals([$connection], $container->getDefinition(DbalActivityLog::class)->getArguments());
        self::assertSame([DbalActivityLog::class, true], [(string) $container->getAlias(ActivityLog::class), $container->getAlias(ActivityLog::class)->isPublic()]);
        self::assertEquals([$connection, new Reference('clock')], $container->getDefinition(DbalCheckpoints::class)->getArguments());
        self::assertSame([DbalCheckpoints::class, true], [(string) $container->getAlias(Checkpoints::class), $container->getAlias(Checkpoints::class)->isPublic()]);
        self::assertEquals([$connection], $container->getDefinition(ActivitySchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(ActivitySchemaListener::class)->getTags());
        self::assertSame(['tilttek_activity.projection' => [[]]], $container->getAutoconfiguredInstanceof()[Projection::class]->getTags());
        self::assertSame(['tilttek_activity.observer' => [[]]], $container->getAutoconfiguredInstanceof()[JournalObserver::class]->getTags());
        self::assertSame([Verbs::class, 'of'], $container->getDefinition(Verbs::class)->getFactory());
        self::assertSame([[]], $container->getDefinition(Verbs::class)->getArguments());
        self::assertEquals([new TaggedIteratorArgument('tilttek_activity.projection')], $container->getDefinition(Projections::class)->getArguments());
        self::assertTrue($container->getDefinition(Projections::class)->isPublic());
        self::assertEquals([new Reference(ActivityLog::class), new Reference(Checkpoints::class), 500], $container->getDefinition(Projector::class)->getArguments());
        self::assertTrue($container->getDefinition(Projector::class)->isPublic());
        self::assertEquals([new Reference(ActivityLog::class), new Reference('clock'), new Reference(Verbs::class), new TaggedIteratorArgument('tilttek_activity.observer'), new Definition(DateInterval::class, ['P30D']), new Definition(DateInterval::class, ['PT5M'])], $container->getDefinition(Journal::class)->getArguments());
        self::assertTrue($container->getDefinition(Journal::class)->isPublic());
        self::assertEquals([new Reference(Projector::class), new Reference(Projections::class)], $container->getDefinition(LiveProjections::class)->getArguments());
        self::assertSame(['tilttek_activity.observer' => [[]]], $container->getDefinition(LiveProjections::class)->getTags());

        foreach ([CatchUpCommand::class, RebuildCommand::class, StatusCommand::class] as $command) {
            self::assertEquals([new Reference(Projector::class), new Reference(Projections::class)], $container->getDefinition($command)->getArguments());
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }

        self::assertFalse($container->getDefinition(Verbs::class)->isPublic());
        self::assertFalse($container->getDefinition(DbalActivityLog::class)->isPublic());
    }

    public function testThePrivacyModuleWhenPhilPrivacyBundleIsInstalled(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $container = $this->load([]);
        self::assertEquals([new Reference(Journal::class), 'member', 500], $container->getDefinition(ActivityPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(ActivityPrivacy::class)->getTags());
        self::assertEquals([new Reference(Journal::class), 'user', 50], $this->load(['batch_size' => 50, 'privacy' => ['member_kind' => 'user']])->getDefinition(ActivityPrivacy::class)->getArguments());
    }

    public function testNoPrivacyModuleWithoutPhilPrivacyBundle(): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is installed: its absence is checked where it is missing.');
        }

        self::assertFalse($this->load([])->hasDefinition(ActivityPrivacy::class));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['store' => 'memory', 'verbs' => ['game.*', 'news.commented'], 'batch_size' => 50, 'projections' => 'manual']);

        self::assertSame(['service_container', InMemoryActivityLog::class, InMemoryCheckpoints::class, Verbs::class, Projections::class, Projector::class, Journal::class, CatchUpCommand::class, RebuildCommand::class, StatusCommand::class, ...$this->privacy()], array_keys($container->getDefinitions()));
        self::assertSame([InMemoryActivityLog::class, true], [(string) $container->getAlias(ActivityLog::class), $container->getAlias(ActivityLog::class)->isPublic()]);
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryActivityLog::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryCheckpoints::class)->getTags());
        self::assertSame([InMemoryCheckpoints::class, true], [(string) $container->getAlias(Checkpoints::class), $container->getAlias(Checkpoints::class)->isPublic()]);
        self::assertSame([['game.*', 'news.commented']], $container->getDefinition(Verbs::class)->getArguments());
        self::assertSame(50, $container->getDefinition(Projector::class)->getArgument(2));

        self::assertSame(1, $this->load(['batch_size' => 1])->getDefinition(Projector::class)->getArgument(2));

        $dated = $this->load(['dates' => ['past' => 'P1Y', 'ahead' => 'PT1H']])->getDefinition(Journal::class);
        self::assertEquals([new Definition(DateInterval::class, ['P1Y']), new Definition(DateInterval::class, ['PT1H'])], [$dated->getArgument(4), $dated->getArgument(5)]);
        self::assertNull($this->load(['dates' => ['past' => null]])->getDefinition(Journal::class)->getArgument(4));
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DbalActivityLog::class));

        $other = $this->load(['connection' => 'activity']);
        self::assertEquals([new Reference('doctrine.dbal.activity_connection')], $other->getDefinition(DbalActivityLog::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'activity']]], $other->getDefinition(ActivitySchemaListener::class)->getTags());
    }

    public function testTheProjectionsFollowTheJournalThroughMessenger(): void
    {
        $container = $this->load(['projections' => 'messenger']);

        self::assertFalse($container->hasDefinition(LiveProjections::class));
        self::assertEquals([new Reference('messenger.bus.default'), new Reference(Projections::class)], $container->getDefinition(MessengerProjections::class)->getArguments());
        self::assertSame(['tilttek_activity.observer' => [[]]], $container->getDefinition(MessengerProjections::class)->getTags());
        self::assertEquals([new Reference(Projector::class), new Reference(Projections::class), new Reference(Checkpoints::class)], $container->getDefinition(CatchUpProjectionHandler::class)->getArguments());
        self::assertSame(['messenger.message_handler' => [['handles' => CatchUpProjection::class, 'bus' => 'messenger.bus.default']]], $container->getDefinition(CatchUpProjectionHandler::class)->getTags());
        self::assertTrue($container->getDefinition(CatchUpProjectionHandler::class)->isPublic());

        $other = $this->load(['projections' => 'messenger', 'projection_bus' => 'projections.bus']);

        self::assertEquals(new Reference('projections.bus'), $other->getDefinition(MessengerProjections::class)->getArgument(0));
        self::assertSame([['handles' => CatchUpProjection::class, 'bus' => 'projections.bus']], $other->getDefinition(CatchUpProjectionHandler::class)->getTag('messenger.message_handler'));
        self::assertFalse($this->load([])->hasDefinition(MessengerProjections::class));
        self::assertFalse($this->load(['projections' => 'live'])->hasDefinition(CatchUpProjectionHandler::class));
        self::assertTrue($this->load(['projections' => 'live'])->hasDefinition(LiveProjections::class));
    }

    public function testTheProjectionsFollowTheJournalOneWayOfThree(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['projections' => 'cron']);
    }

    public function testTheBusOfTheProjectionsIsNamed(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['projection_bus' => '']);
    }

    public function testAWrongVerbPatternFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains('"Game" is not a verb');

        $this->load(['verbs' => ['Game']]);
    }

    public function testTheBatchSizeIsAtLeastOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['batch_size' => 0]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function wrongDates(): iterable
    {
        yield 'past' => [['past' => '30 days']];
        yield 'empty past' => [['past' => '']];
        yield 'ahead' => [['ahead' => 'P']];
        yield 'no ahead' => [['ahead' => null]];
    }

    /**
     * @param array<string, mixed> $dates
     */
    #[DataProvider('wrongDates')]
    public function testTheDatesAreIso8601Durations(array $dates): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['dates' => $dates]);
    }

    public function testTheStoreIsDoctrineOrMemory(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['store' => 'redis']);
    }

    /**
     * The privacy module, registered when phil/privacy-bundle is installed.
     *
     * @return list<class-string>
     */
    private function privacy(): array
    {
        return interface_exists(PersonalDataEraser::class) ? [ActivityPrivacy::class] : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekActivityBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
