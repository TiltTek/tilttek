<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\CommerceBundle\Application\LineOptions;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Option\TextSource;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\Money\Currency;
use Phil\Money\Money;
use Tilttek\LootTables\LootCatalog;
use Tilttek\LootTables\Reading\CatalogReader;
use Tilttek\LootTablesBundle\Application\Drawers;

/**
 * A shop for the tests: a catalogue of a box of two d6 tables and a box of a d21 table, and the products that draw
 * them — a loot shirt, an epitaph shirt whose text is drawn among three, a shirt whose list has a single text, a shirt
 * of the d21 box, one drawn elsewhere, a mug of a typed caption, and a shirt of both a caption and the box.
 */
final readonly class Shop implements SellableOptions
{
    public const array CATALOGUE = [
        'tables' => [
            'rings' => ['name' => 'Rings', 'entries' => [
                ['key' => 'copper', 'name' => 'Copper ring', 'image' => '/loot/copper.png', 'slot' => 'finger'],
                ['key' => 'silver', 'name' => 'Silver ring', 'image' => '/loot/silver.png', 'slot' => 'finger'],
                ['key' => 'gold', 'name' => 'Gold ring', 'image' => '/loot/gold.png', 'slot' => 'finger'],
                ['key' => 'bone', 'name' => 'Bone ring', 'slot' => 'finger'],
                ['key' => 'glass', 'name' => 'Glass ring', 'slot' => 'finger'],
                ['key' => 'none', 'name' => 'No ring'],
            ]],
            'hats' => ['name' => 'Hats', 'entries' => [
                ['key' => 'helmet', 'name' => 'Helmet', 'image' => '/loot/helmet.png', 'slot' => 'head'],
                ['key' => 'hood', 'name' => 'Hood', 'image' => '/loot/hood.png', 'slot' => 'head'],
                ['key' => 'crown', 'name' => 'Crown', 'image' => '/loot/crown.png', 'slot' => 'head'],
                ['key' => 'cap', 'name' => 'Cap', 'image' => '/loot/cap.png', 'slot' => 'head'],
                ['key' => 'pot', 'name' => 'Pot', 'image' => '/loot/pot.png', 'slot' => 'head'],
                ['key' => 'bare', 'name' => 'Bare head'],
            ]],
        ],
        'boxes' => [
            'starter' => ['name' => 'Starter box', 'tables' => ['rings', 'hats']],
        ],
    ];

    public const array EPITAPHS = ['Here lies a hero.', 'Gone fishing.', 'Back soon, maybe.'];

    public static function catalog(): LootCatalog
    {
        $catalogue = self::CATALOGUE;
        $catalogue['tables']['d21'] = ['name' => 'D21', 'entries' => array_map(static fn (int $face): array => ['key' => 'f'.$face, 'name' => 'Face '.$face], range(1, 21))];
        $catalogue['boxes']['giant'] = ['name' => 'Giant box', 'tables' => ['d21']];

        return new CatalogReader()->read($catalogue);
    }

    public static function drawers(): Drawers
    {
        return new Drawers(self::catalog());
    }

    public static function lineOptions(): LineOptions
    {
        return new LineOptions([new self()]);
    }

    /**
     * A placed order of the sellables given, by identifier, with their quantities.
     *
     * @param array<string, int> $quantities
     */
    public static function order(array $quantities, string $number = '1042'): Order
    {
        $order = Order::open($number, Currency::of('CAD'));

        foreach ($quantities as $id => $quantity) {
            $order->add(self::sellable($id), $quantity, self::price(), self::configuration($id));
        }

        $order->place(new DateTimeImmutable('2026-11-08 10:00:00'));

        return $order;
    }

    public static function line(Order $order, string $id): OrderLine
    {
        foreach ($order->lines() as $line) {
            if ($line->reference->id === $id) {
                return $line;
            }
        }

        throw new LogicException(\sprintf('The order has no line of %s.', $id));
    }

    public static function sellable(string $id): Sellable
    {
        return Sellable::of(SellableReference::of('product', $id), ucfirst($id), self::price(), 'goods', new self()->optionsOf(SellableReference::of('product', $id), Currency::of('CAD')));
    }

    #[Override]
    public function supports(string $type): bool
    {
        return 'product' === $type;
    }

    #[Override]
    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        $drawn = static fn (array $entries): OptionDefinition => OptionDefinition::text('epitaph', 'Epitaph', TextConstraints::of(200, sources: [TextSource::Drawn], entries: $entries), drawer: Drawers::TEXT);

        return match ($reference->id) {
            'shirt' => OptionSet::of(OptionDefinition::draw('loot', 'Loot', Drawers::box('starter'))),
            'epitaph' => OptionSet::of($drawn(self::EPITAPHS)),
            'lonely' => OptionSet::of($drawn(['Alone here.'])),
            'giant' => OptionSet::of(OptionDefinition::draw('loot', 'Loot', Drawers::box('giant'))),
            'elsewhere' => OptionSet::of(OptionDefinition::draw('loot', 'Loot', 'mystery')),
            'mug' => OptionSet::of(OptionDefinition::text('caption', 'Caption', TextConstraints::of(40))),
            'combo' => OptionSet::of(OptionDefinition::text('caption', 'Caption', TextConstraints::of(40)), OptionDefinition::draw('loot', 'Loot', Drawers::box('starter'))),
            default => OptionSet::none(),
        };
    }

    private static function configuration(string $id): ?LineConfiguration
    {
        return match ($id) {
            'epitaph', 'lonely' => LineConfiguration::of(['epitaph' => OptionValue::toDraw()]),
            'mug', 'combo' => LineConfiguration::of(['caption' => OptionValue::chosen('Hello')]),
            default => null,
        };
    }

    private static function price(): Money
    {
        return Money::of(2_500, Currency::of('CAD'));
    }
}
