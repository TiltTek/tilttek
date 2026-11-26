# tilttek/conditions

**Track conditions on characters during play: how long they last in rounds or time, how they stack and decay, and what
they change on the sheet.**

tilttek/conditions keeps the conditions of the characters at a table — prone, frightened 2, three doses of poison, a
disease that lasts a week of the fiction, a mutation, a lost arm — and says what they do to the statistics. It knows no
game system: the site declares the conditions of its system in a catalogue, and the library applies their rules.

- **A catalogue of the system.** Each condition says how a new application stacks with one in place (replace, keep the
  highest value, sum the values, keep each application apart, or ignore), whether it has a value and up to what, how
  that value decays at the start or the end of the affected character's turns, the conditions it brings with it —
  unconscious brings blinded and prone — and its modifiers.
- **Durations.** In rounds, counted at the start or the end of the affected character's turns, of the source's turns —
  "until the end of your next turn" — or of the rounds; in game time, run down when the table advances the fiction; in
  real time, read from the clock, as in a live game; or until removed.
- **Modifiers.** Add, multiply by a percentage, raise to a floor, lower to a cap, or override, by the condition's value
  when asked; additions of one type keep only their highest bonus and lowest penalty. The statistics come out with the
  modifiers that made the difference, to show the player why.
- **Safe to retry.** A turn boundary or a time advance carries an identifier: told twice, it counts once.

The service reads the time from a PSR-20 clock and from nothing else. It tells when the next condition in real time
ends, for the application's scheduled task to end it then. The facts of a character's conditions — applied, changed,
ended and why — go to an observer, each once, by identifiers only.

It is a plain PHP library: one service, a calculator, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `ConditionDefinition`, `ConditionCatalogue`, `Modifier`, `Decay`, `Duration`: the system's conditions, bounded and checked | Implemented and tested |
| `Conditions`: apply by the stacking rule, remove, set a value, turns, rounds, game time, real-time expiry | Implemented and tested |
| `Effect\EffectCalculator`: the statistics once the conditions are counted, with the system's rounding | Implemented and tested |
| `View\ConditionsView`: the conditions to show the table, and the next end in real time | Implemented and tested |
| `Store\InMemoryConditionStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the catalogue from configuration, the Doctrine store, scheduled expiry and a Stimulus display | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Conditions and effects](doc/conditions.md) | The catalogue, stacking, values and decay, durations, turns and ticks, modifiers and the view. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the clock, the catalogue and the rounding. |
| [Exceptions](doc/exceptions.md) | `ConditionsException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\Conditions\ConditionCatalogue;
use Tilttek\Conditions\ConditionDefinition;
use Tilttek\Conditions\Conditions;
use Tilttek\Conditions\Decay;
use Tilttek\Conditions\Duration;
use Tilttek\Conditions\Modifier;
use Tilttek\Conditions\Stacking;

$catalogue = new ConditionCatalogue([
    new ConditionDefinition('prone', modifiers: [Modifier::add('ac', -2)]),
    new ConditionDefinition('frightened', Stacking::Highest, maxValue: 4,
        modifiers: [Modifier::add('checks', -1, perValue: true, type: 'status')],
        decay: new Decay(1)),                                       // falls by 1 at the end of each of its turns
    new ConditionDefinition('poisoned', Stacking::Separate, maxInstances: 3,
        modifiers: [Modifier::add('str', -1)], duration: Duration::gameTime(3600)),
]);

$conditions = new Conditions($store, $catalogue, $clock, $observer);   // your ConditionStore, a PSR-20 clock, an observer

$conditions->apply('hero-1', 'frightened', 2, source: 'dragon-1');
$conditions->apply('hero-1', 'prone', duration: Duration::rounds(1));

$conditions->endTurn('enc-4:r1:hero-1:end', 'hero-1', ['hero-1', 'dragon-1']);   // frightened 1, prone ends
$conditions->advanceTime('enc-4:rest', 3600, ['hero-1']);                         // an hour of the fiction

$sheet = $conditions->effective('hero-1', ['ac' => 16, 'checks' => 5, 'str' => 3]);
// $sheet['checks']->value === 4, and $sheet['checks']->contributions says why

$view = $conditions->view('hero-1');                              // send $view->toArray() to the table
```

## Requirements

PHP 8.5 or later, with `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
