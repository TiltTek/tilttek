# Catalogues and licences

## No catalogue is bundled

The library imports no publisher's catalogue and ships none: no unit, no points value, no detachment and no rule of a
published game, and no importer of a catalogue in another tool's format. A game's army data is its publisher's;
copying it needs the publisher's licence, and data published without a licence grants none.

The engine is apart from the data: it knows units, options, slots and constraints, never a game. The examples, the
tests and the fuzz corpora of the library use an invented catalogue only.

## How an application provides its catalogue

An application provides its catalogues through `Contract\CatalogueProvider::find($code)`, which gives the current
version of a catalogue, or null. The data behind it is the application's to hold:

- the catalogue of its own game, or of a game its members write;
- an invented catalogue, for examples, tutorials and tests;
- the catalogue of a game under a licence it holds that allows this use — a licence it checked, for the units, the
  points and the texts it shows.

A catalogue is read with `Catalogue::fromArray()` or `fromJson()` from the application's files or database, in the
shape [Catalogues](catalogues.md) describes, and kept once read. `Catalog\InMemoryCatalogueProvider` holds the
catalogues an application read at boot.

A rule a catalogue's data cannot declare — a game's special case — is an application's own `Contract\Constraint`, as
[Constraints](constraints.md#an-applications-own-constraints) shows: the catalogue stays data.
