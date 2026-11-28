<!-- phil-dev source-sha256=850428e1307319b6427ec2c4fef387d6e897dee4b5a76a4cb05c557eaa94af1f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/play-log` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Play` : une partie jouée une fois, son jeu du catalogue, le membre qui l'a notée, de 1 à 100 places, son résultat et,
  quand ils sont connus, sa date, ses minutes, son groupe et son lieu, bornés et vérifiés ; une date peut rester
  inconnue.
- `Seat` : un membre ou un joueur anonyme, avec un score et s'il a gagné ; `Seat::members()` et `Seat::guests()`.
- `Result` : compétitif, avec les gagnants marqués sur les places, une victoire coopérative, une défaite coopérative, ou
  inachevé.
- `PlayLog` : noter, corriger et supprimer une partie par le membre qui l'a notée, sur un jeu du catalogue qui n'est pas
  masqué ; il ne garde aucun état entre les appels.
- Les places liées à leurs membres seulement une fois confirmées : celle de la personne qui note tout de suite, celle
  d'un autre membre par `PlayLog::confirm()` ou par un `Contract\PlayerConsent`, rendue anonyme par `PlayLog::decline()`
  ; `Play::seenBy()` montre aux autres lecteurs les places non confirmées comme anonymes.
- `PlayLog::forget()` : un membre oublié par lots, les parties qu'il a notées supprimées et ses autres places rendues
  anonymes.
- `PlayQuery` et `PlayLog::plays()` : les parties d'un membre, en attente d'un membre, notées par un membre, ou d'un
  groupe, par jeu et par période, les plus récentes en premier, celles dont la date est inconnue en dernier.
- `PlayStatistics`, `Statistics`, `GameStatistics` et `PlayerStatistics` : les parties, les victoires, les parties
  inachevées, les minutes, les jeux, les membres avec qui on a joué et l'indice h d'un membre ou d'un groupe, calculés à
  la demande à partir d'au plus 10 000 parties.
- Les événements `PlayRecorded`, `PlayCorrected`, `PlayDeleted`, `SeatLinked`, `SeatPending` et `SeatUnlinked`, par
  identifiants seulement, annoncés une fois à un `Contract\PlayObserver`.
- `Contract\PlayStore`, gardé à une version et lu par requête, et `Store\InMemoryPlayStore` pour les tests.
- Les exceptions `InvalidPlay`, `PlayRefused`, `PlayNotFound` et `PlayConflict`, sous `PlayLogException`.
- Les adaptateurs du journal des parties d'une soirée de jeux et de l'historique des parties du catalogue, documentés.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
