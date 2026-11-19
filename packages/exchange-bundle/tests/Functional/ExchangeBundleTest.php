<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reservation\ReservationStore;
use Tilttek\Exchange\Trade\InMemoryInventory;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\Exchange\Transfer\Transfers;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Form\RedeemRewardType;
use Tilttek\ExchangeBundle\Tests\Functional\App\HeardEvents;
use Tilttek\ExchangeBundle\Tests\Functional\App\TestKernel;
use Tilttek\ExchangeBundle\TilttekExchangeBundle;
use Tilttek\Points\Points;

/**
 * The bundle in an application: the tables in the schema the ORM generates, a reward reserved and confirmed with its
 * event, the stock and the allowance refusing, a moment of another zone kept in UTC and a held reward expiring, a trade accepted, the expiry by its command, the redeem form, and a
 * member exported and erased through the privacy bundle.
 */
#[CoversClass(TilttekExchangeBundle::class)]
final class ExchangeBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([]);
        self::assertTrue($schema->hasTable(ExchangeTables::RESERVATIONS));
        self::assertTrue($schema->hasTable(ExchangeTables::HOLDS));
        self::assertFalse($schema->hasTable(ExchangeTables::REWARDS));

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testRewardsTransfersAndTrades(): void
    {
        $points = $this->service(Points::class, Points::class);
        $redemptions = $this->service(Redemptions::class, Redemptions::class);
        $heard = $this->service('app.heard', HeardEvents::class);
        $points->grant($this->member(1), 400, 'welcome:1');
        $points->grant($this->member(2), 400, 'welcome:2');

        $redemptions->reserve($this->member(1), 'seat', 'order:1');
        self::assertSame(['RewardReserved'], $heard->heard);
        self::assertSame(150, $points->balance($this->member(1)));
        self::assertSame(ReservationStatus::Confirmed, $redemptions->confirm('order:1')->status);

        try {
            $redemptions->reserve($this->member(2), 'seat', 'order:2');
            self::fail('The stock was expected to be out.');
        } catch (ExchangeRefused $exchangeRefused) {
            self::assertSame(Refusal::OutOfStock, $exchangeRefused->refusal);
        }

        $redemptions->reserve($this->member(2), 'discount-10', 'order:3');
        $this->service('clock', MockClock::class)->modify('+1 hour');
        $expire = new CommandTester(new Application(self::$kernel ?? throw new LogicException())->find('tilttek:exchange:expire'));
        self::assertSame(0, $expire->execute([]));
        self::assertSame('1 reservations and 0 offers expired.', trim($expire->getDisplay()));
        self::assertSame(400, $points->balance($this->member(2)));

        $transfers = $this->service(Transfers::class, Transfers::class);
        $transfers->transfer($this->member(2), $this->member(1), 50, 'gift:1');

        try {
            $transfers->transfer($this->member(2), $this->member(1), 1, 'gift:2');
            self::fail('The allowance was expected to be reached.');
        } catch (ExchangeRefused $exchangeRefused) {
            self::assertSame(Refusal::AllowanceReached, $exchangeRefused->refusal);
        }

        $inventory = $this->service('app.inventory', InMemoryInventory::class);
        $inventory->give(ResourceReference::fromString('card:1'), $this->member(1));
        $inventory->give(ResourceReference::fromString('card:2'), $this->member(2));

        $trades = $this->service(Trades::class, Trades::class);
        $trades->offer('trade:1', $this->member(1), $this->member(2), [ResourceReference::fromString('card:1')], [ResourceReference::fromString('card:2')], 20);
        $trades->accept('trade:1', $this->member(2));
        self::assertSame(['RewardReserved', 'ReservationConfirmed', 'RewardReserved', 'ReservationExpired', 'PointsTransferred', 'OfferMade', 'OfferAccepted'], $heard->heard);
        self::assertSame('member:2', $inventory->ownerOf(ResourceReference::fromString('card:1'))?->toString());
        self::assertSame(180, $points->balance($this->member(1)));

        $form = $this->service('app.form_factory', FormFactoryInterface::class)->create(RedeemRewardType::class);
        self::assertSame(['discount-10 (100)' => 'discount-10', 'seat (250)' => 'seat'], $form->get('reward')->getConfig()->getOption('choices'));
    }

    public function testAMemberIsExportedAndErased(): void
    {
        $points = $this->service(Points::class, Points::class);
        $points->grant($this->member(7), 300, 'welcome:7');
        $this->service(Redemptions::class, Redemptions::class)->reserve($this->member(7), 'discount-10', 'order:7');
        $this->service(Transfers::class, Transfers::class)->transfer($this->member(7), $this->member(8), 5, 'gift:7');

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('7'));
        $exchanges = $export['tilttek_exchange'] ?? null;
        self::assertIsArray($exchanges);
        self::assertSame(['reservations', 'transfers'], array_keys($exchanges));

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('7'));
        $connection = $this->service('doctrine.dbal.default_connection', Connection::class);
        self::assertSame([], $connection->fetchAllNumeric('SELECT reservation_key FROM '.ExchangeTables::RESERVATIONS));
        self::assertSame([['gift:7', 'erased:member', 'member:8']], $connection->fetchAllNumeric('SELECT transfer_key, from_member, to_member FROM '.ExchangeTables::TRANSFERS));
    }

    public function testAMomentOfAnotherZoneIsKeptInUtcAndAHeldRewardExpires(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/Toronto');

        try {
            $points = $this->service(Points::class, Points::class);
            $points->grant($this->member(3), 100, 'welcome:3');
            $toronto = new DateTimeZone('America/Toronto');
            $reservation = new Reservation(ExchangeKey::fromString('order:9'), $this->member(3), 'discount-10', 1, 100, 'points', new DateTimeImmutable('2026-11-07 07:00:00', $toronto), new DateTimeImmutable('2026-11-07 07:20:00', $toronto));

            self::assertTrue($this->service(ReservationStore::class, ReservationStore::class)->add($reservation, null, null));
            self::assertTrue($this->service(PointsHold::class, PointsHold::class)->hold($reservation));
            self::assertSame(0, $points->balance($this->member(3)));

            $connection = $this->service('doctrine.dbal.default_connection', Connection::class);
            self::assertSame(['2026-11-07 12:00:00', '2026-11-07 12:20:00'], $connection->fetchNumeric('SELECT reserved_at, expires_at FROM '.ExchangeTables::RESERVATIONS));

            $redemptions = $this->service(Redemptions::class, Redemptions::class);
            self::assertSame('2026-11-07T12:20:00+00:00', $redemptions->get('order:9')->expiresAt->format(\DATE_ATOM));
            self::assertSame(0, $redemptions->expireDue());

            $this->service('clock', MockClock::class)->modify('+20 minutes');
            self::assertSame(1, $redemptions->expireDue());
            self::assertSame(100, $points->balance($this->member(3)));
            self::assertSame('2026-11-07 12:20:00', $connection->fetchOne('SELECT settled_at FROM '.ExchangeTables::RESERVATIONS));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    private function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }
}
