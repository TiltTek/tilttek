<!-- phil-dev source-sha256=3176fcc1c202e2d51bc447ab12d9e10d553c4e0643e8f5ba617c24e13141a3e9 -->
# Exceptions

Le bundle lève les exceptions de `tilttek/exchange` et de `tilttek/points`. Ses propres cas :

| Exception | Levée quand |
|---|---|
| `Tilttek\Exchange\Exception\InvalidExchange` | Le conteneur est compilé avec une récompense, une durée ou un fuseau horaire incohérent, un catalogue en base de données sans le magasin `doctrine`, des récompenses configurées avec le catalogue `doctrine`, un délai sans `member_since`, ou des échanges sans inventaire |
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | Une clé de `tilttek_exchange` contient une valeur du mauvais type ou hors limites |
| `UnexpectedValueException` | Une colonne relue contient ce qu'elle ne peut pas contenir : une colonne de texte, un nombre ; les conditions d'une récompense, autre chose qu'une liste JSON de chaînes |
| `LogicException` | La ligne de verrou d'une récompense n'est ni écrite ni trouvée |
