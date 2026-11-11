<!-- phil-dev source-sha256=2e252a41612481256ddbbbf59ad8e810490e10807ab5748c08c952b850639844 -->
# Dés

Les objets d'un ensemble de dés portent un `CollectibleDie` : leur nombre de faces, une couleur et une matière. Trois
sortes d'ensembles conviennent aux dés : un ensemble `base` que chaque membre reçoit à son arrivée, un ensemble
`earned` obtenu contre les points du site, et un ensemble `paid` acheté pour soi ou en cadeau.

`DiceShelf::dice(member)` énumère les exemplaires du membre qui sont des dés. `choose(member, copy)` fait de l'un d'eux
le dé que le membre lance pour son nombre de faces — un choix par nombre de faces. `chosen(member, sides)` renvoie son
objet, ou null pour les dés ordinaires ; `clear(member, sides)` y revient. Un dé échangé n'est plus lancé : le choix
revient aux dés ordinaires jusqu'à ce que le membre en choisisse un autre.

`tilttek/collections-bundle` change le dé choisi en l'apparence qu'affiche `tilttek/dice-bundle`. L'apparence ne change
jamais le hasard.
