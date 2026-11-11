<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\Rule\Cap;
use Tilttek\Points\Rule\EarningRule;
use Tilttek\Points\Rule\Rules;

/**
 * The projection that turns the members' activity into points: each recorded activity a rule rewards earns its
 * points, issued from the rule's account, up to the rule's cap in the activity's period; each cancellation reverses
 * what the activity earned. The movements are keyed by the rule and the activity's idempotency key, so an entry handed
 * twice earns once.
 *
 * When a rule changes, `Projector::rebuild()` forgets the derived movements of the period — earned, reversed, expired
 * — and replays its activity under the new rules: the journal, never rewritten, is what the points are recomputed
 * from. The movements the application decided — spendings, transfers, grants — are kept as they are.
 */
final readonly class PointsProjection implements Projection
{
    public const string NAME = 'points';

    public function __construct(
        private Ledger $ledger,
        private Currencies $currencies,
        private Rules $rules,
        private ClockInterface $clock,
        private string $name = self::NAME,
    ) {
        foreach ($rules->all() as $rule) {
            $currencies->get($rule->currency);
        }
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function filter(): Filter
    {
        return Filter::all()->verbs(...$this->rules->patterns());
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        if ($entry->isCancellation()) {
            $this->reverse($entry);

            return;
        }

        foreach ($this->rules->matching($entry->activity) as $rule) {
            $this->earn($rule, $entry);
        }
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->ledger->forgetDerived($since);
    }

    private function earn(EarningRule $rule, Entry $entry): void
    {
        $activity = $entry->activity;
        $key = MovementKey::derived('earn', $rule->name.':'.$activity->key->value);

        if ($this->ledger->find($key) instanceof LedgerEntry) {
            return;
        }

        $currency = $this->currencies->get($rule->currency)->code;
        $member = Account::member($activity->member);
        $issuance = Account::issuance($rule->name);
        $amount = $rule->pointsFor($activity);

        if ($rule->cap instanceof Cap) {
            [$since, $until] = $rule->cap->period->bounds($activity->occurredAt, $this->rules->timezone);
            $amount = min($amount, $rule->cap->amount - $this->ledger->net($member, $issuance, $currency, $since, $until));
        }

        if ($amount < 1) {
            return;
        }

        $this->ledger->append(new Movement($key, MovementKind::Earned, $currency, $issuance, $member, $amount, $activity->occurredAt, $activity->key->value), $this->clock->now());
    }

    private function reverse(Entry $entry): void
    {
        foreach ($this->ledger->causedBy($entry->activity->key->value) as $earned) {
            $movement = $earned->movement;

            if (MovementKind::Earned !== $movement->kind) {
                continue;
            }

            $this->ledger->append(new Movement(
                MovementKey::derived('reverse', $movement->key->value),
                MovementKind::Reversed,
                $movement->currency,
                $movement->credit,
                $movement->debit,
                $movement->amount,
                $movement->occurredAt,
                $movement->cause,
                $movement->key,
            ), $this->clock->now());
        }
    }
}
