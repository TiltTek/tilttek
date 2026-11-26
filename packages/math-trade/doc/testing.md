# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network:

- the model and its refusals: identifiers at their bounds, groups, offers wishing for themselves or for one offer
  twice, participants without offers, every limit at its bound and one past it;
- the solver on chosen lists: two offers that wish for each other, a loop of three, no trade possible, an empty list,
  more trades before better wishes, groups, several loops, copies of a game guarded and not, identifiers made of
  digits, the seed breaking ties, the order of the list changing nothing;
- properties on random lists: each offer changes hands once at most, each trade is wished for, nobody trades with
  themselves or receives a game twice, the result is the same for the same seed whatever the order of the list, and,
  without games, the solver trades as many offers as a search of every cover does, at the best total priority;
- the assignment underneath, against a search of every assignment on random sparse matrices;
- the JSON format: a document written and read back, and every refusal of the reader.

In the monorepo, Infection holds the library at 100 % of covered mutants.

## The benchmark

`tests/Benchmark/TradeSolverBench.php` solves a convention's trade — 1 000 offers from about 330 participants of
three offers each, every offer wishing for 20 or 100 others, one line in five a group of three, one game for every
two offers so that copies and their guards are common — and reads its JSON document:

```sh
composer bench -- packages/tilttek-math-trade/tests/Benchmark
```

| Subject | 20 wishes per offer | 100 wishes per offer |
|---|---|---|
| Solving | about 0.12 s, 16 MB | about 0.6 s, 84 MB |
| Reading the JSON | about 14 ms, 11 MB | about 60 ms, 42 MB |

The timings are those of one laptop, for scale; the benchmark is not a gate.

## Complexity

With V offers — plus one node for each game a participant is guarded on — and E wished offers, each offer of a group
counted:

- building and checking a list, writing and reading its JSON, are O(V + E);
- the solver builds the graph in O(V + E), then finds a minimum-cost assignment by successive shortest augmenting
  paths: Dijkstra on reduced costs with a binary heap, O(E log V) for each offer the greedy start leaves unassigned,
  so O(V · E log V) at worst;
- the memory is O(V + E).

In practice most offers take their cheapest wish at the start and most paths are short: the solver stays well below
the worst case, as the benchmark shows. The default limits — 5 000 offers, 250 000 wished offers — keep a list
solvable in a few seconds.

## Fuzzing

The JSON reader has a fuzz target, `trade-list-json`, fed by the seeds of `tests/Fuzz/corpus/trade-list-json`:
whatever the document, the reader returns a trade list or refuses it with an exception of the library; a list it
returns is written and read back to the same document, and solves to trades in which each offer changes hands once at
most.

```sh
composer fuzz -- trade-list-json
```

## Testing code that runs a trade

Build the list in the test, and solve it with a fixed seed: the result is the same at every run.

```php
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeSolver;
use Tilttek\MathTrade\Want;

$list = new TradeList([
    new Participant('alice', [new Offer('a1', [new Want('b1')])]),
    new Participant('bob', [new Offer('b1', [new Want('a1')])]),
]);

$result = new TradeSolver()->solve($list, 'test');

self::assertSame('b1', $result->swapOf('a1')?->received);
```
