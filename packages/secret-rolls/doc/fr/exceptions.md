<!-- phil-dev source-sha256=da3a20ca7ed6041bfd4748399e28d51d316db482e7ccde2d15fc1bde198ae02a -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\SecretRolls\Exception\SecretRollsException`. `roll()` laisse
aussi passer les exceptions de `tilttek/dice` : `InvalidExpression`, `LimitExceeded` et `CannotRoll`.

| Exception | Lancée quand | Message, par exemple |
|---|---|---|
| `InvalidSecretRoll` | Une scène, un lanceur ou un contexte est refusé, ou une ouverture, un jet caché, une entrée du journal ou un journal ne se lit pas | `A scene is 1 to 128 letters, digits, dots, colons, underscores or hyphens.` |
| `SecretRollRefused` | Un jet dans une scène close ou pleine, la révélation d'un jet que la scène n'a jamais engagé, déjà révélé ou plus scellé, ou une seconde clôture | `The roll 3f2a… was revealed already; a roll is revealed once.` |
| `JournalConflict` | Une entrée ne vient pas à la position qui suit la dernière de sa scène : deux écrivains ont ajouté en même temps | `The journal of the scene cave expects position 4; an entry came at position 3.` |

`InvalidSecretRoll` étend `InvalidArgumentException`, `SecretRollRefused` étend `DomainException` et `JournalConflict`
étend `RuntimeException`.
