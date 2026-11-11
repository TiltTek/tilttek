<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit;

use DateTimeZone;
use Phil\Almanac\Almanac;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Daily\Daily;
use Tilttek\Collections\Daily\DailyCalendar;
use Tilttek\Collections\Daily\DailyProjection;
use Tilttek\Collections\Daily\DailyStore;
use Tilttek\Collections\Daily\FixedDate;
use Tilttek\Collections\Daily\InMemoryDailyStore;
use Tilttek\Collections\Dice\ChoiceStore;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Dice\InMemoryChoiceStore;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\MemberData;
use Tilttek\Collections\Pack\InMemoryOpeningStore;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\Collections\Pack\Packs;
use Tilttek\Collections\Pack\SecureSeeds;
use Tilttek\Collections\Pack\SeedSource;
use Tilttek\Collections\Store\CompletionStore;
use Tilttek\Collections\Store\CopyStore;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;
use Tilttek\Collections\WritableCatalog;
use Tilttek\CollectionsBundle\Infrastructure\Almanac\AlmanacDateRule;
use Tilttek\CollectionsBundle\Infrastructure\Console\AlbumCommand;
use Tilttek\CollectionsBundle\Infrastructure\Console\DailyCommand;
use Tilttek\CollectionsBundle\Infrastructure\Console\GrantCommand;
use Tilttek\CollectionsBundle\Infrastructure\DependencyInjection\CollectionsServices;
use Tilttek\CollectionsBundle\Infrastructure\Dice\CollectionDiceAppearance;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\CollectionsSchemaListener;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalChoiceStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalCompletionStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalCopyStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalDailyStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalOpeningStore;
use Tilttek\CollectionsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\CollectionsBundle\Infrastructure\Exchange\CollectionsInventory;
use Tilttek\CollectionsBundle\Infrastructure\Privacy\CollectionsPrivacy;
use Tilttek\CollectionsBundle\Infrastructure\Twig\CollectionsExtension;
use Tilttek\CollectionsBundle\TilttekCollectionsBundle;

#[CoversClass(TilttekCollectionsBundle::class)]
#[CoversClass(CollectionsServices::class)]
final class TilttekCollectionsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_collections', $this->extension()->getAlias());
        self::assertSame([
            'service_container', DbalCopyStore::class, DbalCompletionStore::class, DbalOpeningStore::class, DbalChoiceStore::class, DbalDailyStore::class, CollectionsSchemaListener::class,
            CatalogProvider::class, DispatchingObserver::class, Collections::class, SecureSeeds::class, Packs::class, DiceShelf::class, DailyCalendar::class, Daily::class, MemberData::class,
            CollectionsInventory::class, CollectionDiceAppearance::class, DailyCommand::class, GrantCommand::class, AlbumCommand::class, CollectionsExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));

        foreach ([CopyStore::class => DbalCopyStore::class, CompletionStore::class => DbalCompletionStore::class, OpeningStore::class => DbalOpeningStore::class, ChoiceStore::class => DbalChoiceStore::class, DailyStore::class => DbalDailyStore::class] as $port => $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
            self::assertFalse($container->getDefinition($store)->isPublic());
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
        }

        self::assertEquals([$connection], $container->getDefinition(CollectionsSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(CollectionsSchemaListener::class)->getTags());
        self::assertSame(FixedCatalog::class, $container->getDefinition(CatalogProvider::class)->getClass());
        self::assertSame([FixedCatalog::class, 'fromArray'], $container->getDefinition(CatalogProvider::class)->getFactory());
        self::assertSame([['rarities' => [], 'items' => [], 'sets' => [], 'packs' => []]], $container->getDefinition(CatalogProvider::class)->getArguments());
        self::assertTrue($container->getDefinition(CatalogProvider::class)->isPublic());
        self::assertEquals([new Reference(EventDispatcherInterface::class)], $container->getDefinition(DispatchingObserver::class)->getArguments());
        self::assertEquals([new Reference(CatalogProvider::class), new Reference(CopyStore::class), new Reference(CompletionStore::class), new Reference('clock'), new Reference(DispatchingObserver::class)], $container->getDefinition(Collections::class)->getArguments());
        self::assertSame([SecureSeeds::class, false], [(string) $container->getAlias(SeedSource::class), $container->getAlias(SeedSource::class)->isPublic()]);
        self::assertFalse($container->getDefinition(SecureSeeds::class)->isPublic());
        self::assertEquals([new Reference(Collections::class), new Reference(OpeningStore::class), new Reference(SeedSource::class), new Reference('clock')], $container->getDefinition(Packs::class)->getArguments());
        self::assertEquals([new Reference(Collections::class), new Reference(ChoiceStore::class)], $container->getDefinition(DiceShelf::class)->getArguments());
        self::assertEquals([new Reference(CatalogProvider::class), new Reference(DailyStore::class), new Definition(DateTimeZone::class, ['UTC']), 'tilttek_collections.daily:%kernel.secret%', [], []], $container->getDefinition(DailyCalendar::class)->getArguments());
        self::assertEquals([new Reference(DailyCalendar::class), new Reference(Collections::class), new Reference('clock')], $container->getDefinition(Daily::class)->getArguments());
        self::assertEquals([new Reference(CopyStore::class), new Reference(CompletionStore::class), new Reference(OpeningStore::class), new Reference(ChoiceStore::class)], $container->getDefinition(MemberData::class)->getArguments());

        foreach ([Collections::class, Packs::class, DiceShelf::class, DailyCalendar::class, Daily::class, MemberData::class, CollectionsInventory::class, CollectionDiceAppearance::class, CollectionsExtension::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        self::assertEquals([new Reference(Collections::class)], $container->getDefinition(CollectionsInventory::class)->getArguments());
        self::assertEquals([new Reference(DiceShelf::class), '%kernel.default_locale%'], $container->getDefinition(CollectionDiceAppearance::class)->getArguments());
        self::assertEquals([new Reference(DailyCalendar::class), new Reference('clock')], $container->getDefinition(DailyCommand::class)->getArguments());
        self::assertEquals([new Reference(Collections::class)], $container->getDefinition(GrantCommand::class)->getArguments());
        self::assertEquals([new Reference(Collections::class)], $container->getDefinition(AlbumCommand::class)->getArguments());

        foreach ([DailyCommand::class, GrantCommand::class, AlbumCommand::class] as $command) {
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }

        self::assertEquals([new Reference(Collections::class), new Reference(Packs::class), new Reference(Daily::class), 'tilttek_collections'], $container->getDefinition(CollectionsExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(CollectionsExtension::class)->getTags());
    }

    public function testThePrivacyModuleWhenPhilPrivacyBundleIsInstalled(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }

        $container = $this->load([]);

        self::assertEquals([new Reference(MemberData::class), 'member'], $container->getDefinition(CollectionsPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(CollectionsPrivacy::class)->getTags());
        self::assertSame('user', $this->load(['privacy' => ['member_kind' => 'user']])->getDefinition(CollectionsPrivacy::class)->getArgument(1));
    }

    public function testNoPrivacyModuleWithoutPhilPrivacyBundle(): void
    {
        if (interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is installed: its absence is checked where it is missing.');
        }

        self::assertFalse($this->load([])->hasDefinition(CollectionsPrivacy::class));
    }

    public function testComputedDatesReachTheCalendarWhenPhilAlmanacIsInstalled(): void
    {
        if (!class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is not installed: the computed dates of the item of the day are an optional integration.');
        }

        $container = $this->load(['daily' => ['computed' => [['rule' => 'easter', 'item' => 'ember-d6'], ['rule' => 'fourth thursday of november', 'item' => 'ember-d6', 'days' => 4]]]]);

        self::assertEquals(
            [new Definition(AlmanacDateRule::class, ['easter', 'ember-d6', 1]), new Definition(AlmanacDateRule::class, ['fourth thursday of november', 'ember-d6', 4])],
            $container->getDefinition(DailyCalendar::class)->getArgument(4),
        );

        $this->expectExceptionObject(InvalidCollection::because('"someday" is not a rule the almanac reads.'));

        $this->load(['daily' => ['computed' => [['rule' => 'someday', 'item' => 'a']]]]);
    }

    public function testComputedDatesNeedPhilAlmanac(): void
    {
        if (class_exists(Almanac::class)) {
            self::markTestSkipped('phil/almanac is installed: its absence is checked where it is missing.');
        }

        $this->expectExceptionObject(InvalidCollection::because('The computed dates of the item of the day need phil/almanac.'));

        $this->load(['daily' => ['computed' => [['rule' => 'easter', 'item' => 'a']]]]);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $catalog = [
            'rarities' => ['common' => 3, 'rare' => ['weight' => 1]],
            'items' => ['ember-d6' => ['name' => 'Ember d6', 'rarity' => 'common', 'sets' => ['ember-set']], 'pirate-hat' => ['name' => 'Hat', 'rarity' => 'rare']],
            'sets' => ['ember-set' => ['name' => 'Ember', 'kind' => 'base']],
            'packs' => ['big-booster' => ['name' => 'Booster', 'size' => 3]],
        ];
        $container = $this->load([
            'store' => 'memory',
            'timezone' => 'America/Toronto',
            'locale' => 'fr',
            ...$catalog,
            'daily' => ['enabled' => true, 'verbs' => ['page.viewed'], 'salt' => str_repeat('p', 32), 'sets' => ['ember-set'], 'dates' => ['10-31' => 'ember-d6', '2026-12-25' => 'ember-d6'], 'projection' => 'daily-gift'],
            'translation_domain' => 'gallery',
        ]);

        self::assertSame([
            'service_container', InMemoryCopyStore::class, InMemoryCompletionStore::class, InMemoryOpeningStore::class, InMemoryChoiceStore::class, InMemoryDailyStore::class,
            CatalogProvider::class, DispatchingObserver::class, Collections::class, SecureSeeds::class, Packs::class, DiceShelf::class, DailyCalendar::class, Daily::class, MemberData::class, DailyProjection::class,
            CollectionsInventory::class, CollectionDiceAppearance::class, DailyCommand::class, GrantCommand::class, AlbumCommand::class, CollectionsExtension::class, ...$this->privacy(),
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryCopyStore::class)->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryCopyStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryCompletionStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryOpeningStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryChoiceStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryDailyStore::class)->getTags());
        self::assertSame([InMemoryDailyStore::class, true], [(string) $container->getAlias(DailyStore::class), $container->getAlias(DailyStore::class)->isPublic()]);
        self::assertSame([$catalog], $container->getDefinition(CatalogProvider::class)->getArguments());
        self::assertEquals([
            new Reference(CatalogProvider::class), new Reference(DailyStore::class), new Definition(DateTimeZone::class, ['America/Toronto']), str_repeat('p', 32),
            [new Definition(FixedDate::class, ['10-31', 'ember-d6']), new Definition(FixedDate::class, ['2026-12-25', 'ember-d6'])],
            ['ember-set'],
        ], $container->getDefinition(DailyCalendar::class)->getArguments());
        self::assertEquals([new Reference(Daily::class), ['page.viewed'], 'daily-gift'], $container->getDefinition(DailyProjection::class)->getArguments());
        self::assertSame(['tilttek_activity.projection' => [[]]], $container->getDefinition(DailyProjection::class)->getTags());
        self::assertTrue($container->getDefinition(DailyProjection::class)->isPublic());
        self::assertSame('fr', $container->getDefinition(CollectionDiceAppearance::class)->getArgument(1));
        self::assertSame('gallery', $container->getDefinition(CollectionsExtension::class)->getArgument(3));

        $other = $this->load(['connection' => 'gamification', 'catalog' => 'doctrine']);
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection')], $other->getDefinition(DbalCopyStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'gamification']]], $other->getDefinition(CollectionsSchemaListener::class)->getTags());
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection')], $other->getDefinition(DbalCatalog::class)->getArguments());
        self::assertFalse($other->hasDefinition(CatalogProvider::class));
        self::assertSame([DbalCatalog::class, true], [(string) $other->getAlias(CatalogProvider::class), $other->getAlias(CatalogProvider::class)->isPublic()]);
        self::assertSame([DbalCatalog::class, true], [(string) $other->getAlias(WritableCatalog::class), $other->getAlias(WritableCatalog::class)->isPublic()]);
        self::assertFalse($other->hasDefinition(DailyProjection::class));
        self::assertTrue($this->load(['store' => 'doctrine', 'catalog' => 'configuration'])->hasDefinition(DbalCopyStore::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function wrongCatalogs(): iterable
    {
        yield 'an item of an unknown rarity' => [['rarities' => ['common' => 1], 'items' => ['a' => ['name' => 'A', 'rarity' => 'rare']]], 'No rarity is named "rare".'];
        yield 'a timezone that is none' => [['timezone' => 'Mars/Olympus'], '"Mars/Olympus" is not a timezone.'];
        yield 'a fixed date that is none' => [['daily' => ['dates' => ['13-01' => 'a']]], '"13-01" is not a date: a month and a day, such as "10-31", or a full date, such as "2026-12-25".'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongCatalogs')]
    public function testAWrongCatalogFailsWhenTheContainerIsBuilt(array $config, string $message): void
    {
        try {
            $this->load($config);
            self::fail($message);
        } catch (InvalidCollection|NotFound $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis'], 'The value "redis" is not allowed'];
        yield 'catalog' => [['catalog' => 'yaml'], 'The value "yaml" is not allowed'];
        yield 'both catalogs' => [['catalog' => 'doctrine', 'rarities' => ['common' => 1]], 'The catalog is configured here or kept in the database, not both.'];
        yield 'both catalogs by an item' => [['catalog' => 'doctrine', 'items' => ['a' => []]], 'The catalog is configured here or kept in the database, not both.'];
        yield 'both catalogs by a set' => [['catalog' => 'doctrine', 'sets' => ['a' => []]], 'The catalog is configured here or kept in the database, not both.'];
        yield 'both catalogs by a pack' => [['catalog' => 'doctrine', 'packs' => ['a' => []]], 'The catalog is configured here or kept in the database, not both.'];
        yield 'a daily item without verbs' => [['daily' => ['enabled' => true]], 'The item of the day is claimed on at least one verb.'];
        yield 'empty connection' => [['connection' => ''], 'cannot contain an empty value'];
        yield 'empty timezone' => [['timezone' => ''], 'cannot contain an empty value'];
        yield 'empty locale' => [['locale' => ''], 'cannot contain an empty value'];
        yield 'empty salt' => [['daily' => ['salt' => '']], 'The salt of the item of the day is a secret of at least 32 bytes'];
        yield 'short salt' => [['daily' => ['salt' => str_repeat('p', 31)]], 'The salt of the item of the day is a secret of at least 32 bytes'];
        yield 'empty projection' => [['daily' => ['projection' => '']], 'cannot contain an empty value'];
        yield 'empty domain' => [['translation_domain' => ''], 'cannot contain an empty value'];
        yield 'empty member kind' => [['privacy' => ['member_kind' => '']], 'cannot contain an empty value'];
        yield 'a computed date without rule' => [['daily' => ['computed' => [['item' => 'a']]]], 'The child config "rule" under "tilttek_collections.daily.computed.0" must be configured'];
        yield 'a computed date without item' => [['daily' => ['computed' => [['rule' => 'easter']]]], 'The child config "item" under "tilttek_collections.daily.computed.0" must be configured'];
        yield 'a computed date of no day' => [['daily' => ['computed' => [['rule' => 'easter', 'item' => 'a', 'days' => 0]]]], 'Should be greater than or equal to 1'];
        yield 'a computed date of too many days' => [['daily' => ['computed' => [['rule' => 'easter', 'item' => 'a', 'days' => 367]]]], 'Should be less than or equal to 366'];
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
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    /**
     * @return list<class-string> the privacy module, registered when `phil/privacy-bundle` is installed
     */
    private function privacy(): array
    {
        return interface_exists(PersonalDataEraser::class) ? [CollectionsPrivacy::class] : [];
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekCollectionsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
