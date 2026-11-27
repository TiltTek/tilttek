<!-- phil-dev source-sha256=914782a56a3544b54055853f33b5a5ed1b8ffe5cffeed3c32e1cf2ee31a34438 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/tournament` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Tournament`, `Stage` et `Scoring` : un tournoi de 2 à 512 participants connus par des identifiants opaques, de 1 à 4
  phases — rondes suisses, poules en une ou deux manches, un tableau à élimination directe en dernier —, une notation en
  nombres entiers, jusqu'à 8 critères de départage dans l'ordre, et un germe publié.
- `Tournaments` : le service qui lance un tournoi avec son premier ordre, prépare chaque ronde avec ses raisons avant sa
  publication, la publie telle que préparée ou installée autrement, déplace une ronde sans changer son numéro, donne le
  classement d'une phase ou d'une poule, le tableau, le programme d'un participant avec ses repos et les rondes prévues,
  et pourquoi deux participants se rencontrent dans une ronde ou non.
- Les résultats entrés une seule fois par table : le même résultat de nouveau ne change rien, un résultat contraire
  attend le `resolve()` d'un organisateur ; `correct()` change un résultat passé et renvoie comment le classement a
  bougé ; des points de mission à côté de la victoire.
- `withdraw()` : un participant qui se retire n'est plus apparié, ses tables restées sans résultat sont des forfaits, et
  il ne se qualifie jamais ; dans un tableau, une victoire par forfait.
- `Swiss\SwissPairer` : des rondes suisses par groupes de pointage selon les règles de la FIDE prises comme référence
  écrite — aucune revanche ni second exempt tant qu'un autre appariement les évite, moitié haute contre moitié basse,
  couleurs alternées, revanches avec les couleurs inversées —, exact jusqu'à 16 participants, une recherche bornée
  au-delà, déterministe.
- `RoundRobin\BergerTable` et `RoundRobin\Pools` : des poules selon les tables de Berger, un repos à chaque ronde d'une
  poule impaire montré dans le programme, une manche retour avec les couleurs inversées, et des poules remplies en
  serpentin selon la tête de série.
- `Elimination\Bracket` et `Elimination\BracketState` : un tableau à élimination directe selon les têtes de série, des
  exempts au premier tour, préparé avant que les qualifiés de la phase précédente soient connus.
- `Tiebreak\Tiebreakers` : `buchholz`, `buchholz-cut-1`, `buchholz-cut-2`, `sonneborn-berger`, `head-to-head`, `wins`,
  `mission-points`, et les critères de départage qu'ajoute une application ; les égalités restantes départagées par le
  tirage du germe publié.
- `Contract\SeedingSource`, `Seeding\FixedSeeding` et `Seeding\RatingSeeding`, qui lit les cotes de `tilttek/rating`,
  suggéré.
- `Event\TournamentFinished` : le gagnant et le podium, renvoyés à la fin du tournoi et de nouveau quand une correction
  change le podium.
- `Contract\TournamentStore`, qui garde chaque tournoi à une version, et `Store\InMemoryTournamentStore` pour les tests.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; des tests de propriétés et des bancs
  d'essai des appariements.
- `composer.json`, `phil-dev.php`, `composer-dependency-analyser.php`, `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
