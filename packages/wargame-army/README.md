# tilttek/wargame-army

**Army lists for miniatures games on a generic engine apart from the catalogues the application provides: units,
options, model counts, points, detachment slots and declared constraints, validated one rule at a time with clear
messages and exported as text or escaped HTML, with no publisher's catalogue bundled.**

The engine knows no game. A catalogue brings a game's data: its units with their categories, points, models and option
groups, its detachment types with their slots by category, the number of detachments an army takes, and the
constraints it declares — *one leader at least*, *at most one monster*, *heroes cost at most 25 % of the points*. An
army list names its catalogue and the version it was built with, its points limit, and its detachments of units with
their models and options.

The validator checks a list against its catalogue one declared rule at a time and returns every violation at once, each
with a sentence people read and the values it was built from: *Detachment 2 "Raiders", unit 1 "Scouts": 11 models;
the unit takes 5 to 10.* The points of each unit, detachment and list come with it. An application adds its own rules —
a club's points cap, a tournament's restrictions — as constraints of its own. The export writes the list as plain text
or as a fragment of escaped HTML, to read, paste or print.

The catalogues are the application's, behind a port: the library ships none. It is a plain PHP library, with no
dependency beyond PHP, an in-memory adapter for its port, and nothing kept between two calls.

## Status

| Part | State |
|---|---|
| `Catalogue`, `UnitEntry`, `OptionGroup`, `Option`, `DetachmentType`, `Slot`: a game's data, read from and written to arrays and JSON | Implemented and tested |
| `ArmyList`, `Detachment`, `UnitSelection`: lists that change by new lists, read from and written to arrays and JSON | Implemented and tested |
| `Costing`: the points of each unit, detachment and list | Implemented and tested |
| `ArmyValidator`, `Validation`, `Violation`, `Rule`: every broken rule, with a clear message | Implemented and tested |
| `Constraint\UnitCount`, `Constraint\PointsShare`, `Contract\Constraint`: constraints a catalogue declares, and an application's own | Implemented and tested |
| `Export\TextExport`, `Export\HtmlExport`: lists readable and printable | Implemented and tested |
| `Contract\CatalogueProvider` and its in-memory adapter | Implemented and tested |
| A catalogue of any game | Not provided: the application provides its own, as [Catalogues and licences](#catalogues-and-licences) says |
| A PDF export | Not provided: the HTML export is the source an application prints or converts |

## Catalogues and licences

The library imports no publisher's catalogue, and ships no unit, no points value and no rule of a published game. A
game's army data is its publisher's: an application provides its own catalogues — of its own game, invented, or under
a licence it holds that allows this use —, through `Contract\CatalogueProvider`. The examples and tests use an invented
catalogue only. [Catalogues and licences](doc/catalogues-and-licences.md) says how an application provides its
catalogue.

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Catalogues](doc/catalogues.md) | Units, options, detachment types, slots and the shape of a catalogue in JSON. |
| [Army lists and validation](doc/army-lists-and-validation.md) | Lists, points, the validator and its messages. |
| [Constraints](doc/constraints.md) | Counts and points shares a catalogue declares, and an application's own constraints. |
| [Export](doc/export.md) | The text and HTML of a list. |
| [Catalogues and licences](doc/catalogues-and-licences.md) | Why no catalogue is bundled, and how an application provides its own. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `WargameArmyException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\WargameArmy\{ArmyList, ArmyValidator, Catalogue, Detachment, UnitSelection};
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Export\TextExport;

$catalogue = Catalogue::fromJson(file_get_contents('catalogues/ember-vale.json'));   // the application's own
$validator = new ArmyValidator(new InMemoryCatalogueProvider($catalogue));

$list = ArmyList::for($catalogue, 'list-1', 'Ember Wardens', 500)
    ->withDetachment(new Detachment('vanguard', 'Wardens', [
        new UnitSelection('captain', 1, ['bow' => 1]),
        new UnitSelection('scouts', 6, ['long-rifle' => 1]),
    ]));

$validation = $validator->validate($list);
$validation->isValid();             // true or false
$validation->messages();            // every broken rule, in order
$validation->costing->total;        // the points of the list

echo new TextExport()->render($list, $catalogue, $validation);
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
