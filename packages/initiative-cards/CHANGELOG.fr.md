<!-- phil-dev source-sha256=291db8b9fbebddf8f5eab0b7fad30a0aee1d4175ce1c2a0f0b0552628cf827b9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/initiative-cards` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Encounter` et `Combatant` : une rencontre — son meneur de jeu, un paquet de `tilttek/card-deck`, sa pioche et sa
  défausse, jusqu'à 100 combattants dans l'ordre des tours, son round et sa version — et ses combattants, chacun avec un
  contrôleur, caché ou non, vaincu ou non, piochant une à trois cartes, bornés et vérifiés.
- `InitiativeTracker` : une rencontre commencée sur un paquet mélangé, changée par son meneur de jeu ; il ne garde aucun
  état entre les appels.
- `InitiativeTracker::deal()` : un round distribué, les cartes en main défaussées, la défausse remélangée dans la pioche
  après une carte qui fait remélanger ou quand la pioche manque de cartes, la meilleure des cartes de chaque combattant
  gardée, les tours ordonnés par les cartes, les égalités dans leur ordre précédent et les vaincus en dernier.
- `InitiativeTracker::endTurn()` : le tour du combattant qui le tient terminé par son contrôleur ou par le meneur de
  jeu, qui peut nommer le suivant parmi ceux qui n'ont pas agi.
- `InitiativeTracker::join()` et `leave()` : un combattant qui rejoint un round avec ses cartes piochées, attendant
  après le tour en cours selon sa carte, ou qui le quitte avec sa carte défaussée.
- `InitiativeTracker::defeat()` et `revive()` : un combattant vaincu estompé et sauté, qui se relève dans ce round ou le
  suivant selon la place de sa carte.
- `InitiativeTracker::reveal()` : un combattant caché aux joueurs ou révélé.
- `InitiativeTracker::end()` : une rencontre close.
- `View\InitiativeView` et `View\Slot` : l'ordre des tours qu'un membre peut voir — chaque combattant pour le meneur de
  jeu, les combattants cachés laissés de côté pour tout autre — en données simples.
- `StandardRanking` et `Contract\CardRanking` : le paquet de 52 cartes et ses jokers, un joker en premier qui fait
  remélanger, un ordre des couleurs ; un autre paquet classé par l'application.
- Les événements `EncounterStarted`, `RoundDealt`, `DeckReshuffled`, `TurnStarted`, `TurnEnded`, `RoundEnded`,
  `CombatantJoined`, `CombatantLeft`, `CombatantDefeated`, `CombatantRevived`, `CombatantRevealed`, `CombatantHidden` et
  `EncounterEnded`, par identifiants seulement, annoncés une fois à un `Contract\InitiativeObserver`.
- `Contract\EncounterStore`, gardé à une version, et `Store\InMemoryEncounterStore` pour les tests.
- Les exceptions `InvalidEncounter`, `InitiativeRefused`, `EncounterNotFound` et `EncounterConflict`, sous
  `InitiativeException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
