<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Activity\VerbPattern;
use Tilttek\Points\Account;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\UnknownCurrency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\Points\Points;
use Tilttek\Points\PointsProjection;
use Tilttek\Points\Rule\Cap;
use Tilttek\Points\Rule\EarningRule;
use Tilttek\Points\Rule\Period;
use Tilttek\Points\Rule\Rules;
use Tilttek\Points\Tests\Unit\Fixture\Clock;

#[CoversClass(PointsProjection::class)]
final class PointsProjectionTest extends TestCase
{
    private InMemoryActivityLog $log;

    private InMemoryLedger $ledger;

    private Clock $clock;

    private Currencies $currencies;

    #[Override]
    protected function setUp(): void
    {
        $this->log = new InMemoryActivityLog();
        $this->ledger = new InMemoryLedger();
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->currencies = new Currencies(new Currency('points'), new Currency('stars'));
    }

    public function testTheProjection(): void
    {
        $projection = $this->projection(new EarningRule('games', ['game.*'], 1), new EarningRule('comments', ['news.commented'], 1));

        self::assertSame('points', $projection->name());
        self::assertSame('loyalty', new PointsProjection($this->ledger, $this->currencies, new Rules(new DateTimeZone('UTC')), $this->clock, 'loyalty')->name());
        self::assertSame(['news.commented', 'game.*'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $projection->filter()->verbs->patterns));
    }

    public function testTheRulesCurrenciesAreKnown(): void
    {
        $this->expectExceptionObject(UnknownCurrency::named('crowns'));
        $this->projection(new EarningRule('games', ['game.*'], 1), new EarningRule('wins', ['game.won'], 1, 'crowns'));
    }

    public function testAnActivityEarnsItsPointsOnce(): void
    {
        $journal = $this->journal(new EarningRule('games', ['game.played'], 10), new EarningRule('systems', ['game.*'], 3, 'stars', context: ['system' => 'dnd5e']));
        $this->clock->set('2026-11-07 13:00:00 UTC');

        $journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', $this->at('2026-11-07 10:00:00'), 'game:1', 2, ['system' => 'dnd5e']));
        $journal->record(Activity::of('comment:1', 'member:1', 'news.commented', $this->at('2026-11-07 10:00:00')));

        self::assertSame([
            ['#earn:games:game:1:member:1', 'earned', 'points', 'issuance:games', 'member:member:1', 20, '2026-11-07 10:00:00', '2026-11-07 13:00:00', 'game:1:member:1', null],
            ['#earn:systems:game:1:member:1', 'earned', 'stars', 'issuance:systems', 'member:member:1', 6, '2026-11-07 10:00:00', '2026-11-07 13:00:00', 'game:1:member:1', null],
        ], $this->movements());

        $projection = $this->projection(new EarningRule('games', ['game.played'], 99));
        $projection->apply($this->log->read(0, 1, Filter::all())[0]);
        self::assertCount(2, $this->movements());
    }

    public function testACapLimitsWhatAMemberEarnsInAPeriod(): void
    {
        $journal = $this->journal(new EarningRule('games', ['game.played'], 10, cap: new Cap(25, Period::Week)), new EarningRule('wins', ['game.played'], 1));

        foreach (['2026-11-02 08:00:00', '2026-11-03 08:00:00', '2026-11-04 08:00:00', '2026-11-09 04:59:59', '2026-11-09 05:00:00'] as $index => $moment) {
            $this->clock->set($moment.' UTC');
            $journal->record(Activity::of('g'.$index, 'member:1', 'game.played', $this->at($moment)));
        }

        $journal->record(Activity::of('other', 'member:2', 'game.played', $this->at('2026-11-04 08:00:00')));
        self::assertSame(['#earn:games:g0' => 10, '#earn:games:g1' => 10, '#earn:games:g2' => 5, '#earn:games:g4' => 10, '#earn:games:other' => 10], $this->amounts('games'));
        self::assertCount(6, $this->amounts('wins'));

        $journal->cancel('g1');
        $journal->record(Activity::of('g5', 'member:1', 'game.played', $this->at('2026-11-05 08:00:00')));
        self::assertSame(10, $this->amounts('games')['#earn:games:g5'] ?? null);
    }

    public function testAPeriodIsCountedInTheRulesTimezone(): void
    {
        $journal = $this->journal(new EarningRule('daily', ['login'], 5, cap: new Cap(5, Period::Day)));
        $journal->record(Activity::of('a', 'member:1', 'login', $this->at('2026-11-07 03:00:00')));
        $journal->record(Activity::of('b', 'member:1', 'login', $this->at('2026-11-07 06:00:00')));

        self::assertSame(['#earn:daily:a' => 5, '#earn:daily:b' => 5], $this->amounts('daily'));
    }

    public function testACancellationReversesWhatTheActivityEarned(): void
    {
        $journal = $this->journal(new EarningRule('games', ['game.played'], 10), new EarningRule('wins', ['game.*'], 2));
        $journal->record(Activity::of('a', 'member:1', 'game.played', $this->at('2026-11-02 08:00:00')));
        $journal->record(Activity::of('b', 'member:1', 'game.played', $this->at('2026-11-02 09:00:00')));

        $this->clock->set('2026-11-08 08:00:00 UTC');
        $journal->cancel('a');

        self::assertSame([
            ['#reverse:#earn:games:a', 'reversed', 'points', 'member:member:1', 'issuance:games', 10, '2026-11-02 08:00:00', '2026-11-08 08:00:00', 'a', '#earn:games:a'],
            ['#reverse:#earn:wins:a', 'reversed', 'points', 'member:member:1', 'issuance:wins', 2, '2026-11-02 08:00:00', '2026-11-08 08:00:00', 'a', '#earn:wins:a'],
        ], \array_slice($this->movements(), 4));
        self::assertSame(12, $this->points()->balance(ResourceReference::of('member', '1')));

        $this->projection(new EarningRule('games', ['game.played'], 10))->apply($this->log->read(2, 1, Filter::all())[0]);
        self::assertCount(6, $this->movements());
    }

    public function testACancellationOfAnActivityThatEarnedNothing(): void
    {
        $journal = $this->journal(new EarningRule('games', ['game.played'], 10));
        $journal->record(Activity::of('a', 'member:1', 'news.commented', self::at('2026-11-02 08:00:00')));
        $journal->cancel('a');

        $this->projection(new EarningRule('comments', ['news.commented'], 10))->apply($this->log->read(1, 1, Filter::all())[0]);
        self::assertSame([], $this->movements());
    }

    public function testOnlyEarnedPointsAreReversed(): void
    {
        $this->ledger->append(new Movement(MovementKey::fromString('grant'), MovementKind::Granted, 'points', Account::issuance('manual'), Account::member(ResourceReference::of('member', '1')), 1, self::at('2026-11-02 08:00:00'), 'a'), self::at('2026-11-02 08:00:00'));
        $journal = $this->journal(new EarningRule('games', ['game.played'], 10));
        $journal->record(Activity::of('a', 'member:1', 'game.played', self::at('2026-11-02 08:00:00')));
        $journal->cancel('a');

        self::assertSame(['grant', '#earn:games:a', '#reverse:#earn:games:a'], array_map(static fn (array $movement): int|string|null => $movement[0], $this->movements()));
    }

    public function testARebuildRecomputesTheDerivedMovementsAndKeepsTheDecidedOnes(): void
    {
        $checkpoints = new InMemoryCheckpoints();
        $journal = new Journal($this->log, $this->clock);
        $journal->record(Activity::of('a', 'member:1', 'game.played', $this->at('2026-11-01 08:00:00')));
        $journal->record(Activity::of('b', 'member:1', 'game.played', $this->at('2026-11-05 08:00:00')));
        $journal->record(Activity::of('c', 'member:1', 'game.played', $this->at('2026-11-06 08:00:00')));
        $journal->cancel('c');

        $before = $this->projection(new EarningRule('games', ['game.played'], 10));
        new Projector($this->log, $checkpoints)->catchUp($before);
        $this->points()->spend(ResourceReference::of('member', '1'), 15, 'order:1', 'order:1');
        self::assertSame(5, $this->points()->balance(ResourceReference::of('member', '1')));

        $after = $this->projection(new EarningRule('games', ['game.played'], 20));
        new Projector($this->log, $checkpoints)->rebuild($after, $this->at('2026-11-05 00:00:00'));
        self::assertSame(['#earn:games:a' => 10, '#earn:games:b' => 20, '#earn:games:c' => 20], $this->amounts('games'));
        self::assertNotNull($this->ledger->find(MovementKey::fromString('#reverse:#earn:games:c')));
        self::assertSame(15, $this->points()->balance(ResourceReference::of('member', '1')));

        new Projector($this->log, $checkpoints)->rebuild($after);
        self::assertSame(['#earn:games:a' => 20, '#earn:games:b' => 20, '#earn:games:c' => 20], $this->amounts('games'));
        self::assertSame(25, $this->points()->balance(ResourceReference::of('member', '1')));
    }

    private function projection(EarningRule ...$rules): PointsProjection
    {
        return new PointsProjection($this->ledger, $this->currencies, new Rules(new DateTimeZone('America/Toronto'), ...$rules), $this->clock);
    }

    private function journal(EarningRule ...$rules): Journal
    {
        $projector = new Projector($this->log, new InMemoryCheckpoints());

        return new Journal($this->log, $this->clock, observers: [new LiveProjections($projector, new Projections([$this->projection(...$rules)]))]);
    }

    private function points(): Points
    {
        return new Points($this->ledger, $this->clock, $this->currencies);
    }

    /**
     * @return list<list<int|string|null>>
     */
    private function movements(): array
    {
        $member = Account::member(ResourceReference::of('member', '1'));

        return array_map(static fn (LedgerEntry $entry): array => [
            $entry->movement->key->value,
            $entry->movement->kind->value,
            $entry->movement->currency,
            $entry->movement->debit->toString(),
            $entry->movement->credit->toString(),
            $entry->movement->amount,
            $entry->movement->occurredAt->format('Y-m-d H:i:s'),
            $entry->at->format('Y-m-d H:i:s'),
            $entry->movement->cause,
            $entry->movement->reverses?->value,
        ], $this->ledger->statement($member, null, 0, 100));
    }

    /**
     * The points each movement of a rule earned, by key, over every member.
     *
     * @return array<string, int>
     */
    private function amounts(string $rule): array
    {
        $amounts = [];

        foreach ($this->ledger->statement(Account::issuance($rule), null, 0, 100) as $entry) {
            if ('earned' === $entry->movement->kind->value) {
                $amounts[$entry->movement->key->value] = $entry->movement->amount;
            }
        }

        ksort($amounts);

        return $amounts;
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
