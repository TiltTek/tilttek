<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\TableRules\Diff\LineChange;
use Tilttek\TableRules\Diff\LineKind;
use Tilttek\TableRules\Diff\RulebookDiff;
use Tilttek\TableRules\Diff\RulebookDiffer;
use Tilttek\TableRules\Diff\RuleChange;
use Tilttek\TableRules\Internal\Lcs;
use Tilttek\TableRules\Rule;
use Tilttek\TableRules\RulebookVersion;

#[CoversClass(RulebookDiffer::class)]
#[CoversClass(RulebookDiff::class)]
#[CoversClass(RuleChange::class)]
#[CoversClass(LineChange::class)]
#[CoversClass(Lcs::class)]
final class DiffTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>, list<array{int, int}>}>
     */
    public static function sequences(): iterable
    {
        yield 'both empty' => [[], [], []];
        yield 'first empty' => [[], ['a'], []];
        yield 'second empty' => [['a'], [], []];
        yield 'same' => [['a', 'b'], ['a', 'b'], [[0, 0], [1, 1]]];
        yield 'nothing common' => [['a', 'b'], ['c', 'd'], []];
        yield 'one inserted in the middle' => [['a', 'c'], ['a', 'b', 'c'], [[0, 0], [1, 2]]];
        yield 'one removed in the middle' => [['a', 'b', 'c'], ['a', 'c'], [[0, 0], [2, 1]]];
        yield 'common end only' => [['x', 'a', 'b'], ['y', 'a', 'b'], [[1, 1], [2, 2]]];
        yield 'common start only' => [['a', 'b', 'x'], ['a', 'b', 'y', 'z'], [[0, 0], [1, 1]]];
        yield 'middle matched by the table' => [['x', 'a', 'b', 'y'], ['z', 'a', 'q', 'b', 'w'], [[1, 1], [2, 3]]];
        yield 'moved to the end' => [['a', 'b', 'c'], ['b', 'c', 'a'], [[1, 0], [2, 1]]];
        yield 'moved to the start' => [['a', 'b', 'c'], ['c', 'a', 'b'], [[0, 1], [1, 2]]];
        yield 'swapped matches the later one' => [['a', 'b'], ['b', 'a'], [[1, 0]]];
        yield 'longer middle' => [['p', 'a', 'x', 'b', 'c', 's'], ['q', 'a', 'b', 'y', 'c', 't'], [[1, 1], [3, 2], [4, 4]]];
        yield 'the common start is matched first' => [['a', 'b'], ['a', 'a', 'b'], [[0, 0], [1, 2]]];
        yield 'repeated' => [['a', 'a', 'b'], ['a', 'b', 'b'], [[0, 0], [2, 2]]];
    }

    /**
     * @param list<string>          $a
     * @param list<string>          $b
     * @param list<array{int, int}> $pairs
     */
    #[DataProvider('sequences')]
    public function testTheLongestCommonSubsequenceIsMatched(array $a, array $b, array $pairs): void
    {
        self::assertSame($pairs, Lcs::pairs($a, $b));
    }

    public function testTheMatchesOfRandomSequencesAreTheLongest(): void
    {
        $random = new Randomizer(new Mt19937(42));
        for ($case = 0; $case < 1500; ++$case) {
            $a = $this->randomSequence($random);
            $b = $this->randomSequence($random);
            $pairs = Lcs::pairs($a, $b);

            self::assertCount($this->longest($a, $b), $pairs, json_encode([$a, $b], \JSON_THROW_ON_ERROR));
            $previous = [-1, -1];
            foreach ($pairs as [$i, $j]) {
                self::assertGreaterThan($previous[0], $i);
                self::assertGreaterThan($previous[1], $j);
                self::assertSame($a[$i], $b[$j]);
                $previous = [$i, $j];
            }
        }
    }

    /**
     * @return list<string>
     */
    private function randomSequence(Randomizer $random): array
    {
        $sequence = [];
        for ($i = $random->getInt(0, 9); $i > 0; --$i) {
            $sequence[] = ['a', 'b', 'c', 'd'][$random->getInt(0, 3)];
        }

        return $sequence;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private function longest(array $a, array $b): int
    {
        $length = [];
        foreach (array_keys($a) as $i) {
            foreach (array_keys($b) as $j) {
                $length[$i][$j] = $a[$i] === $b[$j]
                    ? ($length[$i - 1][$j - 1] ?? 0) + 1
                    : max($length[$i - 1][$j] ?? 0, $length[$i][$j - 1] ?? 0);
            }
        }

        return [] === $a || [] === $b ? 0 : $length[\count($a) - 1][\count($b) - 1];
    }

    public function testTwoVersionsOfTheSameRulesHaveNoDifference(): void
    {
        $diff = new RulebookDiffer()->diff($this->version(1, ['a', 'b'], ['v']), $this->version(2, ['a', 'b'], ['v']));

        self::assertTrue($diff->isEmpty());
        self::assertSame(1, $diff->from);
        self::assertSame(2, $diff->to);
    }

    public function testTheRulesAddedRemovedAndMovedAreFound(): void
    {
        $diff = new RulebookDiffer()->diff($this->version(1, ['a', 'b', 'c', 'd']), $this->version(3, ['e', 'c', 'a', 'b', 'f']));

        self::assertSame(['e', 'f'], array_map(static fn (Rule $rule): string => $rule->id, $diff->added));
        self::assertSame(['d'], array_map(static fn (Rule $rule): string => $rule->id, $diff->removed));
        self::assertSame(['c'], $diff->moved);
        self::assertSame([], $diff->changed);
        self::assertFalse($diff->isEmpty());
    }

    public function testARuleRemovedBeforeOthersMovesNone(): void
    {
        $diff = new RulebookDiffer()->diff($this->version(1, ['a', 'b', 'c']), $this->version(2, ['b', 'c']));

        self::assertSame([], $diff->moved);
        self::assertSame(['a'], array_map(static fn (Rule $rule): string => $rule->id, $diff->removed));
    }

    public function testTheRulesChangedAreFoundLineByLine(): void
    {
        $from = new RulebookVersion(1, [
            new Rule('a', 'Phones', "Phones stay in the bag.\nAsk before you film.\nBreaks every hour.", 'table'),
            new Rule('b', 'Snacks', 'Bring your own.'),
            new Rule('c', 'Dice', 'Roll in the open.'),
        ], new DateTimeImmutable(), 'gm');
        $to = new RulebookVersion(2, [
            new Rule('a', 'Phones', "Phones stay in the bag.\nNo filming.\nBreaks every hour.\nTalk to the GM.", 'table'),
            new Rule('b', 'Food', 'Bring your own.', 'food'),
            new Rule('c', 'Dice', 'Roll in the open.'),
        ], new DateTimeImmutable(), 'gm');

        $diff = new RulebookDiffer()->diff($from, $to);

        self::assertCount(2, $diff->changed);
        [$phones, $snacks] = $diff->changed;
        self::assertSame('a', $phones->after->id);
        self::assertSame('Ask before you film.', $phones->before->lines()[1]);
        self::assertTrue($phones->textChanged());
        self::assertFalse($phones->titleChanged());
        self::assertFalse($phones->categoryChanged());
        self::assertEquals([
            new LineChange(LineKind::Kept, 'Phones stay in the bag.'),
            new LineChange(LineKind::Removed, 'Ask before you film.'),
            new LineChange(LineKind::Added, 'No filming.'),
            new LineChange(LineKind::Kept, 'Breaks every hour.'),
            new LineChange(LineKind::Added, 'Talk to the GM.'),
        ], $phones->lines);
        self::assertTrue($snacks->titleChanged());
        self::assertTrue($snacks->categoryChanged());
        self::assertFalse($snacks->textChanged());
        self::assertSame([], $snacks->lines);
        self::assertSame([], $diff->moved);
    }

    public function testTheLinesRemovedComeBeforeTheLinesAddedAtTheStartAndTheEnd(): void
    {
        $from = new RulebookVersion(1, [new Rule('a', 'A', "one\ntwo\nthree")], new DateTimeImmutable(), 'gm');
        $to = new RulebookVersion(2, [new Rule('a', 'A', "zero\ntwo\nfour\nfive")], new DateTimeImmutable(), 'gm');

        $lines = array_map(static fn (LineChange $line): array => $line->toArray(), new RulebookDiffer()->diff($from, $to)->changed[0]->lines);

        self::assertSame([
            ['kind' => 'removed', 'text' => 'one'],
            ['kind' => 'added', 'text' => 'zero'],
            ['kind' => 'kept', 'text' => 'two'],
            ['kind' => 'removed', 'text' => 'three'],
            ['kind' => 'added', 'text' => 'four'],
            ['kind' => 'added', 'text' => 'five'],
        ], $lines);
    }

    public function testTheVariantsAndTheSystemAreCompared(): void
    {
        $from = new RulebookVersion(1, [new Rule('a', 'A', 'x')], new DateTimeImmutable(), 'gm', ['crits', 'flanking', 'potions'], 'old');
        $to = new RulebookVersion(2, [new Rule('a', 'A', 'x')], new DateTimeImmutable(), 'gm', ['flanking', 'gritty', 'crits-max'], 'new');

        $diff = new RulebookDiffer()->diff($from, $to);

        self::assertSame(['crits-max', 'gritty'], $diff->variantsAdded);
        self::assertSame(['crits', 'potions'], $diff->variantsRemoved);
        self::assertSame('old', $diff->systemBefore);
        self::assertSame('new', $diff->systemAfter);
        self::assertFalse($diff->isEmpty());
    }

    /**
     * @return iterable<string, array{RulebookDiff}>
     */
    public static function notEmpty(): iterable
    {
        $rule = new Rule('a', 'A', 'x');

        yield 'added' => [new RulebookDiff(1, 2, added: [$rule])];
        yield 'removed' => [new RulebookDiff(1, 2, removed: [$rule])];
        yield 'changed' => [new RulebookDiff(1, 2, changed: [new RuleChange($rule, new Rule('a', 'B', 'x'), [])])];
        yield 'moved' => [new RulebookDiff(1, 2, moved: ['a'])];
        yield 'variant added' => [new RulebookDiff(1, 2, variantsAdded: ['v'])];
        yield 'variant removed' => [new RulebookDiff(1, 2, variantsRemoved: ['v'])];
        yield 'system set' => [new RulebookDiff(1, 2, systemAfter: 's')];
        yield 'system unset' => [new RulebookDiff(1, 2, systemBefore: 's')];
    }

    #[DataProvider('notEmpty')]
    public function testADiffIsEmptyOnlyWithoutAnyChange(RulebookDiff $diff): void
    {
        self::assertFalse($diff->isEmpty());
        self::assertTrue(new RulebookDiff(1, 2, systemBefore: 's', systemAfter: 's')->isEmpty());
    }

    public function testADiffIsPlainData(): void
    {
        $from = new RulebookVersion(1, [new Rule('a', 'A', 'x'), new Rule('b', 'B', 'y'), new Rule('c', 'C', 'z')], new DateTimeImmutable(), 'gm', ['v']);
        $to = new RulebookVersion(2, [new Rule('c', 'C', 'z'), new Rule('a', 'A2', "x\nw"), new Rule('d', 'D', 'q')], new DateTimeImmutable(), 'gm', ['w'], 's');

        self::assertSame([
            'from' => 1,
            'to' => 2,
            'added' => [['id' => 'd', 'title' => 'D', 'text' => 'q', 'category' => null]],
            'removed' => [['id' => 'b', 'title' => 'B', 'text' => 'y', 'category' => null]],
            'changed' => [[
                'id' => 'a',
                'before' => ['id' => 'a', 'title' => 'A', 'text' => 'x', 'category' => null],
                'after' => ['id' => 'a', 'title' => 'A2', 'text' => "x\nw", 'category' => null],
                'titleChanged' => true,
                'textChanged' => true,
                'categoryChanged' => false,
                'lines' => [['kind' => 'kept', 'text' => 'x'], ['kind' => 'added', 'text' => 'w']],
            ]],
            'moved' => ['a'],
            'variantsAdded' => ['w'],
            'variantsRemoved' => ['v'],
            'systemBefore' => null,
            'systemAfter' => 's',
        ], new RulebookDiffer()->diff($from, $to)->toArray());
    }

    public function testTheVersionsMayBeComparedInEitherOrder(): void
    {
        $diff = new RulebookDiffer()->diff($this->version(3, ['a', 'c']), $this->version(1, ['a', 'b']));

        self::assertSame(3, $diff->from);
        self::assertSame(1, $diff->to);
        self::assertSame(['b'], array_map(static fn (Rule $rule): string => $rule->id, $diff->added));
        self::assertSame(['c'], array_map(static fn (Rule $rule): string => $rule->id, $diff->removed));
    }

    /**
     * @param list<string> $ids
     * @param list<string> $variants
     */
    private function version(int $number, array $ids, array $variants = []): RulebookVersion
    {
        return new RulebookVersion($number, array_map(static fn (string $id): Rule => new Rule($id, strtoupper($id), 'The rule '.$id.'.'), $ids), new DateTimeImmutable(), 'gm', $variants);
    }
}
