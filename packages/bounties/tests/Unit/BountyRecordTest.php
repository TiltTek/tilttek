<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\BountyRecord;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Check;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Instant;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Reader;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(BountyRecord::class)]
#[CoversClass(Reader::class)]
#[CoversClass(Bounty::class)]
#[CoversClass(Instant::class)]
#[CoversClass(Check::class)]
final class BountyRecordTest extends TestCase
{
    public function testAListHoldsItsLimitAndNoMore(): void
    {
        $read = new Reader(['items' => ['a', 'b']], 'a record', ['items']);

        self::assertSame(['a', 'b'], $read->list('items', 2));

        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains('lists 1 items at most');

        $read->list('items', 1);
    }

    /**
     * @return iterable<string, array{Closure(): Bounty}>
     */
    public static function bounties(): iterable
    {
        yield 'open' => [self::bounty(...)];
        yield 'claimed' => [self::claimed(...)];
        yield 'partly awarded' => [static fn (): Bounty => self::claimed()->awardTo('a1', 'c1', 30, World::ana(), self::at())];
        yield 'awarded' => [static fn (): Bounty => self::claimed()->awardTo('a1', 'c1', 30, World::ana(), self::at())->awardTo('a2', 'c2', 70, World::ana(), self::at())];
        yield 'closed' => [static fn (): Bounty => self::claimed()->awardTo('a1', 'c1', 30, World::ana(), self::at())->close(World::ana(), self::at())];
        yield 'expired' => [static fn (): Bounty => self::claimed()->expire(self::at()->modify('+8 days'))];
        yield 'cancelled' => [static fn (): Bounty => self::bounty()->cancel(World::ana(), self::at())];
        yield 'in gems' => [static fn (): Bounty => Bounty::offer(new BountyDraft('42', World::question(), BountyReason::Creativity, new Prize(15, 'gems'), self::at()->modify('+2 days')), World::ana(), self::at(), new BountyRules())];
    }

    /**
     * @param Closure(): Bounty $bounty
     */
    #[DataProvider('bounties')]
    public function testARecordReadsBackAsItWasWritten(Closure $bounty): void
    {
        $record = $bounty()->toArray();
        $json = json_decode(json_encode($record, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);

        self::assertSame($record, Bounty::fromArray($json)->toArray());
    }

    /**
     * @return iterable<string, array{Closure(array<array-key, mixed>): array<array-key, mixed>, string}>
     */
    public static function brokenRecords(): iterable
    {
        yield 'a field missing' => [static fn (array $r): array => array_diff_key($r, ['version' => true]), 'holds the fields'];
        yield 'a field more' => [static fn (array $r): array => [...$r, 'currency' => 'CAD'], 'holds the fields'];
        yield 'a field renamed' => [static fn (array $r): array => [...array_diff_key($r, ['version' => true]), 'versions' => 2], 'holds the fields'];
        yield 'a bad identifier' => [static fn (array $r): array => [...$r, 'id' => 'b 1'], 'A bounty identifier'];
        yield 'a bad subject' => [static fn (array $r): array => [...$r, 'subject' => 'question'], 'not a resource reference'];
        yield 'an unknown reason' => [static fn (array $r): array => [...$r, 'reason' => 'money'], 'not one of its values'];
        yield 'a prize of no point' => [static fn (array $r): array => [...$r, 'prize' => 0], 'A prize is'];
        yield 'a unit of money' => [static fn (array $r): array => [...$r, 'unit' => 'USD'], 'not a unit of points'];
        yield 'claims not a list' => [static fn (array $r): array => [...$r, 'claims' => 'c1'], 'is a list'];
        yield 'a claim not a record' => [static fn (array $r): array => [...$r, 'claims' => ['c1']], 'The claims of a bounty are records'];
        yield 'an award not a record' => [static fn (array $r): array => [...$r, 'awards' => [3]], 'The awards of a bounty are records'];
        yield 'a claim field missing' => [static fn (array $r): array => self::item($r, 'claims', 0, static fn (array $c): array => array_diff_key($c, ['evidence' => true])), 'a claim holds the fields'];
        yield 'an award field missing' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => array_diff_key($a, ['amount' => true])), 'an award holds the fields'];
        yield 'an award of no point' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => [...$a, 'amount' => 0]), 'at least one point'];
        yield 'an end at the offer' => [static fn (array $r): array => [...$r, 'ends_at' => $r['opened_at']], 'ends after it is offered'];
        yield 'a grace ending before the end' => [static fn (array $r): array => [...$r, 'grace_ends_at' => '2026-11-20T01:00:00.000000Z'], 'ends after it is offered'];
        yield 'version zero' => [static fn (array $r): array => [...$r, 'version' => 0], 'version 1 or later'];
        yield 'a claim twice' => [static fn (array $r): array => self::item($r, 'claims', 1, static fn (array $c): array => [...$c, 'id' => 'c1']), 'is listed once'];
        yield 'a claim by the sponsor' => [static fn (array $r): array => self::item($r, 'claims', 1, static fn (array $c): array => [...$c, 'claimant' => 'member:ana']), 'other than the sponsor'];
        yield 'a claim before the offer' => [static fn (array $r): array => self::item($r, 'claims', 0, static fn (array $c): array => [...$c, 'claimed_at' => '2026-11-13T00:00:00.000000Z']), 'while the bounty was open to claims'];
        yield 'a claim at the end' => [static fn (array $r): array => self::item($r, 'claims', 0, static fn (array $c): array => [...$c, 'claimed_at' => $r['ends_at']]), 'while the bounty was open to claims'];
        yield 'an award twice' => [static fn (array $r): array => [...$r, 'awards' => [self::first($r, 'awards'), self::first($r, 'awards')]], 'is listed once'];
        yield 'an award to no claim' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => [...$a, 'claim' => 'c9']), 'to a claim of the bounty'];
        yield 'an award before the offer' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => [...$a, 'awarded_at' => '2026-11-13T00:00:00.000000Z']), 'while the bounty could be awarded'];
        yield 'an award at the end of the grace' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => [...$a, 'awarded_at' => $r['grace_ends_at']]), 'while the bounty could be awarded'];
        yield 'awards over the amount' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => [...$a, 'amount' => 101]), 'no more than its amount'];
        yield 'the whole amount awarded while open' => [static fn (array $r): array => self::item($r, 'awards', 0, static fn (array $a): array => [...$a, 'amount' => 100]), 'some points left while it is open'];
        yield 'a settlement of an open bounty' => [static fn (array $r): array => [...$r, 'settled_at' => $r['opened_at']], 'settlement time'];
        yield 'an awarded bounty without settlement' => [static fn (array $r): array => [...$r, 'state' => 'awarded'], 'settlement time'];
        yield 'an expired bounty with awards' => [static fn (array $r): array => [...$r, 'state' => 'expired', 'settled_at' => $r['grace_ends_at']], 'no awards when it expired'];
        yield 'a cancelled bounty with claims' => [static fn (array $r): array => [...$r, 'state' => 'cancelled', 'settled_at' => $r['opened_at'], 'awards' => []], 'no claims when it was cancelled'];
        yield 'a settlement before the offer' => [static fn (array $r): array => [...$r, 'state' => 'awarded', 'settled_at' => '2026-11-13T00:00:00.000000Z'], 'settled after it is offered'];
        yield 'too many claims' => [static fn (array $r): array => [...$r, 'claims' => array_fill(0, Bounty::MAX_CLAIMS + 1, self::first($r, 'claims'))], 'lists 200 items at most'];
        yield 'too many awards' => [static fn (array $r): array => [...$r, 'awards' => array_fill(0, Bounty::MAX_AWARDS + 1, self::first($r, 'awards'))], 'lists 20 items at most'];
    }

    /**
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $break
     */
    #[DataProvider('brokenRecords')]
    public function testABrokenRecordIsRefused(Closure $break, string $message): void
    {
        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains($message);

        Bounty::fromArray($break(self::claimed()->awardTo('a1', 'c1', 30, World::ana(), self::at())->toArray()));
    }

    public function testAnAwardedBountyWithoutAwardsIsRefused(): void
    {
        $record = [...self::claimed()->toArray(), 'state' => 'awarded', 'settled_at' => '2026-11-14T01:00:00.000000Z'];

        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains('some when it was awarded');

        Bounty::fromArray($record);
    }

    public function testFiveClaimsOfAMemberAreReadAndSixAreNot(): void
    {
        $record = self::claimed()->toArray();
        $claims = array_map(static fn (int $n): array => [...$record['claims'][0], 'id' => 'b'.$n], range(1, 5));

        self::assertCount(5, Bounty::fromArray([...$record, 'claims' => $claims])->claims);

        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains('one of at most 5 of theirs');

        Bounty::fromArray([...$record, 'claims' => [...$claims, [...$record['claims'][0], 'id' => 'b6']]]);
    }

    public function testTheEdgesOfTheTimesAreRead(): void
    {
        $record = self::claimed()->awardTo('a1', 'c1', 30, World::ana(), self::at())->toArray();
        $edge = self::item($record, 'claims', 0, static fn (array $c): array => [...$c, 'claimed_at' => $record['opened_at']]);
        $edge = self::item($edge, 'claims', 1, static fn (array $c): array => [...$c, 'claimed_at' => '2026-11-21T00:59:59.999999Z']);
        $edge = self::item($edge, 'awards', 0, static fn (array $a): array => [...$a, 'awarded_at' => '2026-11-22T00:59:59.999999Z']);
        $edge = [...$edge, 'state' => 'awarded', 'settled_at' => $record['opened_at']];

        self::assertSame($edge, Bounty::fromArray($edge)->toArray());
        self::assertSame([...$record, 'grace_ends_at' => $record['ends_at']], Bounty::fromArray([...$record, 'grace_ends_at' => $record['ends_at']])->toArray());
    }

    /**
     * @param array<array-key, mixed>                                   $record
     * @param Closure(array<array-key, mixed>): array<array-key, mixed> $change
     *
     * @return array<array-key, mixed>
     */
    private static function item(array $record, string $field, int $index, Closure $change): array
    {
        $items = $record[$field];
        self::assertIsArray($items);
        $item = $items[$index];
        self::assertIsArray($item);
        $items[$index] = $change($item);

        return [...$record, $field => $items];
    }

    /**
     * @param array<array-key, mixed> $record
     *
     * @return array<array-key, mixed>
     */
    private static function first(array $record, string $field): array
    {
        $items = $record[$field];
        self::assertIsArray($items);
        self::assertIsArray($items[0]);

        return $items[0];
    }

    private static function bounty(): Bounty
    {
        return Bounty::offer(new BountyDraft('b1', World::question(), BountyReason::Detail, new Prize(100), self::at()->modify('+7 days')), World::ana(), self::at(), new BountyRules());
    }

    private static function claimed(): Bounty
    {
        return self::bounty()->claimWith('c1', World::bea(), 'answer:1', self::at())->claimWith('c2', World::cy(), 'answer:2', self::at()->modify('+1 hour'));
    }

    private static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-14T01:00:00Z');
    }
}
