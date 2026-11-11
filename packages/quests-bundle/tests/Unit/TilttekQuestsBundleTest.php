<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit;

use Phil\Almanac\Almanac;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Quests\Catalog;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\QuestProjection;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Quests;
use Tilttek\Quests\Store\CompletionStore;
use Tilttek\Quests\Store\ContributionStore;
use Tilttek\Quests\Store\DuelStore;
use Tilttek\Quests\Store\EnrolmentStore;
use Tilttek\Quests\Store\InMemoryCompletionStore;
use Tilttek\Quests\Store\InMemoryContributionStore;
use Tilttek\Quests\Store\InMemoryDuelStore;
use Tilttek\Quests\Store\InMemoryEnrolmentStore;
use Tilttek\Quests\WritableCatalog;
use Tilttek\QuestsBundle\Infrastructure\Almanac\AlmanacScheduleReader;
use Tilttek\QuestsBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\QuestsBundle\Infrastructure\Console\ListCommand;
use Tilttek\QuestsBundle\Infrastructure\Console\ShowCommand;
use Tilttek\QuestsBundle\Infrastructure\DependencyInjection\QuestsServices;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalCompletionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalContributionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalDuelStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalEnrolmentStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsSchemaListener;
use Tilttek\QuestsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\QuestsBundle\Infrastructure\Privacy\QuestsPrivacy;
use Tilttek\QuestsBundle\Infrastructure\Twig\QuestsExtension;
use Tilttek\QuestsBundle\TilttekQuestsBundle;

#[CoversClass(TilttekQuestsBundle::class)]
#[CoversClass(QuestsServices::class)]
final class TilttekQuestsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_quests', $this->extension()->getAlias());
        self::assertSame([
            'service_container', DbalContributionStore::class, DbalEnrolmentStore::class, DbalCompletionStore::class, DbalDuelStore::class, QuestsSchemaListener::class,
            QuestReader::class, ...$this->almanac(), Catalog::class, DispatchingObserver::class, Evaluator::class, Quests::class, Duels::class, QuestProjection::class,
            ListCommand::class, ShowCommand::class, ExpireCommand::class, QuestsExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));

        foreach ([ContributionStore::class => DbalContributionStore::class, EnrolmentStore::class => DbalEnrolmentStore::class, CompletionStore::class => DbalCompletionStore::class, DuelStore::class => DbalDuelStore::class] as $port => $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
            self::assertFalse($container->getDefinition($store)->isPublic());
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
        }

        self::assertEquals([$connection], $container->getDefinition(QuestsSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(QuestsSchemaListener::class)->getTags());
        self::assertEquals(['UTC', array_map(static fn (string $reader): Reference => new Reference($reader), $this->almanac())], $container->getDefinition(QuestReader::class)->getArguments());
        self::assertSame(InMemoryCatalog::class, $container->getDefinition(Catalog::class)->getClass());
        self::assertSame([InMemoryCatalog::class, 'fromArray'], $container->getDefinition(Catalog::class)->getFactory());
        self::assertEquals([[], new Reference(QuestReader::class)], $container->getDefinition(Catalog::class)->getArguments());
        self::assertTrue($container->getDefinition(Catalog::class)->isPublic());
        self::assertEquals([new Reference(EventDispatcherInterface::class)], $container->getDefinition(DispatchingObserver::class)->getArguments());
        self::assertEquals([new Reference(Catalog::class), new Reference(ContributionStore::class), new Reference(EnrolmentStore::class), new Reference(CompletionStore::class), new Reference(DuelStore::class)], $container->getDefinition(Evaluator::class)->getArguments());
        self::assertEquals([new Reference(Evaluator::class), new Reference('clock')], $container->getDefinition(Quests::class)->getArguments());
        self::assertTrue($container->getDefinition(Quests::class)->isPublic());
        self::assertEquals([new Reference(Evaluator::class), new Reference('clock'), new Reference(DispatchingObserver::class), 'P7D'], $container->getDefinition(Duels::class)->getArguments());
        self::assertTrue($container->getDefinition(Duels::class)->isPublic());
        self::assertEquals([new Reference(Evaluator::class), new Reference(DispatchingObserver::class), 'quests'], $container->getDefinition(QuestProjection::class)->getArguments());
        self::assertSame(['tilttek_activity.projection' => [[]], 'kernel.reset' => [['method' => 'clearReplay']]], $container->getDefinition(QuestProjection::class)->getTags());
        self::assertTrue($container->getDefinition(QuestProjection::class)->isPublic());
        self::assertEquals([new Reference(Catalog::class)], $container->getDefinition(ListCommand::class)->getArguments());
        self::assertEquals([new Reference(Quests::class)], $container->getDefinition(ShowCommand::class)->getArguments());
        self::assertEquals([new Reference(Duels::class)], $container->getDefinition(ExpireCommand::class)->getArguments());

        foreach ([ListCommand::class, ShowCommand::class, ExpireCommand::class] as $command) {
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }

        self::assertEquals([new Reference(Quests::class), new Reference(Duels::class), 'tilttek_quests'], $container->getDefinition(QuestsExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(QuestsExtension::class)->getTags());
        self::assertTrue($container->getDefinition(QuestsExtension::class)->isPublic());
    }

    public function testThePrivacyModuleWhenPhilPrivacyBundleIsInstalled(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $container = $this->load([]);

        self::assertEquals([new Reference(Quests::class), 'member'], $container->getDefinition(QuestsPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(QuestsPrivacy::class)->getTags());
        self::assertSame('user', $this->load(['privacy' => ['member_kind' => 'user']])->getDefinition(QuestsPrivacy::class)->getArgument(1));
    }

    public function testNoPrivacyModuleWithoutPhilPrivacyBundle(): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is installed: its absence is checked where it is missing.');
        }

        self::assertFalse($this->load([])->hasDefinition(QuestsPrivacy::class));
    }

    public function testQuestsThatComeBackWhenPhilAlmanacIsInstalled(): void
    {
        if (!class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is not installed: quests that come back at computed dates are an optional integration.');
        }

        $hunt = ['recurs' => ['from' => 'easter - 2 days', 'until' => 'easter + 1 day'], 'steps' => [['verbs' => ['egg.found'], 'count' => 3]]];
        $container = $this->load(['store' => 'memory', 'quests' => ['easter-hunt' => $hunt]]);

        self::assertTrue($container->hasDefinition(AlmanacScheduleReader::class));
        self::assertSame(['easter-hunt' => $hunt], $container->getDefinition(Catalog::class)->getArgument(0));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $quests = [
            'first-steps' => ['steps' => [['verbs' => ['game.played']]], 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 5]]],
            'Weekly_Hunt' => ['steps' => [['verbs' => ['egg.found'], 'count' => 3]]],
        ];
        $container = $this->load([
            'store' => 'memory',
            'catalog' => 'configuration',
            'timezone' => 'America/Toronto',
            'quests' => ['first-steps' => $quests['first-steps'], 'weekly-hunt' => $quests['Weekly_Hunt']],
            'duels' => ['offer_lifetime' => 'P2D'],
            'projection' => 'missions',
            'translation_domain' => 'missions',
        ]);

        self::assertSame([
            'service_container', InMemoryContributionStore::class, InMemoryEnrolmentStore::class, InMemoryCompletionStore::class, InMemoryDuelStore::class,
            QuestReader::class, ...$this->almanac(), Catalog::class, DispatchingObserver::class, Evaluator::class, Quests::class, Duels::class, QuestProjection::class,
            ListCommand::class, ShowCommand::class, ExpireCommand::class, QuestsExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryDuelStore::class)->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryContributionStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryEnrolmentStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryCompletionStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryDuelStore::class)->getTags());
        self::assertSame([InMemoryDuelStore::class, true], [(string) $container->getAlias(DuelStore::class), $container->getAlias(DuelStore::class)->isPublic()]);
        self::assertSame('America/Toronto', $container->getDefinition(QuestReader::class)->getArgument(0));
        self::assertSame(['first-steps' => $quests['first-steps'], 'weekly-hunt' => $quests['Weekly_Hunt']], $container->getDefinition(Catalog::class)->getArgument(0));
        self::assertSame('P2D', $container->getDefinition(Duels::class)->getArgument(3));
        self::assertSame('missions', $container->getDefinition(QuestProjection::class)->getArgument(2));
        self::assertSame('missions', $container->getDefinition(QuestsExtension::class)->getArgument(2));

        $other = $this->load(['connection' => 'gamification', 'catalog' => 'doctrine']);
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection')], $other->getDefinition(DbalDuelStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'gamification']]], $other->getDefinition(QuestsSchemaListener::class)->getTags());
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection'), new Reference(QuestReader::class)], $other->getDefinition(DbalCatalog::class)->getArguments());
        self::assertFalse($other->hasDefinition(Catalog::class));
        self::assertSame([DbalCatalog::class, true], [(string) $other->getAlias(Catalog::class), $other->getAlias(Catalog::class)->isPublic()]);
        self::assertSame([DbalCatalog::class, true], [(string) $other->getAlias(WritableCatalog::class), $other->getAlias(WritableCatalog::class)->isPublic()]);
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DbalContributionStore::class));
    }

    public function testAWrongQuestFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidQuest::because('The steps of "broken" are a list.'));
        $this->load(['quests' => ['broken' => ['kind' => 'personal']]]);
    }

    public function testAWrongRecurrenceFailsWhenTheContainerIsBuilt(): void
    {
        if (!class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is not installed: quests that come back at computed dates are an optional integration.');
        }

        $this->expectExceptionObject(InvalidQuest::because('A quest that comes back recurs from a rule, until another rule or for so many days.'));
        $this->load(['quests' => ['broken' => ['recurs' => 'yearly', 'steps' => [['verbs' => ['a.b']]]]]]);
    }

    public function testATimezoneThatIsNoneFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidQuest::because('"Mars/Olympus" is not a timezone.'));
        $this->load(['timezone' => 'Mars/Olympus']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis'], 'The value "redis" is not allowed'];
        yield 'catalog' => [['catalog' => 'yaml'], 'The value "yaml" is not allowed'];
        yield 'both catalogs' => [['catalog' => 'doctrine', 'quests' => ['q' => ['steps' => [['verbs' => ['a.b']]]]]], 'The quests are configured here or kept in the database, not both.'];
        yield 'empty connection' => [['connection' => ''], 'cannot contain an empty value'];
        yield 'empty timezone' => [['timezone' => ''], 'cannot contain an empty value'];
        yield 'empty projection' => [['projection' => ''], 'cannot contain an empty value'];
        yield 'empty domain' => [['translation_domain' => ''], 'cannot contain an empty value'];
        yield 'empty member kind' => [['privacy' => ['member_kind' => '']], 'cannot contain an empty value'];
        yield 'empty lifetime' => [['duels' => ['offer_lifetime' => '']], 'cannot contain an empty value'];
        yield 'lifetime that is no duration' => [['duels' => ['offer_lifetime' => 'soon']], '"soon" is not an ISO 8601 duration of some time, like "P7D".'];
        yield 'lifetime of no time' => [['duels' => ['offer_lifetime' => 'PT0S']], '"PT0S" is not an ISO 8601 duration of some time'];
        yield 'lifetime that is no text' => [['duels' => ['offer_lifetime' => 7]], '7 is not an ISO 8601 duration of some time'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    /**
     * @return list<class-string> the reader of recurring quests, registered when `phil/almanac` is installed
     */
    private function almanac(): array
    {
        return class_exists(Almanac::class) ? [AlmanacScheduleReader::class] : [];
    }

    /**
     * @return list<class-string> the privacy module, registered when `phil/privacy-bundle` is installed
     */
    private function privacy(): array
    {
        return interface_exists(PersonalDataEraser::class) ? [QuestsPrivacy::class] : [];
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
        $extension = new TilttekQuestsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
