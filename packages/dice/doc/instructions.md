# Instructions and results

An input holds one instruction or more, separated by `;` or `,`. The instructions are rolled in order, each with its
own result, and each may speak of those before it — never of those after. A result is a number, a text or a list.

```php
$result = $dice->roll($dice->parse('8d10; @1c[>=6]; @1c[=1]; "$2 successes, $3 ones"'), $randomizer);

$result->totals();    // [44, 5, 1, null], for instance
$result->outputs();   // [44, 5, 1, "5 successes, 1 ones"]
```

## Instructions and references

| Construct | Examples | Meaning |
|---|---|---|
| Instructions | `2d6; 1d8`, `2d6, 1d8` | Each rolled with its own result; `;` and `,` separate them alike |
| A result | `$1`, `$2+1` | The result of an instruction before, numbered from 1: its total, its text or its list |
| An item | `$1[0]`, `$2[3]` | An item of that result, numbered from 0: of its list, or of the values of its dice that count |
| The dice of an instruction | `@1`, `@1c[>=6]`, `@2kh1` | The dice that count in an instruction before, judged again as one set, without being rolled again |
| Dice bound | `b[1,2]kh2` | The dice that count in several instructions, as one set; the instructions keep their own results |
| Dice merged | `m[1,2]kh1` | The same set, the instructions merged giving their dice away |

`@` alone stays the dice of the set before, in the same instruction ([notation](notation.md)); `@` followed by a number
is the dice of that instruction. The dice of instructions take the modifiers that judge dice once rolled — keep and
drop, successes, filters, sorts, critical marks —, none that rolls more.

A merged instruction has no total any more: `ExpressionResult::total()` is null and `mergedInto` gives the number of the
instruction that took its dice. Its result is still kept, and `$n` still reads it; its dice cannot be bound or merged
again. A reference does not take the dice of the instruction it reads: `$1` is a value, `@1` a set of dice.

## Groups

`{4d6, 2d10}kh1` rolls each expression of the group with its own total, then judges the totals as dice already rolled:
keep and drop, successes (`{3d6, 3d6, 3d6}>=10`), filters, sorts, critical marks. An expression of a group does not see
the dice outside it, and the dice before (`@`) after a group are its totals. The totals are listed in
`DiceResult::parts` as `expression-1`, `expression-2` and so on.

## Text lists

| Construct | Examples | Meaning |
|---|---|---|
| A list | `1L[sword,bow]`, `L[sword,bow]` | Draws one item, or as many as the count says; the count is a number or an expression between parentheses |
| Unique items | `3Lu[a,b,c,d]` | Draws different items |
| Without a separator | `3Ln[ka,ro,mi]` | Joins the items drawn into one text, `karomi` |
| Weights | `1L[a[2],b,c[3]]` | An item weighing 2 is drawn twice as often as one weighing 1 |
| Ranges | `1L[1..2:a,3..6:b]` | Rolls a number from the lowest bound to the highest; the item whose range holds it is drawn |

An item is written as it is, up to the comma, the bracket or the weight that ends it, its spaces trimmed; between double
quotes, it may hold any character, a comma or a bracket included. An item is a text or a translation key, never HTML.
The ranges of a list follow each other, from the first to the last, with no gap; a list is weighted or on ranges, not
both. A unique list draws among the items left, by their weights or the width of their ranges.

`ListResult` keeps the items drawn, the index of each in the list (`draws`), the number rolled for each (`rolls`: from 1
to the total weight, or on the ranges) and the count when it was rolled. Each draw counts as a die against the
[limits](limits.md).

## Texts and sentences

A text is written between double quotes: `"Critical!"`, `"weapon.sword"`. Without placeholders, it is a text or a
translation key, as the application reads it — never HTML; the page escapes it. With placeholders, it is a sentence,
whose placeholders are replaced by results when it is rolled:

| Placeholder | Replaced by |
|---|---|
| `%1` | The result it speaks of: the subject of the branch it is in, or the instruction just before |
| `%2` | The values of the dice that count in that result, separated by commas |
| `%3` | The results of every instruction before, separated by commas |
| `$n`, `$n[i]` | The result of instruction `n`, or its item `i` |
| `@n` | The values of the dice that count in instruction `n`, separated by commas |

A list is written with its items separated by a comma and a space, or together when it has no separator. A backslash
writes a line break, `\n`, and writes as such a backslash, a double quote, `%`, `$`, `@`, `[` or a digit that follows
it: `"50\% off"`, `"$1\2"` is the result of instruction 1 followed by the digit 2. A sign not followed by a digit is
written as such: `"100 %"`.

## Branches

| Construct | Examples | Meaning |
|---|---|---|
| If | `1d20i[>=15]{"hit"}{"miss"}` | The first branch when the condition holds, the second otherwise — or the subject itself when there is none |
| Switch | `1d6S[1..2]{"a"}[2..5]{"b"}{"c"}` | As a list, the branch of every case that holds; the last branch, without a condition, when none holds — or the subject itself |
| Exclusive switch | `1d6S^[1..2]{"a"}[3..5]{"b"}{"c"}` | The branch of the first case that holds |

A branch applies to the operand just before it — dice, a list of values, a group, a reference, an expression between
parentheses: `(1d20+5)i[>=15]{…}`. Its condition is a validator between brackets, with the scope it judges the subject
in: its value by default, `all:` or `any:` of the dice that count in it, `total:` their sum without what is added to
them. A subject without dice is judged as one die of its value. A branch holds anything an instruction may: a number,
dice, a text, a list, another branch. Only the branch that holds is rolled. The subject is a number: a text or a list
cannot be judged.

`BranchResult` keeps the subject, the cases that held — by their index from 0, an `if` being one case —, whether the
default branch gave the result, and the result.

## Repetitions

`repeat(4d6kh3, 6)` rolls its first argument as many times as its second says — a number, or an expression rolled
first — and gives the results in a list. What it repeats may be a text or a list, whose items join the list. Each
repetition counts against the [limits](limits.md), those of `repeat()` within `repeat()` once for each time.

## Comments

| Construct | Example |
|---|---|
| To the end of the line | `2d6 # fire damage`, `2d6 // fire damage` |
| Between brackets, after an operand | `2d6[fire] + 1d4[cold]` |

Comments take no part in the roll nor in the canonical form; `Pool::$comments` keeps them, by the index from 0 of the
instruction they are written in. A comment to the end of the line ends at a line break, so an instruction after it starts
on the next line. A comment between brackets holds no quote nor `#`; after a modifier that takes a threshold between
brackets — a reroll, an explosion, a critical mark, occurrences —, brackets are read as the threshold.

## Results

| Result | Gives |
|---|---|
| `ConstantResult`, `OperationResult`, `NegationResult`, `FunctionResult`, `DiceResult` | A number; the labels of the faces that count for custom dice whose faces carry no value |
| `TextResult` | A text |
| `ListResult` | A list of numbers and texts, or one text when joined |
| `BranchResult` | What its branch gave |
| `ReferenceResult` | What the instruction it reads gave, or its item |

`NodeResult::output()` gives the number, the text or the list; `value()` gives the number, and raises `CannotRoll` for a
text or a list. `ExpressionResult::output()` is the output of an instruction, `text()` the same written as a sentence
writes it, `total()` the number when it is one; `PoolResult::outputs()` lists the outputs, `totals()` the totals.

## The canonical form

The canonical form separates the instructions by `", "`, writes the dice of one instruction `@n` and those of several
`b[…]` or `m[…]`, the groups `{a, b}`, a list `nL` with `u` then `n` and its items as written unless they need quotes,
a switch `S` — capital, as `L` and the `F` of Fudge dice, so that neither reads as a sort or a digit —, an `if` `i`, and
a text between double quotes with the escapes it needs. Comments are not written.

| Input | Canonical |
|---|---|
| `8d10; @1c[>=6]` | `8d10, @1>=6` |
| `1d6;1d8;B[1]` | `1d6, 1d8, @1` |
| `l[sword, bow]` | `1L[sword,bow]` |
| `2L[a[3], b, c[1]]` | `2L[a[3],b,c]` |
| `1L[a,"b, c"]` | `1L[a,"b, c"]` |
| `1d6s[1]{"a"}` | `1d6S[1]{"a"}` |
| `2d6 [fire] # roll` | `2d6` |
