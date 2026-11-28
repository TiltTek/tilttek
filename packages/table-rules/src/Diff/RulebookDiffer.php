<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Diff;

use Tilttek\TableRules\Internal\Lcs;
use Tilttek\TableRules\Rule;
use Tilttek\TableRules\RulebookVersion;

/**
 * Compares two versions of a table's rules, rule by rule through their identifiers, and line by line within a text
 * that changed. Its work is bounded by the bounds of a version: 200 rules, 100 lines a text.
 */
final readonly class RulebookDiffer
{
    /**
     * What changed from the first version to the second. The versions may be compared in either order.
     */
    public function diff(RulebookVersion $from, RulebookVersion $to): RulebookDiff
    {
        $before = [];
        foreach ($from->rules as $rule) {
            $before[$rule->id] = $rule;
        }

        $after = [];
        foreach ($to->rules as $rule) {
            $after[$rule->id] = $rule;
        }

        $added = array_values(array_filter($to->rules, static fn (Rule $rule): bool => !isset($before[$rule->id])));
        $removed = array_values(array_filter($from->rules, static fn (Rule $rule): bool => !isset($after[$rule->id])));
        $changed = [];
        $keptAfter = [];
        foreach ($to->rules as $rule) {
            $earlier = $before[$rule->id] ?? null;
            if (null === $earlier) {
                continue;
            }

            $keptAfter[] = $rule->id;
            if (!$earlier->equals($rule)) {
                $changed[] = new RuleChange($earlier, $rule, $earlier->text === $rule->text ? [] : $this->lines($earlier->lines(), $rule->lines()));
            }
        }

        // A removed rule matches nothing of $keptAfter, so it takes no part in the subsequence.
        $inOrder = array_flip(array_column(Lcs::pairs(array_map(static fn (Rule $rule): string => $rule->id, $from->rules), $keptAfter), 1));

        $moved = [];
        foreach ($keptAfter as $position => $id) {
            if (!isset($inOrder[$position])) {
                $moved[] = $id;
            }
        }

        return new RulebookDiff(
            $from->number,
            $to->number,
            $added,
            $removed,
            $changed,
            $moved,
            array_values(array_diff($to->variants, $from->variants)),
            array_values(array_diff($from->variants, $to->variants)),
            $from->system,
            $to->system,
        );
    }

    /**
     * @param list<string> $before
     * @param list<string> $after
     *
     * @return list<LineChange>
     */
    private function lines(array $before, array $after): array
    {
        $lines = [];
        $i = 0;
        $j = 0;
        foreach ([...Lcs::pairs($before, $after), [\count($before), \count($after)]] as [$nextBefore, $nextAfter]) {
            for (; $i < $nextBefore; ++$i) {
                $lines[] = new LineChange(LineKind::Removed, $before[$i]);
            }

            for (; $j < $nextAfter; ++$j) {
                $lines[] = new LineChange(LineKind::Added, $after[$j]);
            }

            if ($i < \count($before)) {
                $lines[] = new LineChange(LineKind::Kept, $before[$i]);
                ++$i;
                ++$j;
            }
        }

        return $lines;
    }
}
