<!-- phil-dev source-sha256=35dc24d76606edf3d50dfb974b67fcba2de60beec462d86b406a8951543131c1 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Dice\Exception\DiceException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidExpression` | Une entrée ou une commande de DiceParser ne s'analyse pas, ou nomme une macro absente ; `input` et `position` disent où, `reason` dit pourquoi | `Unexpected "x" at position 3 of "2d6x".` |
| `LimitExceeded` | Une entrée ou un lancer dépasse une limite | `The dice of a roll are limited to 100; this asks for 120.` |
| `CannotRoll` | Un dé est inconnu, des faces sans valeur entrent dans l'arithmétique, des dés lancés ne peuvent pas être lancés, `@` n'a pas d'ensemble avant lui, un texte ou une liste se trouve là où un nombre est calculé, un élément ou une instruction est absent, un nombre lancé ne peut pas servir à une liste ou à `repeat()`, une division par zéro, un dépassement de capacité | `No die named "bone" is declared.` |
| `InvalidDefinition` | Une face, un dé, un modificateur, un validateur, une fonction, une notation, des limites, un germe ou un engagement est invalide ; un dialecte inconnu | `The face "red" is told by its colour alone: give it a shape or an image too.` |
| `UntranslatableExpression` | Le texte d'une traduction incomplète est demandé | `avrae cannot write 4dF.` |
