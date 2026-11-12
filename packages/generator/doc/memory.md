# Tables with a memory

A table with a memory is a generator whose result depends on the one before: the weather of today follows from the
weather of yesterday, a journey counts its days and the miles left, a party exploring a map of hexagons knows where it
is. Such a generator has a `state`: values carried from one result to the next. Each draw receives the state the last
draw left and returns the result with the new state. The library keeps nothing between draws: the application holds the
state — by campaign, by table, by person — and passes it back; `tilttek/generator-bundle` keeps it for an application
that wants it to.

## Writing a state

```yaml
generators:
  travel:
    state:
      day:
        start: "1"
        next: "{{ state.day + 1 }}"
      remaining:
        start: "60"
        next: "{% set left = state.remaining - 12 - roll('2d6') %}{{ left < 0 ? 0 : left }}"
      pace: brisk
    templates: "Day {{ state.day }}: {{ state.remaining }} miles to go at a {{ state.pace }} pace."
```

Each value of `state` has a name — a letter or `_`, then letters, digits or `_` — and either a template, given once and
kept, or a map of a `start` template and an optional `next` template. A state holds at most 32 values. For each result,
in order:

1. each value is given by its `start` template when the previous state has no value of that name — at the first draw,
   or when the generator gained a value —, by its `next` template otherwise, or kept as it was when it has no `next`;
2. the variables are rendered, then the body, as for any generator.

The templates of the state see the previous state as `previous`, and as `state` the values given so far over the
previous ones: a value sees the new value of the values written before it and the previous value of the others. The
variables and the body see the new state as `state` and the previous one as `previous`; at the first draw, `previous`
is empty, so that a template reads it with `|default`. A value is a text; Twig reads a text of digits as a number in
`state.day + 1`, and the generator keeps whole numbers with `//` rather than `/`. A value longer than 1024 characters
stops the draw.

A generator with a state names no variable or field `state` or `previous`. Another generator that calls it with
`generate` or a field `{generate: …}` gets a result made from the `start` templates: the state belongs to the draw.

## Drawing

```php
use Tilttek\Generator\Draw;

$first = $generator->draw('travel', new Draw(count: 3));
$first->state;   // ['day' => '3', 'remaining' => '19', 'pace' => 'brisk']

$next = $generator->draw('travel', new Draw(state: $first->state));
$next->first();  // "Day 4: …"
```

A draw of several results threads the state through them: the second result goes on from the first. The state a draw
returns is the state of its last result. The application may also change the state between draws — a new heading, a
chaos factor raised by a scene, a destination further away — since it is only a map of texts; a value the generator
does not know is dropped, and a value the generator has that the state lacks is started again. A draw with a seed and a
state gives the same results and the same state each time.

## Examples

The `examples/` directory holds three tables with a memory.

**The weather, as a hex flower** (`examples/weather.yaml`): nineteen hexagons, each a weather, and a state `cell` that
says which one the weather is in. Each day moves one step in a direction drawn by weight, staying in place at the
edge; the entries of `weather.cell` carry their neighbours as fields.

```yaml
state:
  cell:
    start: "10"
    next: "{{ pick('weather.cell', where: {cell: state.cell})[pick('weather.direction').text] }}"
templates: "{{ pick('weather.cell', where: {cell: state.cell})|upper_first }}"
```

**A journey** (`examples/travel.yaml`): the state counts the days and the miles left, which the application sets when
the journey starts; each draw is one day on the road, and says how far the party went since the day before:
`{{ previous.remaining|default(state.remaining) - state.remaining }} miles covered`.

**A hexcrawl** (`examples/hexcrawl.yaml`): the state holds where the party is, in axial coordinates `q` and `r`, the
heading the application sets, and the terrain of the hex the party is in. Each draw enters the next hex on the heading,
and its terrain follows the one before through tags on the entries — a forest goes on as forest more often than it
turns to lake:

```yaml
state:
  heading: se
  q: { start: "0", next: "{{ state.q + {n: 0, ne: 1, se: 1, s: 0, sw: -1, nw: -1}[state.heading]|default(0) }}" }
  r: { start: "0", next: "{{ state.r + {n: -1, ne: -1, se: 0, s: 1, sw: 1, nw: 0}[state.heading]|default(0) }}" }
  terrain: { start: plains, next: "{{ pick('hexcrawl.terrain', where: {from: state.terrain}) }}" }
```

The same mechanism gives values the application sets and the generator only reads — a value without `next`: the
terrain of the random encounters, the place of the rumours, and the chaos factor and the odds of the solo play oracle
among the [random tables](tables.md).
