<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
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
use Tilttek\ExchangeBundle\Infrastructure\DependencyInjection\ExchangeServices;
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
use Tilttek\ExchangeBundle\Infrastructure\Privacy\ExchangePrivacy;
use Tilttek\ExchangeBundle\TilttekExchangeBundle;
use Tilttek\Points\Currencies;
use Tilttek\Points\Points;
use Tilttek\Points\Rule\Period;

#[CoversClass(TilttekExchangeBundle::class)]
#[CoversClass(ExchangeServices::class)]
final class TilttekExchangeBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_exchange', $this->extension()->getAlias());
        self::assertSame([
            'service_container',
            DbalReservationStore::class,
            DbalTransferLog::class,
            DbalOfferStore::class,
            DbalTransaction::class,
            InMemoryCatalog::class,
            ExchangeSchemaListener::class,
            DispatchingAnnouncer::class,
            Redemptions::class,
            SpendingPointsHold::class,
            ExchangeErasure::class,
            ExpireCommand::class,
            RedeemRewardType::class,
            TransferPointsType::class,
            OfferType::class,
            ExchangePrivacy::class,
        ], array_keys($container->getDefinitions()));

        foreach ([ReservationStore::class => DbalReservationStore::class, TransferLog::class => DbalTransferLog::class, OfferStore::class => DbalOfferStore::class, Transaction::class => DbalTransaction::class, Catalog::class => InMemoryCatalog::class] as $port => $store) {
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
            self::assertFalse($container->getDefinition($store)->isPublic());
        }

        foreach ([DbalReservationStore::class, DbalTransferLog::class, DbalOfferStore::class, DbalTransaction::class] as $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
        }

        self::assertSame([InMemoryCatalog::class, 'fromArray'], $container->getDefinition(InMemoryCatalog::class)->getFactory());
        self::assertSame([[]], $container->getDefinition(InMemoryCatalog::class)->getArguments());
        self::assertSame([], $container->getDefinition(DbalReservationStore::class)->getTags());
        self::assertEquals([$connection, false], $container->getDefinition(ExchangeSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(ExchangeSchemaListener::class)->getTags());
        self::assertEquals([new Reference(EventDispatcherInterface::class)], $container->getDefinition(DispatchingAnnouncer::class)->getArguments());
        self::assertSame(DispatchingAnnouncer::class, (string) $container->getAlias(Announcer::class));
        self::assertFalse($container->getAlias(Announcer::class)->isPublic());
        self::assertEquals([
            new Reference(Catalog::class),
            new Reference(ReservationStore::class),
            new Reference(Points::class),
            new Reference(Currencies::class),
            new Reference('clock'),
            new Definition(NoRequirements::class),
            new Reference(Announcer::class),
            'PT30M',
        ], $container->getDefinition(Redemptions::class)->getArguments());
        self::assertTrue($container->getDefinition(Redemptions::class)->isPublic());
        self::assertEquals([new Reference(Points::class)], $container->getDefinition(SpendingPointsHold::class)->getArguments());
        self::assertSame(SpendingPointsHold::class, (string) $container->getAlias(PointsHold::class));
        self::assertTrue($container->getAlias(PointsHold::class)->isPublic());
        self::assertEquals([new Reference(ReservationStore::class), new Reference('clock'), null, null], $container->getDefinition(ExchangeErasure::class)->getArguments());
        self::assertTrue($container->getDefinition(ExchangeErasure::class)->isPublic());
        self::assertEquals([new Reference(Redemptions::class), null], $container->getDefinition(ExpireCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(ExpireCommand::class)->getTags());
        self::assertEquals([new Reference(Catalog::class)], $container->getDefinition(RedeemRewardType::class)->getArguments());

        foreach ([RedeemRewardType::class, TransferPointsType::class, OfferType::class] as $form) {
            self::assertSame(['form.type' => [[]]], $container->getDefinition($form)->getTags());
        }

        self::assertEquals([new Reference(ExchangeErasure::class), new Reference(ReservationStore::class), 'member', null, null], $container->getDefinition(ExchangePrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(ExchangePrivacy::class)->getTags());
    }

    public function testTransfersAndTradesWithTheirGuards(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'rewards' => ['seat' => ['price' => 250, 'stock' => 30, 'per_member' => 1, 'requires' => ['badge:helper'], 'available_until' => '2026-12-01']],
            'reservation_lifetime' => 'PT1H',
            'requirements' => 'app.requirements',
            'member_since' => 'app.member_since',
            'timezone' => 'America/Toronto',
            'transfers' => ['enabled' => true, 'allowance' => ['limit' => 500, 'period' => 'week'], 'new_member_delay' => 'P7D'],
            'trades' => ['enabled' => true, 'inventory' => 'app.inventory', 'offer_lifetime' => 'P3D', 'allowance' => ['limit' => 10, 'period' => 'day']],
            'privacy' => ['member_kind' => 'user'],
        ]);

        self::assertSame([
            'service_container',
            InMemoryReservationStore::class,
            InMemoryTransferLog::class,
            InMemoryOfferStore::class,
            ImmediateTransaction::class,
            InMemoryCatalog::class,
            DispatchingAnnouncer::class,
            Redemptions::class,
            SpendingPointsHold::class,
            Transfers::class,
            Trades::class,
            ExchangeErasure::class,
            ExpireCommand::class,
            TransfersCommand::class,
            RedeemRewardType::class,
            TransferPointsType::class,
            OfferType::class,
            ExchangePrivacy::class,
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryReservationStore::class)->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryReservationStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryTransferLog::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryOfferStore::class)->getTags());
        self::assertSame([], $container->getDefinition(ImmediateTransaction::class)->getTags());
        self::assertSame([['seat' => ['price' => 250, 'stock' => 30, 'per_member' => 1, 'requires' => ['badge:helper'], 'available_until' => '2026-12-01', 'currency' => null, 'available_from' => null]]], $container->getDefinition(InMemoryCatalog::class)->getArguments());

        $redemptions = $container->getDefinition(Redemptions::class);
        self::assertEquals(new Reference('app.requirements'), $redemptions->getArgument(5));
        self::assertSame('PT1H', $redemptions->getArgument(7));

        $memberSince = new Reference('app.member_since');
        self::assertEquals([
            new Reference(TransferLog::class),
            new Reference(Points::class),
            new Reference(Currencies::class),
            new Reference('clock'),
            new Definition(Guards::class, [new Definition(Allowance::class, [500, Period::Week]), 'P7D', 'America/Toronto', $memberSince]),
            new Reference(Announcer::class),
        ], $container->getDefinition(Transfers::class)->getArguments());
        self::assertTrue($container->getDefinition(Transfers::class)->isPublic());
        self::assertEquals([
            new Reference(OfferStore::class),
            new Reference('app.inventory'),
            new Reference(Points::class),
            new Reference(Currencies::class),
            new Reference('clock'),
            new Definition(Guards::class, [new Definition(Allowance::class, [10, Period::Day]), null, 'America/Toronto', $memberSince]),
            new Reference(Transaction::class),
            new Reference(Announcer::class),
            'P3D',
        ], $container->getDefinition(Trades::class)->getArguments());
        self::assertTrue($container->getDefinition(Trades::class)->isPublic());
        self::assertEquals([new Reference(ReservationStore::class), new Reference('clock'), new Reference(TransferLog::class), new Reference(OfferStore::class)], $container->getDefinition(ExchangeErasure::class)->getArguments());
        self::assertEquals([new Reference(Redemptions::class), new Reference(Trades::class)], $container->getDefinition(ExpireCommand::class)->getArguments());
        self::assertEquals([new Reference(Transfers::class)], $container->getDefinition(TransfersCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(TransfersCommand::class)->getTags());
        self::assertEquals([new Reference(ExchangeErasure::class), new Reference(ReservationStore::class), 'user', new Reference(TransferLog::class), new Reference(OfferStore::class)], $container->getDefinition(ExchangePrivacy::class)->getArguments());

        self::assertSame(['discount-10'], array_keys($this->arrayArgument($this->load(['rewards' => ['discount-10' => ['price' => 1]]])->getDefinition(InMemoryCatalog::class)->getArgument(0))));

        self::assertTrue($this->load(['store' => 'doctrine', 'catalog' => 'configuration', 'rewards' => ['gone' => ['price' => 1, 'stock' => 0]]])->hasDefinition(InMemoryCatalog::class));
        $guards = $this->load(['transfers' => ['enabled' => true, 'allowance' => ['limit' => 1, 'period' => 'ever']]])->getDefinition(Transfers::class)->getArgument(4);
        self::assertInstanceOf(Definition::class, $guards);
        self::assertEquals(new Definition(Allowance::class, [1, Period::Ever]), $guards->getArgument(0));

        $plain = $this->load(['transfers' => ['enabled' => true]])->getDefinition(Transfers::class)->getArgument(4);
        self::assertEquals(new Definition(Guards::class, [null, null, 'UTC', new Definition(NoMemberSince::class)]), $plain);
    }

    public function testTheCatalogueInTheDatabase(): void
    {
        $container = $this->load(['catalog' => 'doctrine', 'connection' => 'shop']);
        $connection = new Reference('doctrine.dbal.shop_connection');

        self::assertFalse($container->hasDefinition(InMemoryCatalog::class));
        self::assertEquals([$connection], $container->getDefinition(DbalCatalog::class)->getArguments());
        self::assertSame([DbalCatalog::class, true], [(string) $container->getAlias(Catalog::class), $container->getAlias(Catalog::class)->isPublic()]);
        self::assertEquals([$connection, true], $container->getDefinition(ExchangeSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'shop']]], $container->getDefinition(ExchangeSchemaListener::class)->getTags());
        self::assertEquals([$connection], $container->getDefinition(DbalReservationStore::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedWhenBuilt(): iterable
    {
        yield 'reward' => [['rewards' => ['Seat' => ['price' => 1]]], '"Seat" is not a reward code'];
        yield 'reward date' => [['rewards' => ['seat' => ['price' => 1, 'available_from' => 'soonish']]], '"soonish" is not a date.'];
        yield 'catalogue without the database' => [['store' => 'memory', 'catalog' => 'doctrine'], 'A catalogue kept in the database needs the "doctrine" store.'];
        yield 'both catalogues' => [['catalog' => 'doctrine', 'rewards' => ['seat' => ['price' => 1]]], 'The rewards are configured or kept in the database, not both: leave "rewards" empty with the "doctrine" catalogue.'];
        yield 'reservation lifetime' => [['reservation_lifetime' => 'half an hour'], '"half an hour" is not an ISO 8601 duration; "reservation_lifetime" needs one.'];
        yield 'no reservation lifetime' => [['reservation_lifetime' => 'PT0S'], '"PT0S" is no time; "reservation_lifetime" needs some.'];
        yield 'offer lifetime' => [['trades' => ['offer_lifetime' => 'a week']], '"a week" is not an ISO 8601 duration; "trades.offer_lifetime" needs one.'];
        yield 'transfer delay' => [['transfers' => ['new_member_delay' => 'a week']], '"a week" is not an ISO 8601 duration like "P7D".'];
        yield 'trade delay' => [['member_since' => 'app.member_since', 'trades' => ['new_member_delay' => 'soon']], '"soon" is not an ISO 8601 duration like "P7D".'];
        yield 'timezone' => [['timezone' => 'Mars/Olympus'], '"Mars/Olympus" is not a timezone.'];
        yield 'transfer delay without dates' => [['transfers' => ['new_member_delay' => 'P7D']], '"transfers.new_member_delay" needs "member_since", the service that tells when a member joined.'];
        yield 'trade delay without dates' => [['trades' => ['new_member_delay' => 'P7D']], '"trades.new_member_delay" needs "member_since"'];
        yield 'trades without inventory' => [['trades' => ['enabled' => true]], 'Trades need "trades.inventory", the service that keeps the members\' items.'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedWhenBuilt')]
    public function testAWrongSettingFailsWhenTheContainerIsBuilt(array $config, string $message): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis']];
        yield 'catalogue' => [['catalog' => 'api']];
        yield 'connection' => [['connection' => '']];
        yield 'price' => [['rewards' => ['seat' => ['price' => 0]]]];
        yield 'no price' => [['rewards' => ['seat' => []]]];
        yield 'stock' => [['rewards' => ['seat' => ['price' => 1, 'stock' => -1]]]];
        yield 'limit' => [['rewards' => ['seat' => ['price' => 1, 'per_member' => 0]]]];
        yield 'lifetime' => [['reservation_lifetime' => '']];
        yield 'timezone' => [['timezone' => '']];
        yield 'allowance' => [['transfers' => ['allowance' => ['limit' => 0, 'period' => 'day']]]];
        yield 'allowance period' => [['trades' => ['allowance' => ['limit' => 1, 'period' => 'fortnight']]]];
        yield 'offer lifetime' => [['trades' => ['offer_lifetime' => '']]];
        yield 'member kind' => [['privacy' => ['member_kind' => '']]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayArgument(mixed $argument): array
    {
        self::assertIsArray($argument);

        return $argument;
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
        $extension = new TilttekExchangeBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
