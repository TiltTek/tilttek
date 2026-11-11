<!-- phil-dev source-sha256=c99b57384cf899c2cd0a467ffb4f0ec7dbed8dd2eca2581eaeccaf3d0c506dbc -->
# Exceptions

Le bundle lève les exceptions de la bibliothèque, qui implémentent `Tilttek\Dice\Exception\DiceException`, et deux
exceptions qui lui sont propres :

| Exception | Levée quand | Message |
|---|---|---|
| `Application\Exception\UnknownRoll` | `replay()` nomme un lancer que le journal ne contient pas | `No recorded roll has the identifier "…".` |
| `Application\Exception\InvalidMacro` | `define()` reçoit un nom qui n'en est pas un, ou un niveau pour lequel les rayons ne donnent aucun propriétaire | `The shelves give no owner for the level "member".` |
| `Tilttek\Dice\Exception\InvalidExpression` | Une entrée ne s'analyse pas, nomme une macro absente, ou une macro qui ne s'analyse pas ou se nomme elle-même | `No macro is named "sword" at position 2 of "1+$sword".` |
| `Tilttek\Dice\Exception\LimitExceeded` | Une entrée ou un lancer dépasse une limite ; une entrée développe trop de macros | `The macros an input expands are limited to 32; this asks for 35.` |
| `Tilttek\Dice\Exception\CannotRoll` | Un dé inconnu, des faces sans valeur dans un calcul, une division par zéro | `No die named "bone" is declared.` |
| `Tilttek\Dice\Exception\InvalidDefinition` | Une face, un dé ou une notation configurés sont invalides, à la compilation du conteneur ; un dialecte inconnu | `Two faces of the die "coin" share an identifier.` |

`UnknownRoll` et `InvalidMacro` implémentent aussi `DiceException`. Les filtres Twig attrapent les exceptions de la
bibliothèque : `dice_notation` rend l'entrée telle quelle, `dice_dialect` rend null.
