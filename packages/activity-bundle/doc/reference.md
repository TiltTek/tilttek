# Reference

| Class | Role |
|---|---|
| `TilttekActivityBundle` | The bundle, its configuration, and the privacy module when `phil/privacy-bundle` is installed |
| `Infrastructure\DependencyInjection\ActivityServices` | The services and the tags |
| `Infrastructure\Doctrine\ActivityTables` | The two tables |
| `Infrastructure\Doctrine\ActivitySchemaListener` | The tables in the schema the ORM generates |
| `Infrastructure\Doctrine\DbalActivityLog` | The journal on Doctrine DBAL |
| `Infrastructure\Doctrine\DbalCheckpoints` | The checkpoints on Doctrine DBAL |
| `Infrastructure\Messenger\MessengerProjections` | The observer that sends the projections their messages |
| `Infrastructure\Messenger\CatchUpProjection` | The message: a projection's name and an entry's position |
| `Infrastructure\Messenger\CatchUpProjectionHandler` | Catches the projection up, once past its entry |
| `Infrastructure\Console\CatchUpCommand` | `tilttek:activity:catch-up` |
| `Infrastructure\Console\RebuildCommand` | `tilttek:activity:rebuild` |
| `Infrastructure\Console\StatusCommand` | `tilttek:activity:status` |
| `Infrastructure\Privacy\ActivityPrivacy` | The export and the erasure of a member |
