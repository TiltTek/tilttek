<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\DependencyInjection;

use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Tilttek\Exchange\Allowance;
use Tilttek\Exchange\Announcer;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeErasure;
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\ImmediateTransaction;
use Tilttek\Exchange\NoMemberSince;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reservation\ReservationStore;
use Tilttek\Exchange\Reward\Catalog;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\NoRequirements;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\OfferStore;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\Exchange\Transaction;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\TransferLog;
use Tilttek\Exchange\Transfer\Transfers;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\ExchangeBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\ExchangeBundle\Infrastructure\Console\TransfersCommand;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalOfferStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransaction;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeSchemaListener;
use Tilttek\ExchangeBundle\Infrastructure\Event\DispatchingAnnouncer;
use Tilttek\ExchangeBundle\Infrastructure\Form\OfferType;
use Tilttek\ExchangeBundle\Infrastructure\Form\RedeemRewardType;
use Tilttek\ExchangeBundle\Infrastructure\Form\TransferPointsType;
use Tilttek\ExchangeBundle\Infrastructure\Points\SpendingPointsHold;
use Tilttek\Points\Currencies;
use Tilttek\Points\Points;
use Tilttek\Points\Rule\Period;

/**
 * The bundle's services: the stores on Doctrine DBAL or in memory, the catalogue from the configuration or the
 * database, `Redemptions`, the `PointsHold` of the reserved rewards, and — when the application allows them —
 * `Transfers` and `Trades` with their guards; the events through the application's dispatcher, the erasure, and — when
 * their components are installed — the schema listener, the commands and the forms. The configuration is checked when the container is compiled. The points are
 * those of `tilttek/points-bundle`, whose `Points` and `Currencies` services it uses.
 *
 * @phpstan-type Allowed array{limit: int, period: string}
 * @phpstan-type Config array{store: string, connection: string, catalog: string, rewards: array<string, array{price: int, currency?: ?string, stock?: ?int, available_from?: ?string, available_until?: ?string, per_member?: ?int, requires?: list<string>}>, reservation_lifetime: string, requirements: ?string, member_since: ?string, timezone: string, transfers: array{enabled: bool, allowance?: Allowed, new_member_delay: ?string}, trades: array{enabled: bool, inventory: ?string, offer_lifetime: string, allowance?: Allowed, new_member_delay: ?string}, privacy: array{member_kind: string}}
 */
final class ExchangeServices
{
    public const string DEFAULT_RESERVATION_LIFETIME = Redemptions::DEFAULT_LIFETIME;

    public const string DEFAULT_OFFER_LIFETIME = Trades::DEFAULT_LIFETIME;

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::check($config);
        $doctrine = 'doctrine' === $config['store'];
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        $stores = $doctrine
            ? [ReservationStore::class => DbalReservationStore::class, TransferLog::class => DbalTransferLog::class, OfferStore::class => DbalOfferStore::class, Transaction::class => DbalTransaction::class]
            : [ReservationStore::class => InMemoryReservationStore::class, TransferLog::class => InMemoryTransferLog::class, OfferStore::class => InMemoryOfferStore::class, Transaction::class => ImmediateTransaction::class];

        foreach ($stores as $port => $store) {
            $definition = $container->register($store, $store);

            if ($doctrine) {
                $definition->setArguments([$connection]);
            } elseif (Transaction::class !== $port) {
                $definition->addTag('kernel.reset', ['method' => 'reset']);
            }

            $container->setAlias($port, $store)->setPublic(true);
        }

        if ('doctrine' === $config['catalog']) {
            $container->register(DbalCatalog::class, DbalCatalog::class)->setArguments([$connection]);
            $container->setAlias(Catalog::class, DbalCatalog::class)->setPublic(true);
        } else {
            $container->register(InMemoryCatalog::class, InMemoryCatalog::class)
                ->setFactory([InMemoryCatalog::class, 'fromArray'])
                ->setArguments([$config['rewards']]);
            $container->setAlias(Catalog::class, InMemoryCatalog::class)->setPublic(true);
        }

        if ($doctrine && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(ExchangeSchemaListener::class, ExchangeSchemaListener::class)
                ->setArguments([$connection, 'doctrine' === $config['catalog']])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(DispatchingAnnouncer::class, DispatchingAnnouncer::class)
            ->setArguments([new Reference(EventDispatcherInterface::class)]);
        $container->setAlias(Announcer::class, DispatchingAnnouncer::class);

        $container->register(Redemptions::class, Redemptions::class)
            ->setArguments([
                new Reference(Catalog::class),
                new Reference(ReservationStore::class),
                new Reference(Points::class),
                new Reference(Currencies::class),
                new Reference('clock'),
                null === $config['requirements'] ? new Definition(NoRequirements::class) : new Reference($config['requirements']),
                new Reference(Announcer::class),
                $config['reservation_lifetime'],
            ])
            ->setPublic(true);

        $container->register(SpendingPointsHold::class, SpendingPointsHold::class)->setArguments([new Reference(Points::class)]);
        $container->setAlias(PointsHold::class, SpendingPointsHold::class)->setPublic(true);

        $memberSince = null === $config['member_since'] ? new Definition(NoMemberSince::class) : new Reference($config['member_since']);
        $erasure = [new Reference(ReservationStore::class), new Reference('clock'), null, null];

        if ($config['transfers']['enabled']) {
            $container->register(Transfers::class, Transfers::class)
                ->setArguments([
                    new Reference(TransferLog::class),
                    new Reference(Points::class),
                    new Reference(Currencies::class),
                    new Reference('clock'),
                    self::guards($config['transfers'], $config['timezone'], $memberSince),
                    new Reference(Announcer::class),
                ])
                ->setPublic(true);
            $erasure[2] = new Reference(TransferLog::class);
        }

        if ($config['trades']['enabled']) {
            $inventory = $config['trades']['inventory'] ?? throw InvalidExchange::because('Trades need "trades.inventory", the service that keeps the members\' items.');
            $container->register(Trades::class, Trades::class)
                ->setArguments([
                    new Reference(OfferStore::class),
                    new Reference($inventory),
                    new Reference(Points::class),
                    new Reference(Currencies::class),
                    new Reference('clock'),
                    self::guards($config['trades'], $config['timezone'], $memberSince),
                    new Reference(Transaction::class),
                    new Reference(Announcer::class),
                    $config['trades']['offer_lifetime'],
                ])
                ->setPublic(true);
            $erasure[3] = new Reference(OfferStore::class);
        }

        $container->register(ExchangeErasure::class, ExchangeErasure::class)->setArguments($erasure)->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(ExpireCommand::class, ExpireCommand::class)
                ->setArguments([new Reference(Redemptions::class), $config['trades']['enabled'] ? new Reference(Trades::class) : null])
                ->addTag('console.command');

            if ($config['transfers']['enabled']) {
                $container->register(TransfersCommand::class, TransfersCommand::class)
                    ->setArguments([new Reference(Transfers::class)])
                    ->addTag('console.command');
            }
        }

        if (class_exists(AbstractType::class)) {
            $container->register(RedeemRewardType::class, RedeemRewardType::class)
                ->setArguments([new Reference(Catalog::class)])
                ->addTag('form.type');
            $container->register(TransferPointsType::class, TransferPointsType::class)->addTag('form.type');
            $container->register(OfferType::class, OfferType::class)->addTag('form.type');
        }
    }

    /**
     * The arguments of the privacy module the bundle class registers when `phil/privacy-bundle` is installed: the
     * erasure, the stores of what the application enabled, and the kind of a member's reference.
     *
     * @param Config $config
     *
     * @return list<Reference|string|null>
     */
    public static function privacyArguments(array $config): array
    {
        return [
            new Reference(ExchangeErasure::class),
            new Reference(ReservationStore::class),
            $config['privacy']['member_kind'],
            $config['transfers']['enabled'] ? new Reference(TransferLog::class) : null,
            $config['trades']['enabled'] ? new Reference(OfferStore::class) : null,
        ];
    }

    /**
     * Refuses at compile time what would fail at the first exchange: a reward, a duration or a timezone that does not
     * hold, a catalogue in the database without one, a delay for new accounts without
     * the joining dates.
     *
     * @param Config $config
     */
    private static function check(array $config): void
    {
        InMemoryCatalog::fromArray($config['rewards']);

        if ('doctrine' === $config['catalog'] && 'doctrine' !== $config['store']) {
            throw InvalidExchange::because('A catalogue kept in the database needs the "doctrine" store.');
        }

        if ([] !== $config['rewards'] && 'doctrine' === $config['catalog']) {
            throw InvalidExchange::because('The rewards are configured or kept in the database, not both: leave "rewards" empty with the "doctrine" catalogue.');
        }

        foreach (['reservation_lifetime' => $config['reservation_lifetime'], 'trades.offer_lifetime' => $config['trades']['offer_lifetime']] as $path => $lifetime) {
            self::duration($path, $lifetime);
        }

        foreach (['transfers', 'trades'] as $kind) {
            $delay = $config[$kind]['new_member_delay'];

            new Guards(null, $delay, $config['timezone']);

            if (null !== $delay && null === $config['member_since']) {
                throw InvalidExchange::because(\sprintf('"%s.new_member_delay" needs "member_since", the service that tells when a member joined.', $kind));
            }
        }
    }

    private static function duration(string $path, string $duration): void
    {
        try {
            $interval = new DateInterval($duration);
        } catch (Exception) {
            throw InvalidExchange::because(\sprintf('"%s" is not an ISO 8601 duration; "%s" needs one.', $duration, $path));
        }

        $now = new DateTimeImmutable();

        if ($now->add($interval) <= $now) {
            throw InvalidExchange::because(\sprintf('"%s" is no time; "%s" needs some.', $duration, $path));
        }
    }

    /**
     * @param array{allowance?: Allowed, new_member_delay: ?string, ...} $config
     */
    private static function guards(array $config, string $timezone, Definition|Reference $memberSince): Definition
    {
        $allowance = isset($config['allowance']) ? new Definition(Allowance::class, [$config['allowance']['limit'], Period::from($config['allowance']['period'])]) : null;

        return new Definition(Guards::class, [$allowance, $config['new_member_delay'], $timezone, $memberSince]);
    }
}
