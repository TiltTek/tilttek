<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Reward;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\UnknownReward;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\NoRequirements;
use Tilttek\Exchange\Reward\Reward;

#[CoversClass(InMemoryCatalog::class)]
#[CoversClass(NoRequirements::class)]
#[CoversClass(UnknownReward::class)]
final class InMemoryCatalogTest extends TestCase
{
    public function testTheCatalogHoldsTheRewardsByCode(): void
    {
        $catalog = InMemoryCatalog::fromArray(['seat' => ['price' => 250, 'stock' => 3], 'discount-10' => ['price' => 100], '10-off' => ['price' => 10]]);

        self::assertSame(['10-off', 'discount-10', 'seat'], array_map(static fn (Reward $reward): string => $reward->code, $catalog->all()));
        self::assertSame(3, $catalog->get('seat')->stock);

        $catalog->save(new Reward('seat', 300));
        self::assertSame(300, $catalog->get('seat')->price);
        self::assertNull($catalog->get('seat')->stock);

        $catalog->remove('seat');
        $catalog->remove('never');
        self::assertSame(['10-off', 'discount-10'], array_map(static fn (Reward $reward): string => $reward->code, $catalog->all()));
        self::assertSame([], new InMemoryCatalog()->all());
        self::assertSame(['a', 'b'], array_map(static fn (Reward $reward): string => $reward->code, new InMemoryCatalog(new Reward('b', 1), new Reward('a', 1))->all()));
    }

    public function testAnUnknownRewardIsRefused(): void
    {
        $this->expectException(UnknownReward::class);
        $this->expectExceptionMessageIsOrContains('The catalogue holds no reward "seat".');

        new InMemoryCatalog()->get('seat');
    }

    public function testNoRequirementIsMetWithoutTheApplication(): void
    {
        self::assertFalse(new NoRequirements()->meets(ResourceReference::of('member', '1'), 'badge:helper'));
    }
}
