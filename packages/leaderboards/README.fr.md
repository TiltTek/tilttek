<!-- phil-dev source-sha256=c87caf6430c714bb465d7306fef2407030eec9f36fe9acc1c988ec453a6ac388 -->
# tilttek/leaderboards

**Des membres classés selon ce qu'ils font, par période, et personne n'est montré aux autres sans l'avoir choisi.**

tilttek/leaderboards classe des membres selon une mesure pendant une période : les parties jouées ce mois-ci, les
points gagnés cette semaine, le meilleur score d'une seule partie, les jours d'activité, un niveau ou la plus longue
série. Quatre mesures se tirent de l'activité des membres, enregistrée par `tilttek/activity` : les gestes comptés,
leurs quantités additionnées, la meilleure quantité d'un seul geste, les jours distincts où il y a un geste. Deux
mesures prennent une valeur que l'application rapporte à partir d'un solde qu'elle garde ailleurs : la dernière valeur,
ou la plus haute. Un classement porte sur un jour, une semaine ISO, un mois, un trimestre, une année, des saisons
nommées, depuis toujours ou une fenêtre glissante de ses derniers jours, chacun dans le fuseau horaire qu'il déclare.
Les scores sont tenus à jour à mesure que l'activité est enregistrée, et non recomptés à chaque affichage ; une
annulation reprend sa contribution, et un classement modifié est rejoué à partir du journal. Les scores égaux sont
départagés par au plus deux critères de départage (le moins de tentatives, le temps le plus court), puis reviennent au
premier qui les a atteints, ou partagent un rang. Un classement se fait entre amis ou à l'intérieur d'un groupe, par un
cercle de membres que donne l'application, et peut classer les membres pour chaque objet : chaque jeu, chaque livre.
Un instantané fige les premiers membres à la fin d'une période, une seule fois, pour montrer le classement final et les
récompenser. Un classement hebdomadaire peut avoir une ligue : ses membres sont répartis en divisions d'une trentaine,
par paliers, formées selon leur ordre d'arrivée ou selon leur volume de la semaine précédente ; chacun ne voit que sa
division, et à la fin de la semaine les premiers montent d'un palier et les derniers descendent. Un classement peut
donner un titre à partir d'un seuil, et classer des équipes avec une part de chance que l'application active. Personne
n'est classé publiquement sans l'avoir choisi : un membre qui n'a pas choisi de paraître voit son propre rang, et les
autres voient une ligne sans nom ; un membre peut paraître sous son nom ou sous un pseudonyme. Effacer un membre retire
ses scores, ses lignes dans les instantanés et son choix.

Elle s'adresse à une application qui veut que ses membres comparent ce qu'ils font (un site communautaire, un site de
jeu, une plateforme de lecture ou d'écriture) et qui ne doit pas exposer un membre qui ne l'a pas demandé.
`tilttek/leaderboards-bundle` l'intègre à Symfony et garde les classements dans Doctrine DBAL, les scores dans Valkey
quand il est activé ; une application sans Symfony prend la bibliothèque seule, avec les stockages en mémoire, les
scores dans Valkey par le client qu'elle a, ou ses propres stockages.

C'est une simple bibliothèque PHP. Ses dépendances sont `tilttek/activity`, le journal que lisent les classements,
`phil/resource`, la référence opaque d'un membre, et `psr/clock`. Les membres sont des références que donne
l'application, `member:44` : la bibliothèque ne lit jamais un nom, un courriel ou un compte, et c'est à l'application
de donner le nom qu'affiche une ligne publique.

La conception est sobre : un `Board` contient sa `Measure`, son `Schedule` de `Period`, ses verbes et ses conditions,
son `Ranking` de `TieBreak` et de `Ties`, et ses options. `LeaderboardProjection` lit le journal et écrit des
`Contribution` et des `Score` dans un `ScoreStore`. `Leaderboards` lit un `Standing` tel qu'un spectateur le voit (des
`Row` selon l'`Appearance` de chaque membre), prend les valeurs que rapporte l'application, prend des `Snapshot`, et
exporte et efface un membre. `Leagues` place les membres dans les divisions de la `League` d'un classement et règle ses
semaines.

## État

| Partie | État |
|---|---|
| `Board`, `Boards`, `Measure`, `Ties` : des classements sur six mesures, à partir d'une configuration | Implémentés et testés |
| `TieBreak`, `Aggregate`, `Direction`, `Ranking` : jusqu'à deux critères de départage, puis le premier à atteindre un score ou un rang partagé | Implémentés et testés |
| `Period`, `PeriodKind`, `Schedule` : jours, semaines ISO, mois, trimestres, années, saisons, depuis toujours et fenêtres glissantes, dans un fuseau horaire | Implémentés et testés |
| `LeaderboardProjection` : scores tenus à jour à partir du journal, annulations, reconstructions et fenêtres glissantes | Implémentée et testée |
| `Leaderboards` : classements, rangs, cercles, classements par objet, valeurs rapportées, apparences, instantanés, exportation et effacement | Implémenté et testé |
| `Title`, `Team`, `TeamRow`, `Chance` : titres à partir d'un seuil ; équipes classées, avec la chance qu'un classement active | Implémentés et testés |
| `ValkeyScoreStore`, `ValkeyConnection`, `RawCommandConnection` : les scores dans les ensembles triés de Valkey | Implémentés et testés |
| `Appearance`, `Visibility`, `Row`, `Standing` : caché par défaut, par nom ou par pseudonyme | Implémentés et testés |
| `Snapshot`, `Placing`, `LeaderboardObserver` : le classement final d'une période, annoncé une fois | Implémentés et testés |
| `League`, `Leagues`, `Division`, `Settlement`, `Formation` : divisions hebdomadaires par paliers, formées par arrivée ou par volume, promotion et relégation | Implémentés et testés |
| Quatre stockages et leurs implémentations en mémoire, avec les contrats que tout stockage respecte | Implémentés et testés |
| Exceptions sous une seule interface `LeaderboardsException` | Implémentées et testées |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Classements et périodes](doc/fr/boards-and-periods.md) | Mesures, verbes, conditions, périodes, fuseaux horaires, saisons, fenêtres glissantes, égalités et critères de départage, classements par objet et configuration des classements. |
| [Scores et projection](doc/fr/scores-and-projection.md) | Comment le journal est lu, les annulations, les reconstructions, et les valeurs que rapporte l'application. |
| [Classements affichés](doc/fr/standings.md) | Un classement tel qu'un spectateur le voit, les égalités, la ligne du spectateur et les cercles. |
| [Paraître dans un classement](doc/fr/appearance.md) | Caché par défaut, par nom ou par pseudonyme, et ce que voit chaque spectateur. |
| [Instantanés](doc/fr/snapshots.md) | Figer une période, récompenser les premiers et montrer le classement final. |
| [Ligues](doc/fr/leagues.md) | Divisions hebdomadaires par paliers, entrée dans une division, divisions formées par volume, promotion et relégation à la fin de la semaine. |
| [Équipes et titres](doc/fr/teams-and-titles.md) | Titres détenus à partir d'un seuil, équipes classées et chance qu'un classement ajoute à leurs scores. |
| [Effacement](doc/fr/erasure.md) | Ce que contient l'exportation d'un membre et ce que retire son effacement. |
| [Écrire un stockage](doc/fr/writing-a-store.md) | Les quatre contrats de stockage qu'implémente le support d'une application, et les scores dans Valkey. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : stockages, observateurs, horloge. |
| [Exceptions](doc/fr/exceptions.md) | `LeaderboardsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui lit les classements. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Leaderboards\{Appearance, Boards, LeaderboardProjection, Leaderboards};
use Tilttek\Leaderboards\Store\{InMemoryAppearanceStore, InMemoryScoreStore, InMemorySnapshotStore};

$boards = Boards::fromArray([
    'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'month'],
    'level' => ['measure' => 'latest'],
], 'America/Toronto');
$scores = new InMemoryScoreStore();
$projection = new LeaderboardProjection($boards, $scores); // handed to the journal's projections
$leaderboards = new Leaderboards($boards, $scores, new InMemorySnapshotStore(), new InMemoryAppearanceStore(), $clock);

$leaderboards->appear($member, Appearance::pseudonym('Le Nain Gris'));
$standing = $leaderboards->standing('games-month', viewer: $member);
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
