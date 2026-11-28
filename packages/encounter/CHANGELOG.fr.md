<!-- phil-dev source-sha256=af6eb22b40b799d59c85268bf332eca67a08b726508ab8ddbb7f551b46c59607 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/encounter` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Participant` et `Kind` : une ligne d'une rencontre — un personnage, ou 1 à 50 exemplaires d'un adversaire ou d'un
  allié d'un même modèle — avec sa cote telle que le système de jeu l'écrit, jusqu'à 16 caractères, le membre qui le
  contrôle, si les joueurs le voient, un bonus d'initiative de -99 à 99 et l'identifiant de la fiche de l'application ;
  chaque exemplaire est un combattant à part entière, de `wolves.1` à `wolves.n`.
- `Encounter` : son meneur de jeu, son système de jeu, un titre d'une ligne d'au plus 200 caractères, jusqu'à 100
  participants et 100 combattants dans l'ordre que le meneur de jeu a fixé, chaque identifiant de combattant une fois,
  si elle a été lancée, sa révision.
- `EncounterPlanner::prepare()`, `retitle()`, `add()`, `change()`, `move()` et `remove()` : une rencontre ouverte pour
  un système de jeu qui a des règles, et changée par son meneur de jeu seul ; un participant placé en dernier, ou juste
  avant ou après un autre ; un changement qui ne change rien ne garde rien.
- `Contract\DifficultyRules` et `Difficulty\Assessment`, `Difficulty\Band` : le coût des adversaires, jusqu'à 12
  tranches avec leurs seuils dans l'ordre, la tranche atteinte, et ce qui reste avant chaque tranche.
- `Difficulty\BudgetRules` et `Difficulty\Multiplier` : des montants selon le niveau des personnages, des coûts selon le
  défi des adversaires, multipliés selon le nombre d'adversaires ; jusqu'à 1 000 cotes par table et 20 multiplicateurs.
- `Difficulty\LevelRules` : des budgets pour un groupe d'une taille standard, ajustés selon sa taille, et des coûts
  selon le nombre de niveaux dont un adversaire dépasse le groupe, de -50 à 50.
- `Difficulty\Declaration` : l'une ou l'autre sorte de règles lue depuis des données simples, chaque clé, type et borne
  vérifiés, soumise au fuzzing ; et depuis les nombres de difficulté `encounter` d'un système de `tilttek/rpg-systems`
  par `fromSystem()` — méthode `budget` avec `budgets`, `costs` et `multipliers` facultatif, méthode `level` avec
  `party-size`, `budgets`, `adjustments` et `costs` —, une table inconnue ou manquante étant refusée.
- `Rulebooks` et `EncounterPlanner::assess()` : les règles d'au plus 1 000 systèmes de jeu, trouvées par clé ou par le
  `GameSystem` de `tilttek/rpg-systems`, lues par `Rulebooks::fromSystems()` depuis les nombres de difficulté de chaque
  système, avec les règles que l'application apporte à leur place, et la difficulté d'une rencontre selon les règles de
  son système.
- `EncounterPlanner::launch()`, `roster()`, `Initiative\InitiativeRoster`, `Initiative\RosterEntry` et
  `Contract\InitiativeHandOff` : la rencontre gardée lancée, qui ne change plus, et ses combattants remis dans l'ordre à
  n'importe quel suivi d'initiative, sans dépendre d'aucun.
- `EncounterPlanner::forget()` : une rencontre retirée du stockage.
- Les événements `EncounterPrepared`, `EncounterRetitled`, `ParticipantAdded`, `ParticipantChanged`, `ParticipantMoved`,
  `ParticipantRemoved`, `EncounterLaunched` et `EncounterForgotten`, par identifiants seulement, annoncés une fois à un
  `Contract\EncounterObserver`.
- `Contract\EncounterStore`, qui garde chaque rencontre à une révision, et `Store\InMemoryEncounterStore` pour les
  tests.
- Les exceptions `InvalidEncounter`, `EncounterRefused`, `EncounterNotFound`, `EncounterConflict` et `UnknownSystem`,
  sous `EncounterException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
