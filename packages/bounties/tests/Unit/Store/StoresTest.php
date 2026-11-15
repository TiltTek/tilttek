<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Exception\BountyConflict;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Store\InMemoryBountyStore;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(InMemoryBountyStore::class)]
final class StoresTest extends TestCase
{
    public function testTheStoreKeepsEachVersionOnce(): void
    {
        $store = new InMemoryBountyStore();
        $bounty = $this->bounty('b1', '42', '+7 days');

        self::assertNull($store->get('b1'));
        $store->add($bounty);
        self::assertSame($bounty->toArray(), $store->get('b1')?->toArray());

        try {
            $store->add($bounty);
            self::fail('The bounty is kept already.');
        } catch (BountyConflict $bountyConflict) {
            self::assertSame('A bounty "b1" is stored already.', $bountyConflict->getMessage());
        }

        $claimed = $bounty->claimWith('c1', World::bea(), 'answer:1', $this->at());
        $store->update($claimed, 1);
        self::assertSame(2, $store->get('b1')?->version);

        $this->expectException(BountyConflict::class);

        $store->update($claimed->claimWith('c2', World::cy(), 'answer:2', $this->at()), 1);
    }

    public function testAnUnknownBountyIsNotUpdated(): void
    {
        $this->expectException(BountyConflict::class);

        new InMemoryBountyStore()->update($this->bounty('b1', '42', '+7 days'), 1);
    }

    public function testASubjectHasOneOpenBounty(): void
    {
        $store = new InMemoryBountyStore();
        $store->add($this->bounty('b1', '42', '+7 days'));
        $store->add($this->bounty('7', '43', '+7 days'));

        self::assertSame('b1', $store->openOn(World::question('42')));
        self::assertSame('7', $store->openOn(World::question('43')));
        self::assertNull($store->openOn(World::question('44')));

        try {
            $store->add($this->bounty('b2', '42', '+7 days'));
            self::fail('The question has an open bounty.');
        } catch (BountyConflict $bountyConflict) {
            self::assertSame('An open bounty on question:42 is stored already.', $bountyConflict->getMessage());
        }

        $store->update($this->bounty('b1', '42', '+7 days')->cancel(World::ana(), $this->at()), 1);

        self::assertNull($store->openOn(World::question('42')));
        $store->add($this->bounty('b2', '42', '+7 days'));
        self::assertSame('b2', $store->openOn(World::question('42')));
    }

    public function testTheDueBountiesComeEarliestFirst(): void
    {
        $store = new InMemoryBountyStore();
        $store->add($this->bounty('late', '1', '+9 days'));
        $store->add($this->bounty('early', '2', '+7 days'));
        $store->add($this->bounty('later', '3', '+20 days'));
        $store->add($this->bounty('cancelled', '4', '+1 day')->cancel(World::ana(), $this->at()));
        $store->add($this->bounty('middle', '5', '+8 days'));
        $store->add($this->bounty('b', '6', '+7 days +500000 usec'));
        $store->add($this->bounty('42', '7', '+7 days +250000 usec'));

        $at = $this->at()->modify('+12 days');

        self::assertSame(['early', '42', 'b', 'middle', 'late'], $store->due($at, 10));
        self::assertSame(['early', '42'], $store->due($at, 2));
        self::assertSame([], $store->due($at, 0));
        self::assertSame([], $store->due($at, -1));
        self::assertSame(['early'], $store->due($this->at()->modify('+8 days'), 10));
        self::assertSame([], $store->due($this->at()->modify('+8 days -1 second'), 10));
    }

    private function bounty(string $id, string $question, string $ends): Bounty
    {
        return Bounty::offer(new BountyDraft($id, World::question($question), BountyReason::Detail, new Prize(50), $this->at()->modify($ends)), World::ana(), $this->at(), new BountyRules());
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-14T01:00:00Z');
    }
}
