# tilttek/game-catalog

**Keep one catalogue of board and role-playing games: open database identifiers, errata, member corrections kept apart,
and collections imported by file.**

tilttek/game-catalog keeps the games an application shows, one entry per game, so that game nights, player searches
and members' shelves all speak of the same game. An entry holds what the publisher states — name, type, kind, game
system, publishers, designers, year, numbers of players, playing time, minimum age, languages, levels of a scenario,
dependence on language, description, cover — and, beside it:

- the identifiers of the game in open databases: BoardGameGeek and RPGGeek, of which the catalogue keeps the
  identifier and builds the link, and copies nothing else; Wikidata, whose data is CC0, as a free anchor;
- its errata, in the order they were published, with an observer told of each one, for the members who follow the
  game;
- the corrections of the members, on any field, kept beside the entry until the administration accepts or refuses
  them; once accepted, kept beside the values of the source, so that the source sending the entry again does not undo
  them;
- the votes of the members, shown beside the values of the publisher and never in their place: for each number of
  players a rating of 1 to 5 and one of the three choices — best, recommended, not recommended —, the weight, the
  minimum age and the dependence on language;
- its links to other entries — expansion, reimplementation, edition, translation and the others of the OpenTabletop
  vocabulary —, and the barcodes of its boxes, so that a box scanned once is identified locally the next time;
- its history.

Entries are entered by hand or sent by sources, the ports that feed the catalogue: the catalogue keeps every entry it
was sent and stands without any third-party service. A member imports their collection from the file they export —
the role-playing games first —, matched by identifier; nothing of the file but the identifiers, the statuses, the
priority of a wish and the plays is read. The experience of a reviewer — games played and run — is frozen beside each
review through a port to the application's review system.

It is a plain PHP library: services, ports, value objects and a store kept in memory for tests. A Symfony bundle, with
the Doctrine store, the synchronisation through Messenger, the queue of corrections and the Twig components of the
page of a game, comes later.

## Status

| Part | State |
|---|---|
| `Details`, `Entry`, `Origin`, `ExternalId`, `Gtin`, `Erratum`, `Link`: an entry, its identifiers, its barcodes, its errata and its links | Implemented and tested |
| `Catalog`: entries by hand and from sources, identifiers, errata, links, barcodes identified locally then through a port, hiding, the page of a game, the history | Implemented and tested |
| `Corrections`: corrections proposed on any field, accepted with conflicts shown, refused with a note, fields returned to their source | Implemented and tested |
| `Polls`, `Ballot`, `PollResults`: the ratings and the three choices by number of players, the weight, the age, the dependence on language | Implemented and tested |
| `Reviews`: the experience of a reviewer, frozen | Implemented and tested |
| `Import\CollectionReader`, `Import\CollectionImporter`: a collection file read within bounds, and matched to the catalogue | Implemented and tested |
| `Store\InMemoryCatalogStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, the queue of corrections, the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Entries and sources](doc/catalogue.md) | Entries, sources, identifiers, errata, links, barcodes, hiding and the page of a game. |
| [Corrections](doc/corrections.md) | Proposing, accepting, refusing and returning a field to its source. |
| [Votes](doc/polls.md) | Ballots, the results, and how they stand beside the values of the publisher. |
| [Collection import](doc/import.md) | The file a member exports, its bounds, and how its games are found. |
| [Vocabulary](doc/vocabulary.md) | The terms of OpenTabletop the library takes, and those it adds. |
| [Extension points](doc/extension-points.md) | The store, the sources, the barcode resolver, the observer, the plays and the reviews. |
| [Exceptions](doc/exceptions.md) | `CatalogException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests and its fuzz target. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Corrections;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Range;

$catalog = new Catalog($store, $clock);              // your CatalogStore and PSR-20 clock
$catalog->add('lanterns', new Details(
    'Lanterns',
    kind: GameKind::RolePlaying,
    system: 'lanterns',
    players: new Range(3, 6),
    languages: ['fr'],
), [ExternalId::rpgGeek(100), ExternalId::wikidata('Q100')]);

$corrections = new Corrections($store, $clock);
$corrections->propose('c-1', 'lanterns', 'm-104', new Details('Lanterns', kind: GameKind::RolePlaying,
    system: 'lanterns', players: new Range(2, 6), languages: ['fr']));
$corrections->accept('c-1', 'admin-1');

$sheet = $catalog->sheet('lanterns');
$sheet->details()->players;                           // 2 to 6, as corrected
$sheet->results->bestByChoice();                      // the members' best numbers of players
$sheet->entry->identifier(IdentifierScheme::RpgGeek)?->link();
```

## Requirements

PHP 8.5 or later, with `mbstring`, and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). The
vocabulary of game types and links follows the OpenTabletop data model, CC-BY-4.0, as
[the vocabulary](doc/vocabulary.md) credits.
