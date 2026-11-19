<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Exchange\Trade\InMemoryInventory;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\Transfers;
use Tilttek\ExchangeBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\ExchangeBundle\Infrastructure\Console\TransfersCommand;
use Tilttek\ExchangeBundle\Tests\Unit\Fixture\Clock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

#[CoversClass(ExpireCommand::class)]
#[CoversClass(TransfersCommand::class)]
final class CommandsTest extends TestCase
{
    private Clock $clock;

    private Points $points;

    private Currencies $currencies;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->currencies = new Currencies(new Currency('points'));
        $this->points = new Points(new InMemoryLedger(), $this->clock, $this->currencies);
        $this->points->grant($this->member(1), 100, 'welcome:1');
        $this->points->grant($this->member(2), 100, 'welcome:2');
    }

    public function testTheReservationsAndTheOffersExpire(): void
    {
        $redemptions = new Redemptions(new InMemoryCatalog(new Reward('seat', 10)), new InMemoryReservationStore(), $this->points, $this->currencies, $this->clock);
        $inventory = new InMemoryInventory();
        $inventory->give(ResourceReference::fromString('card:1'), $this->member(1));

        $trades = new Trades(new InMemoryOfferStore(), $inventory, $this->points, $this->currencies, $this->clock);
        $redemptions->reserve($this->member(1), 'seat', 'r1');
        $redemptions->reserve($this->member(2), 'seat', 'r2');

        $trades->offer('t1', $this->member(1), $this->member(2), [ResourceReference::fromString('card:1')], []);
        $this->clock->set('2026-11-20 00:00:00 UTC');

        $tester = $this->tester(new ExpireCommand($redemptions, $trades), 'tilttek:exchange:expire');
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('2 reservations and 1 offers expired.', trim($tester->getDisplay()));

        $alone = $this->tester(new ExpireCommand($redemptions), 'tilttek:exchange:expire');
        self::assertSame(Command::SUCCESS, $alone->execute([]));
        self::assertSame('0 reservations and 0 offers expired.', trim($alone->getDisplay()));
    }

    public function testTheJournalOfTransfers(): void
    {
        $transfers = new Transfers(new InMemoryTransferLog(), $this->points, $this->currencies, $this->clock);
        $tester = $this->tester(new TransfersCommand($transfers), 'tilttek:exchange:transfers');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame('No transfer.', trim($tester->getDisplay()));

        $transfers->transfer($this->member(1), $this->member(2), 5, 'gift:1');
        $this->clock->set('2026-11-07 13:00:00 UTC');
        $transfers->transfer($this->member(2), $this->member(3), 7, 'gift:2');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Moment', $display);
        self::assertMatchesRegularExpression('/2026-11-07 13:00:00 +gift:2 +member:2 +member:3 +7 +points.*\n.*2026-11-07 12:00:00 +gift:1 +member:1 +member:2 +5 +points/s', $display);

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1']));
        self::assertStringNotContainsString('gift:1', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['member' => 'member:1']));
        self::assertStringContainsString('gift:1', $tester->getDisplay());
        self::assertStringNotContainsString('gift:2', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['member' => 'member:9']));
        self::assertSame('No transfer.', trim($tester->getDisplay()));

        self::assertSame(Command::FAILURE, $tester->execute(['member' => 'nobody']));
        self::assertStringContainsString('"nobody" is not a resource reference', $tester->getDisplay());
    }

    private function tester(callable $command, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find($name));
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }
}
