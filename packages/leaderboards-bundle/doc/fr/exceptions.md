<!-- phil-dev source-sha256=0241a471de3960400fc6bc8430b3346c998ffbc7326627214162eb4e625c9bd9 -->
# Exceptions

| Exception | Levée quand |
|---|---|
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | Une option est du mauvais genre ou hors des bornes |
| `Tilttek\Leaderboards\Exception\InvalidLeaderboard` | Un classement, une saison ou un fuseau horaire sont incohérents, à la compilation du conteneur ; une limite ou un cercle que donne un gabarit est refusé |
| `Tilttek\Activity\Exception\InvalidActivity` | Un motif de verbe ou une condition de contexte d'un classement est refusé, à la compilation du conteneur |
| `Tilttek\Leaderboards\Exception\UnknownBoard`, `UnknownPeriod` | Un gabarit, une commande ou un service demande un classement ou une période qui n'existe pas |
| `Tilttek\Leaderboards\Exception\PeriodNotOver` | Un instantané est demandé pour une période qui n'est pas terminée |
| `UnexpectedValueException` | Une ligne d'une table contient une valeur d'un autre type que sa colonne : une base de données modifiée à la main |
