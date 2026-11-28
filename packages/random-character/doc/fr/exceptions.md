<!-- phil-dev source-sha256=4febb7a91367310e02c724e935cd6f8fa69d8297d3bc018718e4fbaa234ecd8f -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\RandomCharacterException` : attrapez-la pour les attraper
toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidRules` | `InvalidArgumentException` | Des règles, des statistiques ou un champ enfreignent une règle à la construction ; des règles tirées alors que `check()` trouve des problèmes, chacun listé. |
| `InvalidDocument` | `InvalidArgumentException` | Un document ne peut pas être lu ; le message commence par l'emplacement du champ. |
| `InvalidSeed` | `InvalidArgumentException` | Une graine est vide, n'est pas en UTF-8 ou dépasse 256 caractères. |
| `InvalidEdit` | `InvalidArgumentException` | On demande à un personnage, ou on lui donne, une valeur qu'il n'a pas, ou une valeur hors des bornes. |
| `UnknownRules` | `OutOfBoundsException` | Un catalogue ne contient aucun jeu de règles de cette clé. |
| `RollFailed` | `RuntimeException` | Une statistique sort de ses bornes, un lancer ne donne aucun nombre ou dépasse ses limites, un générateur échoue ou donne trop peu de résultats différents, ou la valeur d'un champ ne peut pas nommer un générateur. |

Quelques messages :

```text
The creation rules "lanterns.wanderer" cannot be rolled:
- The attribute "luck" is not one of the system "lanterns".
- The field "name" draws from the generator "lanterns.names", which is unknown.
The stats gave 7 to the attribute "grit", outside its bounds of 1 to 6.
The field "name" names its generator by "{lineage}", which gave no text.
The generator "lanterns.trait" gave 1 of the 2 different results the field "traits" asks for.
The field "name" names its generator by the field "lineage", which is not before it.
rules[0].fields[3].count: a whole number is expected.
```

Une `RollFailed` causée par `tilttek/generator` ou `tilttek/dice` garde leur exception comme exception précédente.
