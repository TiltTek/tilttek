<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Effect;

use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;
use Tilttek\Conditions\Operation;
use Tilttek\Conditions\Rounding;

/**
 * Counts the modifiers in force on the statistics of a character: a pure computation, without state.
 *
 * For each statistic, the operations run in the order of `Operation`: the multiplications one after the other in the
 * order given, each rounded by the system's rule; the additions summed, those of one type keeping only their highest
 * bonus and lowest penalty; the highest floor; the lowest cap; the last override given. A modifier of a statistic not in
 * the base changes nothing: the character sheet says which statistics exist.
 */
final readonly class EffectCalculator
{
    /** The most statistics of a base. */
    public const int MAX_STATS = 1_000;

    /** The largest base value of a statistic, either way. */
    public const int MAX_BASE = 1_000_000_000;

    /** The largest value a statistic reaches, either way: beyond, it stays at the bound. */
    public const int MAX_RESULT = 1_000_000_000_000_000;

    public function __construct(private Rounding $rounding = Rounding::Down)
    {
    }

    /**
     * @param array<string, int>    $base      the statistics before the conditions, by identifier
     * @param list<AppliedModifier> $modifiers the modifiers in force, in the order the conditions were applied
     *
     * @return array<string, StatValue> by identifier, in the order of the base
     *
     * @throws InvalidCondition when there are too many statistics, an identifier is not one, or a base value is out of
     *                          its bounds
     */
    public function compute(array $base, array $modifiers): array
    {
        if (\count($base) > self::MAX_STATS) {
            throw InvalidCondition::because(\sprintf('A base has %d statistics at most.', self::MAX_STATS));
        }

        $byStat = [];
        foreach ($modifiers as $applied) {
            $byStat[$applied->modifier->stat][] = $applied;
        }

        $values = [];
        foreach ($base as $stat => $value) {
            Identifier::check($stat, 'a statistic');
            if (abs($value) > self::MAX_BASE) {
                throw InvalidCondition::because(\sprintf('A base value is %d at most either way.', self::MAX_BASE));
            }

            $values[$stat] = $this->one($stat, $value, $byStat[$stat] ?? []);
        }

        return $values;
    }

    /**
     * @param list<AppliedModifier> $modifiers
     */
    private function one(string $stat, int $base, array $modifiers): StatValue
    {
        $grouped = [];
        foreach ($modifiers as $applied) {
            $grouped[$applied->modifier->operation->value][] = $applied;
        }

        $value = $base;
        $counted = [];
        foreach ($grouped[Operation::Multiply->value] ?? [] as $applied) {
            $value = $this->bound($this->rounding->divide($value * $applied->amount, 100));
            $counted[] = $applied;
        }

        $added = $this->additions($grouped[Operation::Add->value] ?? []);
        foreach ($added as $applied) {
            $value = $this->bound($value + $applied->amount);
            $counted[] = $applied;
        }

        $floor = $this->extreme($grouped[Operation::Upgrade->value] ?? [], true);
        if ($floor instanceof AppliedModifier && $floor->amount > $value) {
            $value = $floor->amount;
            $counted[] = $floor;
        }

        $cap = $this->extreme($grouped[Operation::Downgrade->value] ?? [], false);
        if ($cap instanceof AppliedModifier && $cap->amount < $value) {
            $value = $cap->amount;
            $counted[] = $cap;
        }

        $overrides = $grouped[Operation::Override->value] ?? [];
        if ([] !== $overrides) {
            $override = $overrides[\count($overrides) - 1];
            $value = $override->amount;
            $counted[] = $override;
        }

        return new StatValue($stat, $base, $value, $counted);
    }

    /**
     * The additions that count: every one without a type, and of each type its highest bonus and its lowest penalty,
     * the first given on a tie; in the order given.
     *
     * @param list<AppliedModifier> $additions
     *
     * @return array<int, AppliedModifier>
     */
    private function additions(array $additions): array
    {
        $best = [];
        foreach ($additions as $index => $applied) {
            $type = $applied->modifier->type;
            if (null === $type || 0 === $applied->amount) {
                continue;
            }

            $key = ($applied->amount > 0 ? '+' : '-').$type;
            $kept = $best[$key] ?? null;
            if (null === $kept || abs($applied->amount) > abs($additions[$kept]->amount)) {
                $best[$key] = $index;
            }
        }

        $winners = array_flip($best);

        return array_filter(
            $additions,
            static fn (AppliedModifier $applied, int $index): bool => null === $applied->modifier->type || isset($winners[$index]),
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * The highest or the lowest amount, the first given on a tie, or null when there are none.
     *
     * @param list<AppliedModifier> $modifiers
     */
    private function extreme(array $modifiers, bool $highest): ?AppliedModifier
    {
        $kept = null;
        foreach ($modifiers as $applied) {
            if (!$kept instanceof AppliedModifier || ($highest ? $applied->amount > $kept->amount : $applied->amount < $kept->amount)) {
                $kept = $applied;
            }
        }

        return $kept;
    }

    private function bound(int $value): int
    {
        return max(-self::MAX_RESULT, min(self::MAX_RESULT, $value));
    }
}
