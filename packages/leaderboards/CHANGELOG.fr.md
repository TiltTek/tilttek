<!-- phil-dev source-sha256=86d544df09bd2c282ba11189c3c1813355aa23a3ff6d871df49c9c6ce5a782b9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/leaderboards` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.1-alpha - 2026-11-20

### Corrigé

- `ValkeyScoreStore::delete()` et `eraseMember()` retirent le champ d'un pointage de l'index du membre, que le pointage
  soit encore gardé ou non : une entrée d'index laissée sans son pointage par une écriture partielle, une restauration
  ou une modification à la main restait dans l'index après un effacement. Seuls les pointages encore gardés sont
  comptés.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `Store\ScoreStore::keep()` : un stockage ne remplace un pointage signalé que s'il l'emporte sur celui qui est gardé,
  la comparaison et l'écriture en une seule étape atomique ; un stockage écrit pour 0.1 l'implémente.
  `InMemoryScoreStore` et `ValkeyScoreStore` l'implémentent, ce dernier dans le script Lua qui écrit, sur les clés de
  tri.
- `Leaderboards::report()` garde la valeur par `ScoreStore::keep()` plutôt que de lire le pointage et de le réécrire :
  de deux signalements au même moment, une valeur plus ancienne ou plus basse ne remplace plus l'autre.

### Ajouté

- `Measure::supersedes()` : si un pointage signalé prend la place de celui qui est gardé.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Period`, `PeriodKind` et `Schedule` : les périodes d'un classement — un jour civil (`2026-11-07`), une semaine ISO à
  partir du lundi (`2026-w45`), un mois (`2026-11`), un trimestre (`2026-q4`) ou une année (`2026`) civils dans le
  fuseau horaire du classement, des saisons nommées et datées qui ne se chevauchent jamais et peuvent laisser du temps
  entre elles, toujours, ou une fenêtre glissante — ; la période dans laquelle tombe un moment, celle que nomme un code,
  et celle qui a fini en dernier.
- `Measure`, `Ties`, `Board` et `Boards` : des classements qui comptent des gestes, additionnent leurs quantités,
  gardent la meilleure ou comptent les jours distincts avec un geste dans le journal, pour des verbes par motif et des
  conditions de contexte, ou qui gardent la valeur la plus récente ou la plus haute que signale l'application ; des
  pointages égaux départagés en faveur du premier qui les a atteints ou un rang partagé ; des instantanés de 1 à 1000
  membres ; une fenêtre glissante de 1 à 366 jours ; un classement pour chaque objet des activités, gardé sous une
  empreinte de l'objet ; les classements d'une application à partir de valeurs simples, comme les donne une
  configuration, un classement hebdomadaire avec sa ligue.
- `TieBreak`, `Aggregate`, `Direction` et `Ranking` : jusqu'à deux bris d'égalité par classement — le nombre, le total,
  la plus haute ou la plus basse des quantités des gestes ou d'un entier du contexte, ou une valeur signalée avec le
  pointage —, chacun croissant ou décroissant, avant le premier à atteindre un pointage ou un rang partagé ; les clés
  qui trient les pointages d'un classement dans son ordre.
- `Title`, `Team`, `TeamRow` et `Chance` : un titre détenu tant qu'un pointage atteint son seuil, par le meneur seul
  quand il le précise ; des équipes que donne l'application, classées selon la somme des pointages de leurs membres,
  avec de 1 à 10 dés de 2 à 100 faces de chance qu'un classement active, tirés à partir du secret de l'application.
- `Contribution` et `Score` : ce qu'une activité a apporté à un membre sur un classement dans une période et à chaque
  bris d'égalité, gardé pour pouvoir être repris, et le pointage qu'elle donne — de 0 à 10^15 —, avec ses bris d'égalité
  et le moment où il a été atteint.
- `Visibility`, `Appearance`, `Circle`, `Row` et `Standing` : chaque membre caché jusqu'à ce qu'il choisisse
  d'apparaître par son nom ou sous un pseudonyme de 1 à 40 caractères ; un classement tel qu'une personne le voit — les
  premières lignes, sa propre ligne même plus bas, le nombre de membres classés —, parmi tout le monde ou parmi les 1 à
  1000 membres d'un cercle.
- `Placing`, `Snapshot` et `LeaderboardObserver` : un classement figé à la fin d'une période avec ses premiers membres,
  le nombre de membres classés et le moment, pris une seule fois et annoncé une seule fois, les instantanés dus pour la
  période que chaque classement a finie en dernier, et un instantané montré à une personne selon le choix actuel de
  chaque membre.
- `League`, `Formation`, `Movement`, `Membership`, `Division`, `Settlement`, `LeagueObserver` et `Leagues` : la ligue
  d'un classement hebdomadaire — de 1 à 50 paliers, des divisions de 2 à 100 membres, le nombre de membres qui montent
  et qui descendent, des divisions formées par arrivée ou par volume — ; un membre qui entre dans une division de son
  palier la première fois qu'il marque dans une semaine, un nouveau membre au palier le plus bas, des divisions remplies
  dans l'ordre d'arrivée, ou parmi les membres de sa cohorte selon son pointage de la semaine précédente ; la division
  d'un membre classée parmi ses seuls membres, selon le choix d'apparition de chacun, avec ses zones ; les semaines
  réglées une seule fois et annoncées une seule fois — les premiers montent, les derniers d'une division pleine
  descendent, personne au-dessus du palier le plus haut ni sous le plus bas, un membre absent toute la semaine laissé où
  il est —, les semaines encore ouvertes réglées avant qu'un membre entre dans la suivante ; paliers, exportation et
  effacement.
- `ScoreStore`, `SnapshotStore`, `AppearanceStore` et `LeagueStore`, avec `InMemoryScoreStore`, `InMemorySnapshotStore`,
  `InMemoryAppearanceStore` et `InMemoryLeagueStore` : une activité qui contribue une seule fois par classement, les
  contributions d'un classement plus anciennes qu'un moment retirées, des pointages lus dans l'ordre d'un classement
  parmi tout le monde ou un cercle, le nombre de membres classés devant un pointage, un instantané gardé une seule fois
  par classement et par période, les choix des membres qui apparaissent, un membre placé une seule fois par semaine et
  une semaine réglée une seule fois. Les implémentations en mémoire se vident avec `reset()`, pour un processus de
  longue durée.
- `ValkeyScoreStore`, `ValkeyConnection` et `RawCommandConnection` : les contributions et les pointages dans les
  ensembles triés de Valkey, chaque classement lu par son index, chaque écriture un script Lua, chaque clé sous une
  étiquette de hachage ; une connexion par l'extension redis, Relay ou Predis.
- `LeaderboardProjection` : les pointages tenus à jour depuis le journal de `tilttek/activity`, un geste plus ancien
  qu'une fenêtre glissante laissé de côté et les fenêtres glissées, une activité remise deux fois comptée une seule
  fois, une annulation qui recompte les pointages qu'elle a touchés, et une reconstruction qui oublie les contributions
  d'une période et la rejoue sous les nouveaux classements, les classements signalés laissés tels quels, et un membre
  qui entre dans une division quand il marque sur un classement avec une ligue.
- `Leaderboards` : la période en cours ou une période nommée d'un classement, des classements de 1 à 100 lignes, le rang
  d'un membre, pour un objet sur un classement par objet, les valeurs que signale l'application avec leurs bris
  d'égalité — la plus récente qui ignore un signalement plus ancien, la plus haute qui ignore celui qui ne classe pas
  mieux —, la division d'un membre, les titres que détient un membre et s'il détient celui d'un classement, un
  classement des équipes, le choix d'apparition de chaque membre, l'exportation et l'effacement d'un membre.
- `Erasure` : ce que l'effacement d'un membre a retiré — contributions et pointages, lignes dans les instantanés, choix
  d'apparition, paliers et adhésions dans les ligues.
- `LeaderboardsException`, `InvalidLeaderboard`, `UnknownBoard`, `UnknownPeriod` et `PeriodNotOver`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
