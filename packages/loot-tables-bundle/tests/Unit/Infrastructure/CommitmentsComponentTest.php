<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTablesBundle\Application\LootCommitments;
use Tilttek\LootTablesBundle\Application\LootOptionDrawer;
use Tilttek\LootTablesBundle\Application\LootResults;
use Tilttek\LootTablesBundle\Application\SecureSeeds;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Infrastructure\Memory\InMemoryLootDrawRepository;
use Tilttek\LootTablesBundle\Infrastructure\Twig\Components\CommitmentsComponent;
use Tilttek\LootTablesBundle\Infrastructure\Twig\KitComponents;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Orders;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(CommitmentsComponent::class)]
#[CoversClass(KitComponents::class)]
final class CommitmentsComponentTest extends TestCase
{
    private InMemoryLootDrawRepository $draws;

    private LootCommitments $commitments;

    #[Override]
    protected function setUp(): void
    {
        $this->draws = new InMemoryLootDrawRepository();
        $this->commitments = new LootCommitments(Shop::drawers(), Shop::lineOptions(), $this->draws, new SecureSeeds(), Clock::at());
    }

    public function testTheCommitmentsOfAnOrderAreShownWithTheNamesOfTheirLines(): void
    {
        $order = Shop::order(['shirt' => 2, 'epitaph' => 1, 'mug' => 1]);
        $this->commitments->commit($order);
        $component = $this->component($order);

        $draws = $component->draws();

        self::assertCount(3, $draws);
        self::assertSame($draws, $component->draws());
        self::assertSame(['1042'], array_values(array_unique(array_map(static fn (LootDraw $draw): string => $draw->orderNumber(), $draws))));
        self::assertSame(['line' => 'Shirt', 'option' => 'Loot'], $component->name($draws[0]));
        self::assertSame(['line' => 'Epitaph', 'option' => 'Epitaph'], $component->name($draws[2]));
        self::assertNull($component->result($draws[0]));
        self::assertNull($component->shopCode($draws[0]));
        self::assertFalse($component->verified($draws[0]));
    }

    public function testShowingTheDrawsCommitsNothing(): void
    {
        $component = $this->component(Shop::order(['shirt' => 1]));

        self::assertSame([], $component->draws());
        self::assertSame([], $this->draws->ofOrder('1042'));
    }

    public function testTheOrderIsFoundOnceWhateverTheNumberOfTimesTheDrawsAreAsked(): void
    {
        $order = Shop::order(['shirt' => 1]);
        $orders = new class($order) implements OrderRepository {
            public int $finds = 0;

            public function __construct(private readonly Order $order)
            {
            }

            #[Override]
            public function find(string $number): ?Order
            {
                ++$this->finds;

                return $number === $this->order->number ? $this->order : null;
            }

            #[Override]
            public function add(Order $order): void
            {
            }
        };
        $component = new CommitmentsComponent($orders, new LootResults(Shop::drawers(), $this->draws, new LootDrawer()));
        $component->order = '1042';

        $component->draws();
        $component->draws();

        self::assertSame(1, $orders->finds);

        $nameless = new CommitmentsComponent($orders, new LootResults(Shop::drawers(), $this->draws, new LootDrawer()));
        self::assertSame([], $nameless->draws());
        self::assertSame(1, $orders->finds);
    }

    public function testTheNamesAreKnownWithoutAskingForTheDrawsFirst(): void
    {
        $order = Shop::order(['shirt' => 1]);
        [$draw] = $this->commitments->commit($order);

        self::assertSame(['line' => 'Shirt', 'option' => 'Loot'], $this->component($order)->name($draw));
    }

    public function testAnOrderThatDoesNotExistHasNoDraws(): void
    {
        $order = Shop::order(['shirt' => 1]);
        [$draw] = $this->commitments->commit($order);
        $component = $this->component($order);
        $component->order = '9999';

        self::assertSame([], $component->draws());
        self::assertSame(['line' => $draw->line(), 'option' => 'loot'], $component->name($draw));

        $component = $this->component($order);
        $component->order = '';

        self::assertSame([], $component->draws());
        self::assertSame([], $this->component(Order::open('2003', Currency::of('CAD')))->draws());
    }

    public function testARevealedDrawShowsItsResultItsShopCodeAndItsVerification(): void
    {
        $order = Shop::order(['shirt' => 1]);
        $this->commitments->commit($order);
        new LootOptionDrawer(Shop::drawers(), $this->commitments, $this->draws, new LootDrawer(), Clock::at())->draw('loot-box:starter', $order, Shop::line($order, 'shirt'), 'loot', 1);
        $component = $this->component($order);

        [$revealed] = $component->draws();
        $result = $component->result($revealed);

        self::assertNotNull($result);
        self::assertSame(ShopCode::write($result), $component->shopCode($revealed));
        self::assertTrue($component->verified($revealed));
    }

    public function testTheRecipesOfTheKitAreNamedAsTheApplicationInstalledThem(): void
    {
        $order = Shop::order(['shirt' => 1]);

        self::assertSame('Card:Title', $this->component($order)->kit('Card:Title'));
        self::assertSame('ui:Card', $this->component($order, new KitComponents('ui'))->kit('Card'));
        self::assertSame('ui:loot:Badge', $this->component($order, new KitComponents('/ui/loot/'))->kit('Badge'));
        self::assertSame('Badge', $this->component($order, new KitComponents('/'))->kit('Badge'));
        self::assertSame(['card', 'badge'], KitComponents::RECIPES);
    }

    private function component(Order $order, ?KitComponents $kit = null): CommitmentsComponent
    {
        $results = new LootResults(Shop::drawers(), $this->draws, new LootDrawer());
        $component = $kit instanceof KitComponents ? new CommitmentsComponent(new Orders($order), $results, $kit) : new CommitmentsComponent(new Orders($order), $results);
        $component->order = $order->number;

        return $component;
    }
}
