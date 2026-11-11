<!-- phil-dev source-sha256=f1ec2f7c0dd5024c24f322cd718a6f91a1b32d05b9d68e51d1ca0c455a88b97b -->
# Limites

`Limits` borne ce que peut demander une expression, contre les abus. Ce qui dépasse une limite est refusé par
`LimitExceeded`, jamais tronqué.

| Limite | Valeur par défaut | Vérifiée |
|---|---|---|
| `dice` | 100 | Les dés qu'un lot lance ou énumère et les éléments qu'il tire des listes de textes, avant les relances et les explosions, à l'analyse et au lancer — chaque branche comptée à l'analyse, la branche retenue au lancer, ce que répète `repeat()` une fois pour chaque passage ; un nombre lancé d'abord est compté une fois lancé |
| `sides` | 1000 | Les faces de chaque dé numéroté et celles d'un dé sur un intervalle, aux mêmes moments ; des faces lancées d'abord une fois lancées |
| `explosions` | 100 | Les explosions d'un lancer, de toute sorte, et les dés des dés tous identiques, sur tout le lot, à mesure qu'elles se produisent |
| `expressions` | 10 | Les instructions d'un lot |
| `length` | 256 | Les caractères d'une entrée, avant son analyse |
| `rerolls` | 100 | Les relances d'un lancer — répétées, uniques ou de dés uniques —, sur tout le lot, à mesure qu'elles se produisent |
| `repetitions` | 100 | Les répétitions de `repeat()`, sur tout le lot — celles d'un `repeat()` dans un `repeat()` une fois pour chaque passage —, à l'analyse et à mesure qu'elles se produisent ; un nombre lancé d'abord est compté une fois lancé |
| `text` | 4096 | Les caractères d'un texte que donne une phrase, une fois ses marqueurs remplacés |

Chaque limite vaut au moins 1. Un nombre qui dépasse la plage d'un entier est pris comme le plus grand entier, et
refusé. Passez les mêmes `Limits` à l'analyseur et au lanceur — `DiceRoller` le fait — pour qu'un lot construit dans le
code soit tenu aux mêmes bornes qu'un lot tapé par une personne.
