# Reference

| Class | Role |
|---|---|
| `TilttekPointsBundle` | The bundle, its configuration `tilttek_points`, the privacy module when installed |
| `Infrastructure\DependencyInjection\PointsServices` | The services the configuration builds |
| `Infrastructure\Doctrine\PointsTables` | The two tables, added to a schema |
| `Infrastructure\Doctrine\PointsSchemaListener` | Adds the tables to the schema the ORM generates |
| `Infrastructure\Doctrine\DbalLedger` | The ledger on Doctrine DBAL |
| `Infrastructure\Console\ExpireCommand` | `tilttek:points:expire` |
| `Infrastructure\Console\HistoryCommand` | `tilttek:points:history` |
| `Infrastructure\Privacy\PointsPrivacy` | The export and the erasure of a member for `phil/privacy-bundle` |
