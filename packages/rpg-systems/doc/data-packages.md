# Data packages

A data package carries one open source, in one version, already imported into the library's format: a Composer
package an application requires instead of importing the source itself. It holds the system's documents, a manifest
that names the source, its licence and the attribution a page displays, the digest of every document, and the
upstream files the documents were imported from, so that anyone can check the import against the publisher's own
files.

## Name

One package per source and per version, named after the source and its version:

| Source | Package |
|---|---|
| System Reference Document 5.1 | `vendor/rpg-data-srd-5.1` |
| System Reference Document 5.2 | `vendor/rpg-data-srd-5.2` |
| Pathfinder Second Edition | `vendor/rpg-data-pf2e` |
| Fate Core System | `vendor/rpg-data-fate-core` |

## Published packages

Four data packages are published; three are imported from their publisher's file, whose address, size and SHA-256
digest their manifest names:

| Package | System | Version | Licence | Records |
|---|---|---|---|---|
| `tilttek/rpg-data-srd-5.1` | `srd-5.1` | `5.1` | `CC-BY-4.0` | 6 attributes; 18 skills, 15 conditions, 13 damage types and 8 schools of magic; 3 dice expressions; 2 rule variants; the difficulty numbers `check` |
| `tilttek/rpg-data-srd-5.2` | `srd-5.2` | `5.2.1` | `CC-BY-4.0` | 6 attributes; 18 skills, 15 conditions, 13 damage types and 8 schools of magic; 3 dice expressions; the difficulty numbers `check` and `encounter` |
| `tilttek/rpg-data-fate-core` | `fate-core` | `2013` | `CC-BY-3.0` | 18 skills, 6 approaches of Fate Accelerated and the 11 steps of the ladder; 1 dice expression |
| `tilttek/rpg-data-pf2e` | `pf2e` | `2023` | `ORC` | 3 dice expressions; 4 rule variants; the difficulty numbers `simple`, `level` and `encounter` |

The rule variants and difficulty numbers are those the import profile states, as the source gives them; no source
declares party roles. Fate Core has no numbered version: its package's version is the year of its edition.

The publisher of Pathfinder Second Edition distributes no reference document as a file: the rules under the ORC licence
are read in its books, Pathfinder Player Core and Pathfinder GM Core, whose copyright year is the package's version.
Its package holds the records its import profile states — the rolls, the variant rules and the difficulty numbers —,
names no upstream file, and carries in its attribution the ORC notice and the credits of the two books; its README and
`LICENSE` add the other notices the ORC License asks for. It holds no attribute and no term: they are read from a
source by the profile, which this package does not claim. Its `composer.json` declares the licence `LicenseRef-ORC`,
the ORC License having no SPDX identifier.

A new version of a source that changes its rules — 5.1 to 5.2 — is a new package, so that an application chooses the
rules it plays by; a correction of the same version is a new release of the same package.

## Structure

```text
rpg-data-srd-5.2/
├── composer.json      the package, under the licence of the material
├── rpg-data.json      the manifest
├── system.json        the system, in the library's format
├── ATTRIBUTION.txt    the notices a page that shows the material displays
└── LICENSE            the text of the material's licence
```

The `composer.json` of a data package carries the licence of the material, not the library's:

```json
{
    "name": "vendor/rpg-data-srd-5.2",
    "description": "The System Reference Document 5.2, in the format of tilttek/rpg-systems",
    "license": "CC-BY-4.0",
    "type": "tilttek-rpg-data",
    "require": {
        "tilttek/rpg-systems": "^0.1"
    }
}
```

## The manifest

```json
{
    "format": "tilttek-rpg-data/1",
    "name": "vendor/rpg-data-srd-5.2",
    "system": "srd-5.2",
    "version": "5.2.1",
    "source": {
        "title": "System Reference Document 5.2.1",
        "licence": "CC-BY-4.0",
        "attribution": "The attribution statement the source asks for, copied from the source itself.",
        "author": "Its publisher",
        "url": "https://the-address-of-the-source.example"
    },
    "documents": {
        "system.json": "the SHA-256 digest of system.json"
    },
    "upstream": [
        {
            "title": "SRD 5.2.1, English",
            "url": "https://the-address-of-the-file.example/srd.pdf",
            "sha256": "the SHA-256 digest of the file",
            "bytes": 6031375,
            "locale": "en"
        }
    ]
}
```

| Field | Required | What it holds |
|---|---|---|
| `format` | Yes | `tilttek-rpg-data/1` |
| `name` | Yes | The package's Composer name |
| `system` | Yes | The key of the one system the documents hold |
| `version` | Yes | The version of the source; the system's version must be the same |
| `source` | Yes | The provenance of the system, as a document's `source` holds it ([Sources and attribution](attribution.md)) |
| `documents` | Yes | 1 to 64 documents of the format, each with its SHA-256 digest, by its path relative to the package |
| `upstream` | No | Up to 64 files of the publisher, each with its title, address, digest, size and language |

A document's path is made of segments of letters, digits, `.`, `_` and `-`, none starting with a dot, at most eight,
and ends in `.yaml`, `.yml` or `.json`: no absolute path, no `..`. The licence of the source must be one of the closed
list; material under any other licence is refused as it is in a document.

## Writing a package

The importer gives the system, the writer the package:

```php
$system = new Importer()->importText(ImportProfile::srd52()->withVersion('5.2.1'), $extract, Syntax::Json, $srd);

new DataPackageWriter()->write('build/rpg-data-srd-5.2', 'vendor/rpg-data-srd-5.2', $system, [
    new UpstreamFile('SRD 5.2.1, English', $address, hash_file('sha256', $pdf), filesize($pdf), 'en'),
]);
```

`write()` writes `system.json`, `rpg-data.json` and `ATTRIBUTION.txt` into an existing directory, replacing them;
`manifest()` gives the manifest without writing anything, and `json()` its text. A system without version is refused:
a data package holds one version of its source, and `ImportProfile::withVersion()` gives the system the version of the
files it is imported from — `5.2.1` for the SRD 5.2. The `composer.json` and the `LICENSE` are written by the package's
maintainer.

## Loading the installed packages

A data package's `composer.json` declares the type `tilttek-rpg-data`. `InstalledDataPackages` finds the packages of
that type Composer installed, by their name, and reads each one with the strict reader: requiring a package is all an
application does to know its system.

```shell
composer require tilttek/rpg-data-srd-5.2
```

```php
use Tilttek\RpgSystems\Package\InstalledDataPackages;

$packages = new InstalledDataPackages();

$packages->names();                              // ["tilttek/rpg-data-srd-5.2"], in alphabetical order
$srd = $packages->load('tilttek/rpg-data-srd-5.2');
$systems = $packages->systems()->with($ownSystem);
```

`load()` raises `UnknownRecord` for a package that is not installed, and `InvalidDocument` for a package installed
without a directory, whose manifest declares another name than the one it is installed under, or that the reader
refuses; `systems()` raises `InvalidRecord` when two packages hold a system of the same key. The packages are found
and read again at each call: an application that loads them at each start keeps the `Systems` it read in its cache.

The constructor takes the reader — with the limit of its manifests — and, instead of Composer's list, the packages
and their directories by name, for a package kept outside `vendor/` or for a test:

```php
$packages = new InstalledDataPackages(installed: ['acme/rpg-data-open-ranges' => __DIR__.'/data/open-ranges']);
```

## Reading a package

```php
$reader = new DataPackageReader();

$system = $reader->load('vendor/vendor/rpg-data-srd-5.2');
$package = $reader->manifest('vendor/vendor/rpg-data-srd-5.2');
```

`load()` reads the manifest, then every document, and refuses the package — with an `InvalidDocument` naming the
place — when:

- a document is not a file inside the package, a link out of it included;
- a document does not match its digest: it changed after the manifest was written;
- the documents do not hold exactly one system, of the manifest's key;
- the system's version is not the manifest's;
- the system's source is not the manifest's.

The manifest is read as JSON, at most 100 000 bytes — the limit is the reader's argument —, strictly: an unknown field
is refused with its place. The documents are read by `SystemReader`, with its own limits ([The
format](format.md#reading)).

## What the library holds

The library holds no game data: the records of a source — its attributes, terms, rolls, rule variants and difficulty
numbers, with their texts — come in its data package, under the licence of the material. Built in are the import
profiles alone, which say how an extract of each source turns into a system and state the records the source gives
once for all — the named rolls, the rule variants and the difficulty numbers, without their texts beyond their
names —, so that a data package is built from them ([Importing open sources](importing.md)). The library fetches
nothing: the application, or the maintainer of a data package, obtains the source's files from their publisher and
checks their licence before importing them.
