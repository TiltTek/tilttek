<!-- phil-dev source-sha256=f8c482d952a6d17c10ab76c0bc148b60214b09e74afebdce6cf6868fcf6e446e -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekPointsBundle` | Le bundle, sa configuration `tilttek_points`, le module de vie privée quand il est installé |
| `Infrastructure\DependencyInjection\PointsServices` | Les services que construit la configuration |
| `Infrastructure\Doctrine\PointsTables` | Les trois tables, ajoutées à un schéma |
| `Infrastructure\Doctrine\PointsSchemaListener` | Ajoute les tables au schéma que génère l'ORM |
| `Infrastructure\Doctrine\DbalLedger` | Le registre sur Doctrine DBAL |
| `Infrastructure\Console\ExpireCommand` | `tilttek:points:expire` |
| `Infrastructure\Console\HistoryCommand` | `tilttek:points:history` |
| `Infrastructure\Privacy\PointsPrivacy` | L'exportation et l'effacement d'un membre pour `phil/privacy-bundle` |
