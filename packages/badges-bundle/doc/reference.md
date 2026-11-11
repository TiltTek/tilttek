# Reference

| Class | Role |
|---|---|
| `TilttekBadgesBundle` | The bundle and its configuration `tilttek_badges` |
| `Infrastructure\DependencyInjection\BadgesServices` | Declares the services from the configuration |
| `Infrastructure\Doctrine\BadgesTables` | The four tables |
| `Infrastructure\Doctrine\BadgesSchemaListener` | Adds them to the schema the ORM generates |
| `Infrastructure\Doctrine\DbalProgressStore`, `DbalAwardStore`, `DbalProfileStore`, `DbalGrantStore` | The stores on Doctrine DBAL |
| `Infrastructure\Doctrine\Rows` | The idempotent insertion per platform and the reading of row values |
| `Infrastructure\Event\BadgeAwarded`, `BadgeRevoked` | The events |
| `Infrastructure\Event\DispatchingObserver` | Turns what the projection tells into events |
| `Infrastructure\Twig\BadgesExtension` | The Twig functions |
| `Infrastructure\Console\AwardCommand`, `RevokeCommand`, `ListCommand` | The commands |
| `Infrastructure\OpenBadges\BadgeCredentials` | Issues a member's badge as a credential |
| `Infrastructure\Privacy\BadgesPrivacy` | The module of `phil/privacy-bundle` |
