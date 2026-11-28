# Reference

| Type | Role |
|---|---|
| `GameSystem` | A system: names, version, provenance, attributes, dice expressions, terms, rule variants, difficulty numbers, party roles and the sources of its translations |
| `Systems` | The systems of an application, by key |
| `Attribute` | An attribute, its names, abbreviations, descriptions and bounds |
| `DiceExpression` | A roll in the canonical notation of `tilttek/dice`, checked and normalised |
| `Term` | A term, its names, descriptions and category |
| `RuleVariant` | A rule variant a table may choose, its names and descriptions |
| `Difficulty` | Difficulty numbers: their method, their bands from the easiest and their tables of whole numbers by label |
| `DifficultyBand` | One band of difficulty numbers, its names and its own number |
| `PartyRole` | A role a system declares for the members of a party, its names and descriptions |
| `Translations` | A text by locale, and the text found for a reader |
| `Localisation` | The texts of one more language and the source of the translation |
| `Provenance` | The source, licence and attribution of a record |
| `Licence` | The closed list of licences, what each asks for, and the page of its text |
| `Format\SystemReader` | Reads the documents of the format, YAML or JSON, and loads files |
| `Format\SystemWriter` | Writes a system as a document of the format |
| `Format\Syntax` | YAML or JSON, by a file's extension |
| `Attribution\AttributionRenderer` | The notices of a system or of a page, as text or HTML |
| `Import\Importer` | Turns an extract into a system |
| `Import\ImportProfile` | How an extract turns into a system, with the records its source states once for all; the SRD 5.1, SRD 5.2, Pathfinder Second Edition and Fate Core profiles, and the same profile for another version of its source |
| `Import\TableMapping` | One table of an extract and the fields of its records |
| `Package\DataPackage` | The manifest of a data package: name, system, version, source, documents and upstream files |
| `Package\DataPackageReader` | Reads a data package and checks its documents against the manifest |
| `Package\DataPackageWriter` | Writes a system as a data package |
| `Package\InstalledDataPackages` | The data packages Composer installed, by name, loaded into systems |
| `Package\UpstreamFile` | A file of the publisher a data package was imported from |
| `Exception\RpgSystemsException` and its three exceptions | [Exceptions](exceptions.md) |
