# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| Documents of the format | Its systems and their localisations, in YAML or JSON | [The format](format.md) |
| `Import\ImportProfile`, `Import\TableMapping` | A profile that reads an extract shaped otherwise, or another open source | [Importing open sources](importing.md#a-profile-of-your-own) |
| `Provenance` | The source, licence and attribution of what it imports or writes | [Sources and attribution](attribution.md) |
| Data packages | One open source in one version, already imported, required with Composer under the type `tilttek-rpg-data`, which `Package\InstalledDataPackages` loads | [Data packages](data-packages.md#loading-the-installed-packages) |
| The method of difficulty numbers | Numbers of its own under a method its tools read, beside `check`, `level-dc`, `budget` and `level` | [Systems and records](systems-and-records.md#difficulty-numbers) |
| The limit of `Format\SystemReader` | The longest document it reads, in bytes | [The format](format.md#reading) |

The limit of `Package\DataPackageReader`, the longest manifest it reads, is its argument too. `Package\InstalledDataPackages`
takes that reader, and the packages and their directories by name instead of those Composer installed.

The records are values the application may also build in code. The services keep no state of their own between two
calls.
