<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderMarkingStore;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Workflow\CommitOnPlace;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(CommitOnPlace::class)]
final class CommitOnPlaceTest extends TestCase
{
    public function testTheDrawsAreCommittedWhenTheWorkflowPlacesTheOrder(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CommitOnPlace::EVENT, new CommitOnPlace(new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at())));

        $workflow = OrderWorkflow::create(new OrderMarkingStore(), $dispatcher);
        $order = Order::open('1042', Currency::of('CAD'));
        $order->add(Shop::sellable('shirt'), 2, Money::of(2_500, Currency::of('CAD')));

        $workflow->apply($order, OrderWorkflow::QUOTE, ['at' => new DateTimeImmutable('2026-11-08 09:00:00')]);
        self::assertNull($draws->get(LootDraw::idOf('1042', Shop::line($order, 'shirt')->reference->value(), 'loot', 1)));

        $workflow->apply($order, OrderWorkflow::PLACE, ['at' => new DateTimeImmutable('2026-11-08 10:00:00')]);

        self::assertSame('workflow.phil_commerce_order.completed.place', CommitOnPlace::EVENT);
        self::assertCount(2, $draws->ofOrder('1042'));
    }

    public function testASubjectThatIsNotAnOrderIsLeftAlone(): void
    {
        $draws = new InMemoryLootDrawRepository();
        $listener = new CommitOnPlace(new LootCommitments(Shop::drawers(), Shop::lineOptions(), $draws, new SecureSeeds(), Clock::at()));

        $listener(new CompletedEvent(new stdClass(), new Marking()));

        self::assertSame([], $draws->ofOrder('1042'));
    }
}
