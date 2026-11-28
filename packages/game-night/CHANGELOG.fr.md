<!-- phil-dev source-sha256=14f1cfcc54b18f4b560c85511d0223b5976f4fecbed4fe0e510cf78c0c169bdd -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/game-night` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `GameNight` : une soirée d'un club ou d'une boutique, quand et où, son hôte, ses tables, ses inscriptions, sa liste
  d'attente, ses jeux proposés et ses votes, vérifiée en entier à chaque changement.
- `Table` : une table et ses places, de 1 à 30, avec le jeu prévu à cette table et le membre qui l'anime.
- `Registration` : un membre inscrit, seul ou avec un invité qui prend une place, confirmé ou en attente.
- La liste d'attente, servie dans l'ordre d'inscription des membres : les places libérées vont au premier membre en
  attente dont les places conviennent, et personne ne le dépasse.
- `GameNights` : la planification, les tables, la publication, les inscriptions, les désistements qui libèrent des
  places, les places assises, le vote et l'annulation ; les inscriptions, les places et les votes ferment quand la
  soirée commence.
- Les événements `NightPublished`, `NightFull`, `NightCancelled` et `SeatOffered`, par identifiants seulement, annoncés
  une fois à un `Contract\NightObserver` ; aucun rappel n'est envoyé.
- `Choice\GameChooser` et `Choice\Criteria` : des jeux suggérés pour une table ou toute la soirée, parmi les jeux que
  les membres peuvent apporter, lus par `Contract\ShelfReader`, et ceux que garde le club ou la boutique, selon les
  joueurs et les minutes disponibles.
- `Choice\Suggestion` : un jeu suggéré, avec le choix des membres du catalogue pour ce nombre de joueurs et le nombre de
  votes sur lequel il repose, et le lien vers sa page sur BoardGameGeek.
- `Vote\VoteResult` : un vote par membre inscrit, compté et expliqué, une égalité étant laissée à l'hôte.
- `NightPlays` et `Contract\PlayJournal` : les jeux joués lors d'une soirée, envoyés au journal des parties.
- `Contract\NightStore`, gardé à une version, et `Store\InMemoryNightStore` pour les tests.
- Les exceptions `InvalidNight`, `NightRefused`, `NightConflict` et `NightNotFound`, sous `GameNightException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
