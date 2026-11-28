<!-- phil-dev source-sha256=e3cd4a0097f2f4410d60004f1b23abbb649e2176d852e5c59d778e5045eac8c5 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/tavern-games` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Contract\TavernGame` et `Turns` : des jeux au tour par tour de `tilttek/game-engine` qui gardent leurs propres tours
  dans leurs données et acceptent l'expiration d'un tour, `tavern.timeout`, jouée par la table.
- `TavernTables` : des tables ouvertes et jouées, le minuteur des tours averti du tour qui commence ou de la fin de la
  partie, l'expiration d'un tour jouée seulement quand la place joue encore après le même nombre de coups, les vues,
  l'état complet et les fins de partie.
- `Contract\TurnTimer`, `Store\NullTurnTimer` et `Store\InMemoryTurnTimer` : l'horloge facultative des tours, aucune
  horloge par défaut, et les tours en cours en mémoire pour les tests.
- `Ending` et `Tone` : une victoire, seule ou partagée, célébrée ; toute autre fin annoncée avec bienveillance.
- `TavernGames` : les jeux de taverne d'une application, par nom, et les mêmes jeux pour les `Tables` du moteur.
- `DiceCup` : un à dix dés à six faces lancés par `tilttek/dice` avec le hasard d'un coup.
- `Odds\Estimator`, `Odds\Estimate`, `Odds\Trial` et `Contract\Simulation` : les chances d'une position sur 1 à 100 000
  fins simulées, les victoires, les égalités et les défaites comptées à part avec le nombre de fins.
- `Game\Pig` : le jeu de dés où l'on pousse sa chance, jusqu'à une cible de 20 à 200 points.
- `Game\ShipCaptainCrew` et `Odds\CargoChase` : le jeu de dés du navire, du capitaine, de l'équipage et de la cargaison,
  avec des victoires partagées sur des cargaisons égales, et les chances du tour de la place qui joue.
- `Game\CrazyEights` : le jeu de cartes où l'on se défausse, les huit valant n'importe quelle carte, ses cartes gardées
  par `tilttek/card-deck` et mélangées à partir d'un germe tiré de celui de la table, chaque place ne voyant que sa
  propre main ; les parties bloquées et trop longues gagnées par le moins de cartes.
- Des exceptions sous une seule interface `TavernGamesException` : `InvalidTavernGame`.
- Une cible de fuzzing des coups de Crazy Eights et un banc d'essai de sa relecture.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
