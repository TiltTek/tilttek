<!-- phil-dev source-sha256=6657573c5d577c6b80899dc82af6005aecb3d36e02de6bd1be63fa9fc5ae42a1 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\MathTradeException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Levée quand | Message |
|---|---|---|
| `InvalidTradeList` | Un identifiant hors des bornes, deux participants ou deux offres de même identifiant, un participant sans offre, un souhait pour elle-même, pour l'offre de personne, pour une offre de son propre participant ou pour une même offre deux fois, un groupe de moins de deux, des limites hors de leurs bornes, un document JSON qui n'est pas une liste d'échange du format, une offre cherchée que personne ne fait | Ce qui ne va pas, en nommant le participant, l'offre ou la clé |
| `LimitExceeded` | Une liste ou un document JSON plus grand que ne le permettent ses `Limits` | La limite qui a été dépassée, comme `The trade list holds more than 5000 offers.` |
| `InvalidSeed` | Un germe vide, de plus de 256 octets, qui n'est pas en UTF-8, ou qui contient un caractère de contrôle | `A seed is 1 to 256 bytes of UTF-8 without a control character.` |

`InvalidTradeList` et `InvalidSeed` étendent `InvalidArgumentException` ; `LimitExceeded` étend `LengthException`.

Les messages nomment les identifiants de l'application, que connaît le participant qui a écrit la liste : une
application peut les lui montrer tels quels.
