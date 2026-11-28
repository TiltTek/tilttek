<!-- phil-dev source-sha256=f02da3f88252dd38d30bcd2861ff7a9d6c5e31debf96e676d369ebdd92f48344 -->
# Rencontres, rondes et tours

## Une rencontre

`InitiativeTracker::start()` ouvre une rencontre : son identifiant, son maître de jeu, un jeu de `tilttek/card-deck` et
les combattants, jusqu'à 100, dans l'ordre où ils se joignent. Le jeu est mélangé dans la pioche ; aucune carte n'est
encore distribuée. Chaque face du jeu doit être connue du classement, sinon la rencontre est refusée.

Un `Combatant` a un identifiant, unique dans la rencontre, et un contrôleur : le membre qui termine ses tours, un joueur
pour son personnage, le maître de jeu pour les autres. Il peut être caché des joueurs, et piger une à trois cartes à
chaque ronde. Les identifiants comptent de 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, commencent par une lettre,
et ne sont jamais un nom ni une adresse.

Le maître de jeu — et lui seul — distribue, ajoute, retire, vainc, relève, cache, révèle et termine. Un joueur termine
les tours des combattants qu'il contrôle.

## Distribuer une ronde

`deal()` termine la ronde en cours et distribue la suivante :

1. les cartes que détiennent les combattants vont à la défausse ;
2. la défausse est remise dans la pioche quand une carte qui remélange a été distribuée durant la ronde qui vient de
   finir, ou quand la pioche contient moins de cartes que les combattants n'en pigent ;
3. chaque combattant non vaincu pige ses cartes sur le dessus de la pile et garde la meilleure ; les autres sont
   défaussées ;
4. les combattants sont ordonnés selon leurs cartes. Ceux qui sont à égalité gardent leur ordre de la ronde précédente,
   ou de leur arrivée ; les vaincus, qui ne détiennent aucune carte, viennent en dernier.

Quand même le jeu entier est trop petit pour les pioches des combattants, la distribution est refusée et rien ne change.
La ronde se compte à partir de 1 ; `RoundDealt` est informé, puis `TurnStarted` pour le premier combattant.

## Le classement

`StandardRanking` ordonne les faces de `StandardDeck` : un joker (`JK`) d'abord, puis l'as (`A`), le roi, la dame, le
valet, le dix jusqu'au deux ; deux cartes de même rang suivent l'ordre des couleurs, pique (`S`), cœur (`H`), carreau
(`D`), puis trèfle (`C`) par défaut. Un autre ordre se donne comme une liste des quatre couleurs :

```php
new StandardRanking(['C', 'D', 'H', 'S']);   // clubs first
new StandardRanking(jokerShuffle: false);     // a joker acts first, and shuffles nothing
```

Un jeu propre à l'application — des cartes à l'art d'un jeu, un tarot — vient avec un `Contract\CardRanking`, décrit dans
les [points d'extension](extension-points.md#le-classement). Le classement lit la première face de chaque carte.

## Les tours

Le combattant qui détient le tour, `Encounter::current()`, est le premier, dans l'ordre, qui détient une carte, n'a pas
agi et n'est pas vaincu. `endTurn()` le termine, par son contrôleur ou le maître de jeu ; le tour passe à la carte
suivante. Terminer le tour d'un combattant qui ne le détient pas est refusé, pour que deux clics ne terminent jamais deux
tours.

Celui qui termine le tour peut nommer le suivant, parmi les combattants qui n'ont pas agi et ne sont pas vaincus : le
combattant nommé passe tout de suite après celui qui termine, et l'ordre des autres reste. Quand tous les combattants
debout ont agi, `RoundEnded` est informé, et le maître de jeu distribue la ronde suivante.

## Arrivée et départ

`join()` ajoute un combattant. Avant la première ronde, il vient en dernier. En cours de ronde, il pige ses cartes
aussitôt — la défausse est remise dans la pioche quand celle-ci est trop courte — et attend parmi ceux qui n'ont pas
agi, après celui qui détient le tour, selon l'ordre des cartes. Un combattant qui se joint quand la ronde est terminée
prend le tour. Un combattant qui se joint vaincu ne détient aucune carte avant la prochaine distribution.

`leave()` retire un combattant ; sa carte va à la défausse, et s'il détenait le tour, le tour passe au suivant.

## Défaite et relève

`defeat()` marque un combattant vaincu : il reste dans l'ordre, estompé, garde sa carte pour la ronde, et ses tours sont
sautés ; aux rondes suivantes, il ne pige rien. S'il détenait le tour, le tour passe au suivant.

`revive()` le remet debout. Sa carte, s'il en détient une, garde sa place : quand un combattant après lui a agi ou
détient le tour, le tour a dépassé sa place, et il attend la prochaine ronde ; sinon son tour vient durant celle-ci.

## Combattants cachés et vues

`reveal()` montre un combattant caché aux joueurs, et `reveal(..., false)` en cache un. `view()` donne ce qu'un membre
peut voir, sous la forme d'une `InitiativeView` dont le `toArray()` lui est envoyé :

- la ronde, les cartes restantes dans la pioche, si la rencontre est terminée, et la version lue ;
- un `Slot` par combattant, dans l'ordre des tours : son identifiant, la face de sa carte ou null, s'il a agi, s'il est
  vaincu et s'il détient le tour.

La vue du maître de jeu contient chaque combattant, chacun marqué caché ou non ; celle de n'importe qui d'autre, ou d'un
spectateur, omet les cachés. Les événements `TurnStarted` et `CombatantJoined` disent quand le combattant est caché, pour
qu'une application ne l'annonce pas aux joueurs.

`end()` clôt la rencontre : elle ne change plus, et n'a plus de tour courant.
