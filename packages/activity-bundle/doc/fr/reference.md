<!-- phil-dev source-sha256=337cded298bfe8988a51b3fdbb6a1e81f6ad4e638462a4e1bb956225217c86e8 -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekActivityBundle` | Le bundle, sa configuration, et le module de vie privée quand `phil/privacy-bundle` est installé |
| `Infrastructure\DependencyInjection\ActivityServices` | Les services et les étiquettes |
| `Infrastructure\Doctrine\ActivityTables` | Les deux tables |
| `Infrastructure\Doctrine\ActivitySchemaListener` | Les tables dans le schéma que génère l'ORM |
| `Infrastructure\Doctrine\DbalActivityLog` | Le journal sur Doctrine DBAL |
| `Infrastructure\Doctrine\DbalCheckpoints` | Les points de reprise sur Doctrine DBAL |
| `Infrastructure\Messenger\MessengerProjections` | L'observateur qui envoie leurs messages aux projections |
| `Infrastructure\Messenger\CatchUpProjection` | Le message : le nom d'une projection et la position d'une entrée |
| `Infrastructure\Messenger\CatchUpProjectionHandler` | Fait rattraper la projection, une seule fois au-delà de son entrée |
| `Infrastructure\Console\CatchUpCommand` | `tilttek:activity:catch-up` |
| `Infrastructure\Console\RebuildCommand` | `tilttek:activity:rebuild` |
| `Infrastructure\Console\StatusCommand` | `tilttek:activity:status` |
| `Infrastructure\Privacy\ActivityPrivacy` | L'export et l'effacement d'un membre |
