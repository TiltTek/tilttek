# tilttek/random-character

**A ready-to-play character rolled by the creation rules of a game system — attributes within their bounds, names and
traits drawn from tables —, rolled again from its seed and open to edit afterwards.**

tilttek/random-character rolls characters by creation rules a system declares. The rules fill the attributes of a
system of `tilttek/rpg-systems` — 3d6 for each in order, 4d6 drop the lowest dealt at random, or a fixed array dealt at
random — and refuse a value outside the bounds the system sets. They add fields, rolled in order: a name, a lineage, a
background or two different traits drawn from the generators of `tilttek/generator`, an age or starting gold rolled
with the dice of `tilttek/dice`. A field may draw from a generator named by the fields before it: `names.{lineage}`
draws from `names.river` when the lineage came out `river`, and `names.{lineage.key}` reads one value of a structured
result. Before rolling, the rules are checked against the system and the generators — unknown attributes, fixed values
outside their bounds, generators that do not exist — and every problem is listed.

Every character carries a seed, the caller's or one drawn, and the same seed with the same rules and tables gives the
same character again. Each part of a character draws from a seed of its own, derived from the character's seed: a field
added to the rules leaves the stats and the other fields as they were. A character is an immutable value: an edit gives
a new one, the seed kept and the value marked as changed by hand, ready for a character sheet to take over. The
attributions of the tables the fields drew from come with it, each once.

Rules are written in a YAML or JSON document that names a system once and lets a set of rules state its own; every
refusal names its place, `rules[0].fields[2].count`. A roll is told to an observer by the keys of the rules and of the
system, never by the seed or the values.

It is for an application that offers a "roll me a character" button — a campaign tool, a one-shot generator, a game
master who needs a stranger for tonight. It does not keep characters nor show them: the application holds its rules,
tables and sheets. It is a plain PHP library whose dependencies are `tilttek/rpg-systems`, which gives the attributes
and their bounds, `tilttek/generator`, which draws the fields, `tilttek/dice`, which rolls the stats and the rolled
fields, and `symfony/yaml`, which reads the YAML documents. Every service keeps nothing between two calls and serves
every request of a worker.

## Status

| Part | State |
|---|---|
| `CreationRules`, `Stats`, `StatMethod`, `Field`, `FieldKind`: up to 32 attributes and 64 fields, by roll, rolled array or fixed array; fields drawn or rolled, up to 16 values each | Implemented and tested |
| Generators named by the fields before: `names.{lineage}`, `names.{lineage.key}` | Implemented and tested |
| `CharacterRoller`: rules checked against the system and the generators, characters rolled within the system's bounds, from a seed given or drawn | Implemented and tested |
| `RandomCharacter`: the character, its seed, its attributions, edits marked by value | Implemented and tested |
| `RulesCatalogue`: up to 1,000 rules, by key and by system | Implemented and tested |
| `Format\RulesReader`, `Format\Syntax`: the YAML and JSON documents, each refusal at its place | Implemented and tested, fuzzed |
| `Contract\CharacterObserver`, `Event\CharacterRolled`: each roll told by identifiers | Implemented and tested |
| Exceptions under one `RandomCharacterException` interface | Implemented and tested |
| Point-buy and rerolls below a minimum total | Not started |
| The Symfony bundle: the rules as a service, the roll button | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Creation rules](doc/rules.md) | Stats and their methods, fields, generators named by earlier fields, and the catalogue. |
| [The format](doc/format.md) | The YAML and JSON document of a system's creation rules, field by field. |
| [Rolling](doc/rolling.md) | Checking rules, rolling, seeds, bounds, attributions, edits, and the observer. |
| [Extension points](doc/extension-points.md) | What an application gives the library, and where the library stops. |
| [Exceptions](doc/exceptions.md) | `RandomCharacterException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

The `examples/` directory holds the rules of a small system, `lanterns.yaml`, the tables they draw from,
`lanterns-tables.yaml`, and the system's attributes, `lanterns-system.yaml`.

## Quick start

```sh
composer require tilttek/random-character
```

```php
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\RandomCharacter\CharacterRoller;
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RpgSystems\Format\SystemReader;

$systems = new SystemReader()->load('examples/lanterns-system.yaml');
$generator = new Generator(new FileLoader()->catalog(['examples/lanterns-tables.yaml']));
$rules = new RulesReader()->load('examples/lanterns.yaml');
$roller = new CharacterRoller($systems, $generator);

$character = $roller->roll($rules->get('lanterns.wanderer'));
$character->stats;                  // ['grit' => 3, 'wits' => 2, 'glow' => 4]
$character->field('name');          // "Kesh", from the list of the lineage "ash"
$character->seed;                   // 32 hexadecimal digits: keep them to roll the same character again

$roller->roll($rules->get('lanterns.wanderer'), $character->seed)->toArray() === $character->toArray();   // true
$character->withField('name', 'Kestrel')->edited;                                                      // ['fields.name']
```

## Requirements

PHP 8.5 or later, with the `mbstring` extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
