<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\DependencyInjection;

use DateTimeZone;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Exception;
use Phil\Almanac\Almanac;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\CatalogReader;
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
use Tilttek\CollectionsBundle\Infrastructure\Twig\CollectionsExtension;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\Exchange\Trade\Inventory;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the five stores on Doctrine DBAL or in memory, the catalog from the configuration — checked
 * when the container is compiled — or from the database, `Collections`, `Packs` with secure seeds, `DiceShelf`, the
 * calendar of the item of the day with its fixed and computed dates, `Daily` and its projection tagged for
 * `tilttek/activity-bundle`, `MemberData`, the observer telling the event dispatcher, and — when their packages are
 * installed — the inventory of `tilttek/exchange`, the dice appearance of `tilttek/dice-bundle`, the schema listener,
 * the commands and the Twig functions.
 *
 * @phpstan-type Config array{store: string, connection: string, timezone: string, locale: string, catalog: string, rarities: array<string, mixed>, items: array<string, mixed>, sets: array<string, mixed>, packs: array<string, mixed>, daily: array{enabled: bool, verbs: list<string>, salt: ?string, sets: list<string>, dates: array<string, string>, computed: list<array{rule: string, item: string, days: int}>, projection: string}, translation_domain: string, privacy: array{member_kind: string}}
 */
final class CollectionsServices
{
    /**
     * The tag under which `tilttek/activity-bundle` finds its projections.
     */
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    /**
     * The name the projection of the item of the day is kept under, unless the configuration names another.
     */
    public const string DEFAULT_PROJECTION = DailyProjection::NAME;

    /**
     * The service of `MemberData`, for the privacy module the bundle class registers.
     */
    public const string MEMBER_DATA = MemberData::class;

    /**
     * The salt of the item of the day when the configuration gives none: a label and the kernel's secret, so that no
     * one without the secret can compute the days to come.
     */
    public const string DERIVED_SALT = 'tilttek_collections.daily:%kernel.secret%';

    private const array STORES = [
        CopyStore::class => [InMemoryCopyStore::class, DbalCopyStore::class],
        CompletionStore::class => [InMemoryCompletionStore::class, DbalCompletionStore::class],
        OpeningStore::class => [InMemoryOpeningStore::class, DbalOpeningStore::class],
        ChoiceStore::class => [InMemoryChoiceStore::class, DbalChoiceStore::class],
        DailyStore::class => [InMemoryDailyStore::class, DbalDailyStore::class],
    ];

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        try {
            new DateTimeZone($config['timezone']);
        } catch (Exception) {
            throw InvalidCollection::because(\sprintf('"%s" is not a timezone.', $config['timezone']));
        }

        $catalog = ['rarities' => $config['rarities'], 'items' => $config['items'], 'sets' => $config['sets'], 'packs' => $config['packs']];
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        foreach (self::STORES as $port => [$memory, $dbal]) {
            $store = 'memory' === $config['store'] ? $memory : $dbal;
            $definition = $container->register($store, $store);

            if ('memory' === $config['store']) {
                $definition->addTag('kernel.reset', ['method' => 'reset']);
            } else {
                $definition->setArguments([$connection]);
            }

            $container->setAlias($port, $store)->setPublic(true);
        }

        if ('memory' !== $config['store'] && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(CollectionsSchemaListener::class, CollectionsSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if ('doctrine' === $config['catalog']) {
            $container->register(DbalCatalog::class, DbalCatalog::class)->setArguments([$connection]);
            $container->setAlias(CatalogProvider::class, DbalCatalog::class)->setPublic(true);
            $container->setAlias(WritableCatalog::class, DbalCatalog::class)->setPublic(true);
        } else {
            CatalogReader::read($catalog);
            $container->register(CatalogProvider::class, FixedCatalog::class)
                ->setFactory([FixedCatalog::class, 'fromArray'])
                ->setArguments([$catalog])
                ->setPublic(true);
        }

        $container->register(DispatchingObserver::class, DispatchingObserver::class)
            ->setArguments([new Reference(EventDispatcherInterface::class)]);
        $container->register(Collections::class, Collections::class)
            ->setArguments([new Reference(CatalogProvider::class), new Reference(CopyStore::class), new Reference(CompletionStore::class), new Reference('clock'), new Reference(DispatchingObserver::class)])
            ->setPublic(true);
        $container->register(SecureSeeds::class, SecureSeeds::class);
        $container->setAlias(SeedSource::class, SecureSeeds::class);
        $container->register(Packs::class, Packs::class)
            ->setArguments([new Reference(Collections::class), new Reference(OpeningStore::class), new Reference(SeedSource::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(DiceShelf::class, DiceShelf::class)
            ->setArguments([new Reference(Collections::class), new Reference(ChoiceStore::class)])
            ->setPublic(true);
        $container->register(DailyCalendar::class, DailyCalendar::class)
            ->setArguments([new Reference(CatalogProvider::class), new Reference(DailyStore::class), new Definition(DateTimeZone::class, [$config['timezone']]), $config['daily']['salt'] ?? self::DERIVED_SALT, self::rules($config['daily']), $config['daily']['sets']])
            ->setPublic(true);
        $container->register(Daily::class, Daily::class)
            ->setArguments([new Reference(DailyCalendar::class), new Reference(Collections::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(MemberData::class, MemberData::class)
            ->setArguments([new Reference(CopyStore::class), new Reference(CompletionStore::class), new Reference(OpeningStore::class), new Reference(ChoiceStore::class)])
            ->setPublic(true);

        if ($config['daily']['enabled']) {
            $container->register(DailyProjection::class, DailyProjection::class)
                ->setArguments([new Reference(Daily::class), $config['daily']['verbs'], $config['daily']['projection']])
                ->addTag(self::PROJECTION_TAG)
                ->setPublic(true);
        }

        if (interface_exists(Inventory::class)) {
            $container->register(CollectionsInventory::class, CollectionsInventory::class)
                ->setArguments([new Reference(Collections::class)])
                ->setPublic(true);
        }

        if (interface_exists(DiceAppearance::class)) {
            $container->register(CollectionDiceAppearance::class, CollectionDiceAppearance::class)
                ->setArguments([new Reference(DiceShelf::class), $config['locale']])
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            $container->register(DailyCommand::class, DailyCommand::class)->setArguments([new Reference(DailyCalendar::class), new Reference('clock')])->addTag('console.command');
            $container->register(GrantCommand::class, GrantCommand::class)->setArguments([new Reference(Collections::class)])->addTag('console.command');
            $container->register(AlbumCommand::class, AlbumCommand::class)->setArguments([new Reference(Collections::class)])->addTag('console.command');
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(CollectionsExtension::class, CollectionsExtension::class)
                ->setArguments([new Reference(Collections::class), new Reference(Packs::class), new Reference(Daily::class), $config['translation_domain']])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }

    /**
     * The rules of the calendar: the fixed dates, then the dates `phil/almanac` computes.
     *
     * @param array{dates: array<string, string>, computed: list<array{rule: string, item: string, days: int}>, ...} $daily
     *
     * @return list<Definition>
     */
    private static function rules(array $daily): array
    {
        $rules = [];

        foreach ($daily['dates'] as $date => $item) {
            new FixedDate($date, $item);
            $rules[] = new Definition(FixedDate::class, [$date, $item]);
        }

        foreach ($daily['computed'] as $computed) {
            if (!class_exists(Almanac::class)) {
                throw InvalidCollection::because('The computed dates of the item of the day need phil/almanac.');
            }

            new AlmanacDateRule($computed['rule'], $computed['item'], $computed['days']);
            $rules[] = new Definition(AlmanacDateRule::class, [$computed['rule'], $computed['item'], $computed['days']]);
        }

        return $rules;
    }
}
