<!-- phil-dev source-sha256=e802f0094c2232ae050c6f177e1ade34459a9f7fc9e0518053a1829a69126622 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\KudosException` : attrapez celle-ci pour les attraper toutes.

| Exception | Levée quand | Message |
|---|---|---|
| `KudosRefused` | Un remerciement ne peut pas être donné ; `refusal` dit pourquoi | `A member cannot thank themselves.`, `This member does not take thanks.`, `The giver has given all the thanks of their day.`, `The giver has thanked this member as often as the period allows.` |
| `UnknownMessage` | Le message n'est pas dans la liste de l'application | `"…" is not one of the messages thanks are given with.` |
| `InvalidKudos` | Un message, une liste, des limites, une référence d'objet ou une page hors bornes ; une activité lue comme un remerciement qui ne contient aucun message | Ce qui est hors bornes, et la borne |

`KudosRefused` étend `DomainException` ; `UnknownMessage` et `InvalidKudos` étendent `InvalidArgumentException`.

Les exceptions de `tilttek/activity` parviennent à l'appelant telles quelles : `IdempotencyConflict` quand une clé a été
donnée à d'autres remerciements, `InvalidActivity` quand une clé fournie par l'application n'est pas une clé
d'idempotence.
