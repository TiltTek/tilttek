<?php

declare(strict_types=1);

namespace Tilttek\Dice\Rolling;

use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Critical;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\Look;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Result\DieOrigin;
use Tilttek\Dice\Result\Mark;
use Tilttek\Dice\Result\Notice;
use Tilttek\Dice\Result\RolledDie;

/**
 * Rolls a set of numeric dice with the modifiers that roll — rerolls, unique dice, explosions, dice all the same —,
 * then applies to any set of dice, rolled or not, the modifiers that judge them, in the order `Modifiers` gives.
 *
 * @internal
 */
final class SetRoller
{
    /**
     * @return list<RolledDie>
     */
    public static function roll(Dice $dice, NumericDie $die, Context $context, ?int $limit): array
    {
        $rolled = [];

        for ($index = 0; $index < $dice->count; ++$index) {
            self::rollDie($dice->modifiers, $die, $context, $rolled, DieOrigin::Initial, $limit);
        }

        $batch = 0;

        while ($dice->modifiers->explodeAllSame && self::allSame(\array_slice($rolled, $batch))) {
            $rolled = [...\array_slice($rolled, 0, $batch), ...array_map(static fn (RolledDie $same): RolledDie => $same->counts() ? $same->exploding() : $same, \array_slice($rolled, $batch))];
            $batch = \count($rolled);

            for ($index = 0; $index < $dice->count; ++$index) {
                $context->explode();
                self::rollDie($dice->modifiers, $die, $context, $rolled, DieOrigin::Explosion, $limit);
            }
        }

        return $rolled;
    }

    /**
     * The modifiers that judge dice already rolled, the value of the set, its groups, and what it could not do exactly.
     *
     * @param list<RolledDie> $dice
     *
     * @return array{list<RolledDie>, int, list<list<int>>, list<Notice>}
     */
    public static function judge(array $dice, Modifiers $modifiers, int $lowest, int $highest): array
    {
        if (null !== $modifiers->minimum || null !== $modifiers->maximum || [] !== $modifiers->transforms) {
            $dice = array_map(static fn (RolledDie $die): RolledDie => $die->rerolled ? $die : $die->valued(self::adjust($die, $modifiers)), $dice);
        }

        if ($modifiers->filter instanceof Condition) {
            $dice = self::filter($dice, $modifiers->filter);
        }

        if ($modifiers->selection instanceof Selection) {
            $dice = self::select($dice, $modifiers->selection);
        }

        [$dice, $value, $groups, $notices] = self::count($dice, $modifiers);

        if ($modifiers->criticalSuccess instanceof Critical || $modifiers->criticalFailure instanceof Critical) {
            $dice = array_map(static function (RolledDie $die) use ($modifiers, $lowest, $highest): RolledDie {
                $mark = self::mark($die, $modifiers->criticalSuccess, $modifiers->criticalFailure, $lowest, $highest);

                return $mark instanceof Mark ? $die->marked($mark) : $die;
            }, $dice);
        }

        if ([] !== $modifiers->looks) {
            $dice = self::look($dice, $modifiers->looks);
        }

        if ($modifiers->sort instanceof Sort) {
            $descending = Sort::Descending === $modifiers->sort;
            // The sort is stable: equal dice keep the order they fell in.
            usort($dice, static fn (RolledDie $a, RolledDie $b): int => $descending ? $b->value <=> $a->value : $a->value <=> $b->value);
        }

        return [$dice, $value, $groups, $notices];
    }

    /**
     * Gives each look token to its dice, in the order they are listed.
     *
     * @param list<RolledDie> $dice
     * @param list<Look>      $looks
     *
     * @return list<RolledDie>
     */
    private static function look(array $dice, array $looks): array
    {
        $tokens = [];

        foreach ($looks as $look) {
            for ($given = 0; $given < ($look->count ?? \count($dice)); ++$given) {
                $tokens[] = $look->token;
            }
        }

        return array_map(static fn (RolledDie $die, int $index): RolledDie => isset($tokens[$index]) ? $die->looked($tokens[$index]) : $die, $dice, array_keys($dice));
    }

    /**
     * Rolls one die, its rerolls and its explosions.
     *
     * @param list<RolledDie> $rolled
     */
    private static function rollDie(Modifiers $modifiers, NumericDie $die, Context $context, array &$rolled, DieOrigin $origin, ?int $limit): void
    {
        $value = $die->roll($context->randomizer);
        $reroll = $modifiers->reroll;

        while ($reroll instanceof Reroll && $reroll->on->matches($value)) {
            [$value, $origin] = self::replace($value, $origin, $die, $context, $rolled);
            $reroll = Repetition::Once === $reroll->repetition ? null : $reroll;
        }

        $unique = $modifiers->unique;

        while ($unique instanceof Repetition && \in_array($value, array_map(static fn (RolledDie $shown): int => $shown->value, array_filter($rolled, static fn (RolledDie $shown): bool => $shown->counts())), true)) {
            [$value, $origin] = self::replace($value, $origin, $die, $context, $rolled);
            $unique = Repetition::Once === $unique ? null : $unique;
        }

        $explosion = $modifiers->explosion;
        $rolls = [$value];
        $explosions = 0;

        while ($explosion instanceof Explosion && (null === $limit || $explosions < $limit) && $explosion->triggers($value, $die->highest())) {
            $context->explode();
            ++$explosions;
            $value = $die->roll($context->randomizer);
            $added = $explosion->penetrating ? $value - 1 : $value;

            if ($explosion->compound) {
                $rolls[] = $added;
            } else {
                $rolled[] = new RolledDie(array_sum($rolls), $origin, exploded: true, rolls: $rolls);
                $rolls = [$added];
                $origin = DieOrigin::Explosion;
            }
        }

        $rolled[] = new RolledDie(Arithmetic::integer(array_sum($rolls)), $origin, exploded: 1 < \count($rolls), rolls: $rolls);
    }

    /**
     * Keeps a die that a reroll replaces, and rolls the one that replaces it.
     *
     * @param list<RolledDie> $rolled
     *
     * @return array{int, DieOrigin}
     */
    private static function replace(int $value, DieOrigin $origin, NumericDie $die, Context $context, array &$rolled): array
    {
        $context->reroll();
        $rolled[] = new RolledDie($value, $origin, kept: false, rerolled: true);

        return [$die->roll($context->randomizer), DieOrigin::Reroll];
    }

    /**
     * Whether the dice that count — as many as the set rolls, two at least — all show the same value.
     *
     * @param array<int, RolledDie> $batch
     */
    private static function allSame(array $batch): bool
    {
        $values = array_map(static fn (RolledDie $die): int => $die->value, array_filter($batch, static fn (RolledDie $die): bool => $die->counts()));

        return 1 === \count(array_unique($values));
    }

    /**
     * The value of a die once its floor, its ceiling and the first transformation that matches apply.
     */
    private static function adjust(RolledDie $die, Modifiers $modifiers): int
    {
        $value = max($die->value, $modifiers->minimum ?? $die->value);
        $value = min($value, $modifiers->maximum ?? $value);

        foreach ($modifiers->transforms as $transform) {
            if ($transform->when->validator->matches(Scope::Die === $transform->when->scope ? $die->natural() : $value)) {
                return $transform->value;
            }
        }

        return $value;
    }

    /**
     * @param list<RolledDie> $dice
     *
     * @return list<RolledDie>
     */
    private static function filter(array $dice, Condition $filter): array
    {
        $eachDie = $filter->scope->eachDie();
        $together = !$eachDie && self::matchesTogether($dice, $filter);

        return array_map(static fn (RolledDie $die): RolledDie => !$die->counts() || ($eachDie ? self::matches($die, $filter) : $together) ? $die : $die->dropped(), $dice);
    }

    /**
     * @param list<RolledDie> $dice
     *
     * @return list<RolledDie>
     */
    private static function select(array $dice, Selection $selection): array
    {
        $candidates = array_keys(array_filter($dice, static fn (RolledDie $die): bool => $die->counts()));
        $highest = $selection->mode->highest();
        // The sort is stable: of two equal dice, the first rolled is named first.
        usort($candidates, static fn (int $a, int $b): int => $highest ? $dice[$b]->value <=> $dice[$a]->value : $dice[$a]->value <=> $dice[$b]->value);
        $named = \array_slice($candidates, 0, $selection->count);
        $dropped = $selection->mode->keeps() ? array_diff($candidates, $named) : $named;

        return array_map(static fn (RolledDie $die, int $index): RolledDie => \in_array($index, $dropped, true) ? $die->dropped() : $die, $dice, array_keys($dice));
    }

    /**
     * The successes and failures, the occurrences, the groups or the sum of the dice that count.
     *
     * @param list<RolledDie> $dice
     *
     * @return array{list<RolledDie>, int, list<list<int>>, list<Notice>}
     */
    private static function count(array $dice, Modifiers $modifiers): array
    {
        $values = [];

        foreach ($dice as $die) {
            if ($die->counts()) {
                $values[] = $die->value;
            }
        }

        $success = $modifiers->success;

        if ($success instanceof Condition && !$success->scope->eachDie()) {
            return [$dice, self::matchesTogether($dice, $success) ? 1 : 0, [], []];
        }

        if ($success instanceof Condition) {
            $failure = $modifiers->failure;
            $dice = array_map(static function (RolledDie $die) use ($success, $failure): RolledDie {
                if (!$die->counts()) {
                    return $die;
                }

                $judged = $die->judged(self::matches($die, $success));

                return true !== $judged->success && true === $failure?->matches($die->value) ? $judged->failed() : $judged;
            }, $dice);

            // Only the dice that count are judged: the others are neither successes nor failures.
            return [$dice, \count(array_filter($dice, static fn (RolledDie $die): bool => true === $die->success)) - \count(array_filter($dice, static fn (RolledDie $die): bool => $die->failure)), [], []];
        }

        if ($modifiers->occurrences instanceof Occurrences) {
            $groups = Grouping::occurrences($values, $modifiers->occurrences);

            return [$dice, \count($groups), $groups, []];
        }

        if (null !== $modifiers->groups) {
            [$groups, $optimal] = Grouping::reaching($values, $modifiers->groups);

            return [$dice, \count($groups), $groups, $optimal ? [] : [Notice::GreedyGroups]];
        }

        return [$dice, Arithmetic::integer(array_sum($values)), [], []];
    }

    private static function mark(RolledDie $die, ?Critical $success, ?Critical $failure, int $lowest, int $highest): ?Mark
    {
        return match (true) {
            $die->rerolled => null,
            true === $success?->marks($die->natural(), $highest) => Mark::CriticalSuccess,
            true === $failure?->marks($die->natural(), $lowest) => Mark::CriticalFailure,
            default => null,
        };
    }

    private static function matches(RolledDie $die, Condition $condition): bool
    {
        return $condition->validator->matches(Scope::Die === $condition->scope ? $die->natural() : $die->value);
    }

    /**
     * Whether the dice that count match together: all of them, at least one, or their total.
     *
     * @param list<RolledDie> $dice
     */
    private static function matchesTogether(array $dice, Condition $condition): bool
    {
        $values = array_map(static fn (RolledDie $die): int => $die->value, array_filter($dice, static fn (RolledDie $die): bool => $die->counts()));
        $matching = array_filter($values, $condition->validator->matches(...));

        return match ($condition->scope) {
            Scope::All => \count($matching) === \count($values),
            Scope::Any => [] !== $matching,
            default => $condition->validator->matches(Arithmetic::integer(array_sum($values))),
        };
    }
}
