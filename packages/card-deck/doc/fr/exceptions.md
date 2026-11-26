<!-- phil-dev source-sha256=9855710037fd2d1f1687a588ae82bf957cbfe3393df65726e4070caba030ba5e -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\CardDeck\Exception\CardDeckException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidDefinition` | Un identifiant, une carte, un paquet, un geste ou une partie publiée est refusé à sa création ou à sa lecture : un nom hors de son motif, un paquet hors de ses bornes, des données qui ne se décodent pas, une graine du client trop longue | `A deck holds 1 to 1024 cards.` |
| `IllegalGesture` | L'état de la partie refuse un geste : une zone vide ou non ouverte, une carte que le geste ne peut atteindre, une partie non démarrée ou terminée, trop de zones ou de gestes ; l'état reste tel qu'il était | `The zone "draw" is empty.` |
| `UnknownGame` | Le magasin ne contient aucune partie de cet identifiant | `The store holds no game "g-7f3a".` |
| `ConcurrentChange` | La partie a changé depuis la version sur laquelle le changement a été fait | `The game "g-7f3a" is at version 9, not 8.` |
