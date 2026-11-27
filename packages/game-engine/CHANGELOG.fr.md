<!-- phil-dev source-sha256=26cdf5730d81bcb040c9af412dca8fc8abaab78df67e6630f1df98ba5d0881ef -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/game-engine` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Contract\Game`, `GameDefinition`, `Phase`, `Transition` et `TurnOrder` : les règles d'un jeu derrière un seul contrat
  pur — mise en place, coups permis, coups joués, phases entamées, fin et vue d'une place —, et sa déclaration : des
  phases avec un ordre de tour libre, séquentiel, par initiative ou simultané et un nombre facultatif de coups qui
  termine un tour, les transitions entre elles, la phase par laquelle il commence, de 1 à 64 places, ses options, les
  coups qu'une place joue hors de son tour, si le dernier coup peut être repris, la version de ses règles, les
  préférences qu'il offre à chaque joueur et les statistiques qu'il rapporte à sa fin.
- `Phases` : les phases comme machine à états de Symfony Workflow sans répartiteur d'événements, pour qu'une relecture
  prenne les mêmes transitions, vérifiée par son `StateMachineValidator`, avec les transitions permises depuis une phase
  et l'organigramme Mermaid de la machine.
- `Option`, `OptionKind` et `TableOptions` : des options qui sont des booléens, des nombres entiers entre des bornes ou
  des choix parmi des textes, avec leurs valeurs par défaut, résolues pour chaque table par l'OptionsResolver de
  Symfony, et relues depuis un cache.
- `Preferences` : les préférences d'un joueur, des options d'une place résolues par le même OptionsResolver et données à
  la vue de la place, jamais aux règles ni au journal.
- `Statistic`, `StatisticScope` et `Statistics` : les statistiques qu'un jeu déclare pour la table ou pour chaque place,
  que son issue rapporte en nombres entiers à sa fin, vérifiées par le moteur après chaque coup.
- `Engine`, `Table`, `GameState`, `Turn` et `Outcome` : des tables ouvertes avec leurs places et un germe de 256 bits
  tiré de la source sûre du système, des coups vérifiés contre la place et son tour et demandés au jeu, des tours
  terminés par une place ou par le nombre de coups d'une phase, des manches simultanées, l'initiative fusionnée et
  ordonnée de la plus haute à la plus basse avec l'ordre de la table pour les égalités, des reports après une place
  ultérieure, des changements de phase, des fins qui ferment la table avec leurs statistiques, et la vue d'une place
  façonnée par les préférences de son joueur ; `GameState::toArray()` et `GameState::fromArray()` écrivent et relisent
  un état en valeurs simples, chaque champ vérifié, pour un cache partagé.
- `GameLog`, `LoggedMove`, `Move` et `Randomness` : le journal d'une table comme sa vérité — jeu, version, germe,
  places, options et jusqu'à 100 000 coups numérotés, chacun avec l'indication d'un tirage au hasard ou non —, écrit et
  relu en valeurs simples ; des coups avec un nom typé et une charge utile de valeurs simples d'au plus 8 niveaux et 16
  384 octets ; le hasard tiré par `Random\Engine\Xoshiro256StarStar` à partir du germe et de la place de chaque étape
  dans le journal ; une relecture qui reconstruit l'état et refuse un journal d'une autre version, un coup qui ne
  s'applique plus, ou un coup qui tire différemment ; le dernier coup repris quand le jeu le permet, que la partie n'est
  pas finie et que le coup n'a rien révélé ; l'engagement envers le germe, son empreinte SHA-256, qui peut être montrée
  aux joueurs à l'ouverture, et une relecture vérifiée contre lui.
- `LogHeader`, `Step`, `Engine::setup()`, `Engine::advance()`, `Engine::step()` et `Engine::checkUndo()` : un journal lu
  sans ses coups, avec une révision qui compte chaque changement et ne descend jamais, et un état avancé par les coups
  qui le suivent, lus à mesure qu'ils arrivent.
- `Tables`, `Games`, `Contract\LogStore`, `Contract\StateCache`, `Snapshot`, `Contract\TablePublisher` et
  `TableUpdate` : les tables d'une application ouvertes, jouées, reprises et lues ; les journaux complétés et repris
  avec le nombre de coups et la révision que l'appelant a lus, contre les changements concurrents ; les coups lus
  fenêtre par fenêtre ; le journal d'une table finie montré, germe compris, seulement quand c'est celui de son état
  fini ; l'état mis en cache avec la révision et le germe à partir desquels il a été construit, jamais servi pour
  d'autres, jamais écrit par-dessus une révision ultérieure, et avancé par les coups joués depuis ; chaque table lue au
  plus une fois par requête, les états gardés tant que l'en-tête ne dit aucun changement et relâchés par `reset()` ;
  chaque changement publié avec des identifiants et des compteurs seulement ; des stockages en mémoire, réinitialisés
  entre deux requêtes d'un worker, un cache qui ne garde rien et un diffuseur qui ne publie rien.
- `Scene\RoleplayingScene` : les phases d'une scène de jeu de rôle — exploration, rencontre par initiative, dénouement
  —, menée par la première place à moins que la table désactive l'option, avec des actions, des notes, des rencontres
  comptées et une fin qui les rapporte comme statistiques.
- Des exceptions sous une seule interface `GameEngineException` : `InvalidGame`, `IllegalMove`, `GameOver`,
  `GameInProgress`, `ConcurrentMove` et `UnknownTable`.
- Des cibles de fuzzing des journaux, des états et des instantanés relus, et des bancs d'essai d'un coup, de relectures
  de 100 et de 1 000 coups et de la relecture d'un journal.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
