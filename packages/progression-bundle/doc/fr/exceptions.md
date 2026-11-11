<!-- phil-dev source-sha256=0eb026ca3d19be4e96de452a94a6fd277f6df60403862cd0661b4990c7efc96a -->
# Exceptions

| Exception | Levée quand |
|---|---|
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | Une option est du mauvais genre ou hors limites |
| `Tilttek\Progression\Exception\InvalidProgression` | Une piste, une courbe, un jalon, une série ou un fuseau horaire est incohérent, à la compilation du conteneur |
| `Tilttek\Progression\Exception\UnknownTrack`, `UnknownStreak` | Un gabarit ou un service demande une piste ou une série qui n'existe pas |
| `UnexpectedValueException` | Une ligne d'une table contient une valeur d'un autre type que sa colonne : une base modifiée à la main |
