<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Dice;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Dice\ChoiceStore;
use Tilttek\Collections\Dice\DieChoice;

/**
 * The contract every choice store passes.
 */
abstract class ChoiceStoreTestCase extends TestCase
{
    abstract protected function store(): ChoiceStore;

    public function testAChoiceReplacesTheOneForTheSameSides(): void
    {
        $store = $this->store();
        $one = ResourceReference::fromString('member:1');
        $store->save(new DieChoice($one, 20, 'c1'));
        $store->save(new DieChoice($one, 6, 'c2'));
        $store->save(new DieChoice($one, 6, 'c3'));
        $store->save(new DieChoice(ResourceReference::fromString('member:2'), 6, 'c4'));

        $choice = $store->find($one, 6) ?? self::fail('Not found.');
        self::assertSame(['c3', 6, 'member:1'], [$choice->copy, $choice->sides, $choice->member->toString()]);
        self::assertNull($store->find($one, 8));
        self::assertSame([6, 20], array_map(static fn (DieChoice $choice): int => $choice->sides, $store->ofMember($one)));

        self::assertFalse($store->remove(ResourceReference::fromString('member:2'), 20));
        self::assertTrue($store->remove($one, 20));
        self::assertFalse($store->remove($one, 20));
        self::assertNull($store->find($one, 20));
        self::assertTrue($store->remove(ResourceReference::fromString('member:2'), 6));
        self::assertSame(['c3'], array_map(static fn (DieChoice $choice): string => $choice->copy, $store->ofMember($one)));
        $store->save(new DieChoice(ResourceReference::fromString('member:2'), 6, 'c4'));
        self::assertSame(1, $store->eraseMember($one));
        self::assertSame([], $store->ofMember($one));
        self::assertSame('c4', $store->find(ResourceReference::fromString('member:2'), 6)?->copy);
    }
}
