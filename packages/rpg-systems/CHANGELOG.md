# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/rpg-systems` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Translations`: a text by locale — at most 64 locales, each text at most 4 000 characters of UTF-8 without control
  characters other than line breaks and tabs —, locales written `fr`, `fr_CA`, `fr-ca` or `es-419` and kept
  normalised, and the text found for a reader: the locale, its language, the fallback locale, then the first text.
- `Licence` and `Provenance`: a closed list of licences — CC BY 4.0, CC BY 3.0, CC BY-SA 4.0, CC0 1.0, the ORC licence
  and the application's own material —, what each asks for and the page of its text; the title, licence, attribution, author
  and address of a source, the attribution required by a licence that asks for one, the address read by `phil/url`.
- `Attribute`, `DiceExpression` and `Term`: the records of a system, each with a key of lowercase ASCII and single
  dots, names by locale and its provenance; an attribute's abbreviations, descriptions and bounds; a roll read,
  checked and normalised by `tilttek/dice`, ready to roll and written in the notation of a language; a term's
  descriptions and category.
- `RuleVariant`, `Difficulty`, `DifficultyBand` and `PartyRole`: the rule variants a table may choose; difficulty
  numbers for one purpose — a method, bands from the easiest each with its own number when it has one, and tables of
  whole numbers by label three levels deep at most, up to 24 bands and 10 000 numbers within ±1 000 000 000, found by
  `number()` —; the roles a system declares for a party.
- `GameSystem`, `Systems` and `Localisation`: a system's names, version, provenance and records — attributes, dice
  expressions, terms, rule variants, difficulty numbers and party roles, at most 10 000 together —, looked up by key or
  by category in their order, the sources it draws from each once, and the texts of one more language laid over it
  with the translation's source, the names of the bands of difficulty included; the systems of an application by key.
- `Format\SystemReader`, `Format\SystemWriter` and `Format\Syntax`: the library's format in YAML or JSON —
  `format: tilttek-rpg-systems/1` —, the fields `variants`, `difficulties` and `roles` beside the attributes, dice and
  terms, a source set once for a document and stated by a record where it differs, texts
  as a mapping or a single text in the document's locale, localisation documents laid over their system, files loaded
  systems first; a strict reader that refuses an unknown field with its place, YAML without objects or tags, JSON to a
  depth of 64, documents of more than 2 000 000 bytes and lists of more than 10 000 records; a writer whose documents
  read back to an equal system.
- `Attribution\AttributionRenderer`: the notices of a system or of the records a page shows, one for each source whose
  licence asks for attribution, with the sources of the translations of a locale, as text lines or as an escaped HTML
  list whose licences link with `rel="license"`.
- `Import\Importer`, `Import\ImportProfile` and `Import\TableMapping`: an extract of an open source — tables of
  records in JSON or YAML — turned into a system whose every record carries the source the caller states, refused when
  its licence is not one the profile accepts; profiles for the System Reference Documents 5.1 and 5.2 under CC BY 4.0,
  for Pathfinder Second Edition under the ORC licence and for Fate Core under CC BY 3.0, with their named rolls and
  the rule variants and difficulty numbers each source gives — the typical difficulty classes of the SRDs, the two
  variants of the SRD 5.1, the XP budget of a combat encounter of the SRD 5.2, and the four variant rules, simple DCs,
  DCs by level and encounter budget of Pathfinder Second Edition —, profiles of the application's own, which state
  such records too, and `ImportProfile::withVersion()`, the same profile for the version of the files an extract is
  taken from.
- `Package\DataPackage`, `Package\DataPackageReader`, `Package\DataPackageWriter` and `Package\UpstreamFile`: data
  packages, one source in one version imported into the format, with a manifest — `format: tilttek-rpg-data/1` — that
  names the system, its version and its source, the SHA-256 digest of every document and the publisher's files it was
  imported from; a writer of the system's document, the manifest and the notices; a reader that refuses a document out
  of the package or changed after its manifest, another system, version or source, an unknown field and a manifest of
  more than 100 000 bytes; tests that read back, strictly, the data packages of the SRD 5.1, the SRD 5.2.1 and Fate
  Core imported from their publishers' files, and of Pathfinder Second Edition under the ORC License, which names no
  upstream file, with the rule variants and difficulty numbers of their profiles.
- `Package\InstalledDataPackages`: the data packages Composer installed — type `tilttek-rpg-data` —, or those an
  application lists with their directories, by name in alphabetical order, each loaded with the strict reader and
  refused when it is installed without a directory or its manifest declares another name; their systems together,
  refused when two share a key. The library holds no game data: the records of each source are in its data package.
- `Format\SystemReader::provenance()`: a provenance read from a mapping, as a document's `source` holds it.
- Exceptions under one `RpgSystemsException` interface: `InvalidRecord`, `InvalidDocument` with the place of the
  mistake, and `UnknownRecord`.
- Examples written for the tests in `examples/`: a system with its rule variant, difficulty numbers and party roles,
  its Spanish localisation, and four small extracts.
- A fuzz target of the reader and benchmarks of reading, looking up and rendering the attribution.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
