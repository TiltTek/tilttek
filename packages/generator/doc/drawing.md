# Drawing

`Generator::draw()` takes the name of a generator and a `Draw`, and returns a `DrawResult`:

```php
use Tilttek\Generator\Draw;

$result = $generator->draw('name.elf', new Draw(count: 5, unique: true, seed: 'table 7'));

$result->results;     // list of texts, structured results or maps
$result->first();     // the first result, or null
$result->isShort();   // fewer results than asked
$result->toArray();   // ['generator' => …, 'results' => […], 'requested' => 5, 'short' => false]
```

## Attributions

`DrawResult::$attributions` lists the `Attribution` of every list, entry and generator the results used — once each,
in the order the draw first used them —, so that the application shows the source, the licence and the attribution
of what it prints. An entry's own attribution takes the place of its list's; a word a Markov list invents carries its
list's. `toArray()` adds them under `attributions`, each as the texts it has, when there are any:

```php
$result->toArray()['attributions'];   // [['source' => 'Old tables', 'licence' => 'CC-BY-4.0'], …]
```

See [Lists and generators](lists-and-generators.md#where-the-text-comes-from).

## A state from one draw to the next

A generator with a state — a table with a memory — takes the state its last draw left, and returns the new one:

```php
$result = $generator->draw('weather', new Draw(count: 7, state: $saved));

$result->state;       // ['cell' => '13'], to keep for the next draw
$result->toArray();   // […, 'state' => ['cell' => '13']]
```

A draw's state is a map of at most `Draw::STATE_VALUES` (32) names to texts of at most `Draw::STATE_LENGTH` (1024)
characters, in UTF-8 like the seed; numbers, booleans and objects that print themselves are read as texts, anything else
is an `InvalidDefinition`. The library keeps no state: the application holds it between draws, by campaign, by table or
by person, and passes it back. A generator without a state ignores the one a draw brings, and its result has no state.
See [Tables with a memory](memory.md).

## Unique results

A unique draw — and `generate('name', 3)` in a template, and a field `{generate: name, count: 3}` — makes results one
after the other until it has enough different ones, comparing them whole, or until it has spent its attempts:
`Limits::$attempts` times the count. It never loops without end when the lists do not have enough: it returns what it
found, and `isShort()` says so. A unique draw of a generator with a state drops a result it already has together with
the state that result left.

## Randomness and seeds

A draw without a seed uses the generator's `Randomizer`: a secure engine by default, a scripted or seeded one in tests.
A draw with a seed uses `Xoshiro256StarStar` seeded with the SHA-256 of the seed: the same seed, the same catalog and
the same version of PHP give the same results, which lets a site replay a draw or share it by its seed.

## Limits

`Limits` stops a generator gone wild; past a limit, the draw fails with `LimitExceeded`.

| Limit | Default | What it bounds |
|---|---|---|
| `depth` | 8 | Generators called inside each other, the drawn one included: a generator that calls itself stops. |
| `picks` | 1000 | Picks, rolls, `between`, calls, extra passages and attempts at an invented word of one result. |
| `milliseconds` | 250 | Time spent on one result, checked at each pick, roll, call and step, and before each template is rendered. |
| `attempts` | 10 | Attempts per result asked of a unique draw. |
| `results` | 100 | Results asked of one draw. |
| `cells` | 2500 | Cells of a map or nodes of a graph. |
| `drawMilliseconds` | 1000 | Time spent on the whole draw — every result, and every attempt of a unique draw —, checked like the time of a result. |
| `steps` | 10000 | Turns of every `for` loop and `{% set %}` assignments of one result. |
| `bytes` | 65536 | The longest text, in bytes of UTF-8, that `replace` or `join` returns, measured before it is made. |
| `megabytes` | 16 | The memory one result may grow by, checked at each step. |

Every template is compiled when the generator is built, so that the time limit counts the drawing only, not the first
compilation of a template. Without a cache directory (`$cache`, the last argument of `Generator`), Twig loads each
compiled template by `eval()`; with one, it writes each template to a PHP file of that directory and loads the file,
which an opcode cache then keeps. A long-running application gives it a directory.

A value of a state longer than `Draw::STATE_LENGTH` characters stops the draw with `LimitExceeded` too, since the next
draw could not bring it back.

The time is read on a `Timer` — `SystemTimer`, the system's monotonic clock, by default. The time limits are checked
before each template is rendered — each cell of a map, each field, each value of a state —, at each pick and at each
step inside a template: a loop stops at the turn that crosses a limit ([The budget of a
result](templates.md#the-budget-of-a-result)). The time of the draw bounds a draw of many results, or a unique draw that
keeps finding the same one: twenty maps asked of a unique draw stop after a second rather than run for ten.
