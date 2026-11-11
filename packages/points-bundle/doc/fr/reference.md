<!-- phil-dev source-sha256=9d70619369adeaa13f632f925154c6e5cae0d13402f66035fb3ea15f16415aac -->
# Référence

| Classe | Rôle |
|---|---|
| `TilttekPointsBundle` | Le bundle, sa configuration `tilttek_points`, le module de vie privée quand il est installé |
| `Infrastructure\DependencyInjection\PointsServices` | Les services que construit la configuration |
| `Infrastructure\Doctrine\PointsTables` | Les deux tables, ajoutées à un schéma |
| `Infrastructure\Doctrine\PointsSchemaListener` | Ajoute les tables au schéma que génère l'ORM |
| `Infrastructure\Doctrine\DbalLedger` | Le registre sur Doctrine DBAL |
| `Infrastructure\Console\ExpireCommand` | `tilttek:points:expire` |
| `Infrastructure\Console\HistoryCommand` | `tilttek:points:history` |
| `Infrastructure\Privacy\PointsPrivacy` | L'exportation et l'effacement d'un membre pour `phil/privacy-bundle` |
