# tilttek/rpg-systems

**Describe a role-playing game system once so every tool reads it: attributes, rolls, terms, translations, and the
source and licence of every record.**

tilttek/rpg-systems describes a role-playing game system the way the other tools of a site need it: a character sheet
reads its attributes and their bounds, a dice roller its named rolls, a rules lookup or a generator its terms by
category, each in the reader's language. A system holds its names and version, its attributes — Strength, `STR`, from 1
to 30 —, its dice expressions in the canonical notation of `tilttek/dice` — the check `1d20`, advantage `2d20kh1` —,
checked and normalised when they are read, its terms — conditions, skills, damage types, traits —, the rule variants a
table may choose, its difficulty numbers — the classes of a check, the budget of an encounter — and the roles it
declares for a party, each with names and descriptions by locale. Every record carries its provenance: the title of its
source, who holds it, where it is published, its licence and the attribution the licence asks for; a translation carries
its own, since a translation is a work of its own.

Systems are written in the library's format, a YAML or JSON document that sets a source once for the whole document
and lets a record state its own; a localisation document lays the texts of one more language over a system. The
attribution renderer gives the notices a page displays — each source whose licence asks for one, once, with the
licence and its address —, as text or as an HTML list safe to print. The importer turns an extract of an open source
into a system of the format: the System Reference Documents 5.1 and 5.2 under CC BY 4.0, Pathfinder Second Edition
under the ORC licence and Fate Core under CC BY 3.0 have a profile each — with the rule variants and difficulty numbers
each source gives —, and a profile of the application's own reads
an extract shaped otherwise. A data package carries one source in one version, already imported, with a manifest that
names its source, licence and attribution, the digest of every document and the publisher's files it came from; the
reader refuses a package whose documents changed or whose system is not the one its manifest names; the packages
Composer installed load by their name. The library holds no game data: the SRD 5.1, the SRD 5.2.1 and Fate Core are
published as data packages of their own. The
list of licences is closed: material under any other licence is refused rather than carried without being read.

It is for an application whose tools share game systems — a community site, a campaign tool, a publishing platform —
and that must show where each piece of rules text comes from. It is a plain PHP library whose dependencies are
`tilttek/dice`, which reads the rolls, `symfony/yaml`, which reads the YAML documents, and `phil/url`, which reads the
address of a source; Composer's runtime API lists the data packages it installed.

## Status

| Part | State |
|---|---|
| `GameSystem`, `Systems`: a system, its records looked up by key and category, the systems of an application | Implemented and tested |
| `Attribute`, `DiceExpression`, `Term`, `Translations`: records with texts by locale, bounds, and rolls checked by `tilttek/dice` | Implemented and tested |
| `RuleVariant`, `Difficulty`, `DifficultyBand`, `PartyRole`: the variants a table may choose, difficulty numbers by band and by table, the roles of a party | Implemented and tested |
| `Provenance`, `Licence`: the source, licence and attribution of every record, from a closed list of licences | Implemented and tested |
| `Localisation`: the texts of one more language laid over a system, with the translation's source | Implemented and tested |
| `Format\SystemReader`, `Format\SystemWriter`, `Format\Syntax`: the YAML and JSON format, read and written back | Implemented and tested |
| `Attribution\AttributionRenderer`: the notices of a system or of a page, as text or HTML | Implemented and tested |
| `Import\Importer`, `Import\ImportProfile`, `Import\TableMapping`: extracts of the SRD 5.1 and 5.2, of Pathfinder Second Edition, of Fate Core, or read by a profile of the application's own | Implemented and tested |
| `Package\DataPackage`, `Package\DataPackageReader`, `Package\DataPackageWriter`, `Package\UpstreamFile`: data packages, one source in one version, checked against their manifest | Implemented and tested |
| `Package\InstalledDataPackages`: the data packages Composer installed, loaded by name into systems | Implemented and tested |
| Data packages `tilttek/rpg-data-srd-5.1`, `tilttek/rpg-data-srd-5.2` and `tilttek/rpg-data-fate-core`, imported from the publishers' files | Built and read back by the tests; packages of their own, each with its tests |
| Data package `tilttek/rpg-data-pf2e`, the rolls, variant rules and difficulty numbers of Pathfinder Second Edition under the ORC License, without upstream file | Built and read back by the tests; a package of its own, with its tests |
| Exceptions under one `RpgSystemsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Systems and records](doc/systems-and-records.md) | Systems, attributes, dice expressions, terms, rule variants, difficulty numbers, party roles, translations, and looking them up. |
| [The format](doc/format.md) | The YAML and JSON documents of a system and of a localisation, field by field. |
| [Sources and attribution](doc/attribution.md) | Provenances, the licences, and the notices a page displays. |
| [Importing open sources](doc/importing.md) | The SRD 5.1, SRD 5.2, Pathfinder Second Edition and Fate Core profiles, the shape of an extract, and a profile of your own. |
| [Data packages](doc/data-packages.md) | One open source in one version, already imported: its name, its structure, its manifest, writing and reading it. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `RpgSystemsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz target and its benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\Format\SystemReader;

$systems = new SystemReader()->load('systems/lanterns.yaml', 'systems/lanterns.es.yaml');
$lanterns = $systems->get('lanterns');

$lanterns->attribute('grit')->names->in('es');   // "Temple"
$lanterns->dice('desperate')->notation;          // "3d6kl2", ready for tilttek/dice
$lanterns->terms('condition');                   // the conditions, in their order

echo new AttributionRenderer()->html($lanterns, 'es');
```

The two documents are in [`examples/`](examples), with two small extracts the importer reads.

## Requirements

PHP 8.5 or later with `ext-mbstring`; `tilttek/dice` 0.3, `symfony/yaml` 8.2 and `phil/url` 0.1, which needs
`ext-intl`; Composer 2.2 or later, whose runtime API lists the installed data packages.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). The
licence covers the library's code; the material of a system keeps the licence its provenance states.
