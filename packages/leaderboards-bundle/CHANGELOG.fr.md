<!-- phil-dev source-sha256=97824b58e7927872d0783ed500feefe4c5c08940af1baa9cda75f30f17754ff6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/leaderboards-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.3, la version publiée le 2026-11-24, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.2, sa dernière version mineure publiée, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.
- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8.

## 0.1.0-alpha - 2026-11-15

### Ajouté

- `LeaderboardsTables` et `LeaderboardsSchemaListener` : les neuf tables des contributions, des pointages, des
  instantanés, de leurs lignes, des choix d'apparition des membres, des paliers, des adhésions et des semaines réglées
  des ligues, et des premières places prises, ajoutées au schéma que l'ORM génère pour leur connexion.
- `DbalScoreStore::keep()` : un pointage signalé inséré quand le membre n'en a aucun, ou sinon écrit par un `UPDATE`
  dont la condition est la comparaison de `tilttek/leaderboards`, pour que de deux signalements au même moment, le plus
  ancien ou le plus bas ne l'emporte jamais.
- `DbalScoreStore`, `DbalSnapshotStore`, `DbalAppearanceStore`, `DbalLeagueStore`, `DbalLeadStore` et `Rows` : les cinq
  stockages sur Doctrine DBAL, une contribution, un instantané, une adhésion et une première place écrits une seule fois
  par une instruction qui n'écrit rien sur un doublon, un pointage et ses bris d'égalité remplacés en une seule
  transaction, les premiers pointages lus dans l'ordre du classement parmi tout le monde ou un cercle, les contributions
  plus anciennes qu'une fenêtre retirées, un membre placé parmi sa cohorte, une semaine réglée une seule fois en une
  seule transaction, les moments dans des colonnes du type `datetime_utc_immutable`, la colonne `placing` mise entre
  guillemets par la plateforme ; testés sur SQLite, MySQL 8.4 et PostgreSQL 17.
- `TilttekLeaderboardsBundle` : la configuration `tilttek_leaderboards` — le stockage, les pointages dans Valkey, la
  connexion, le fuseau horaire, les classements avec leurs mesures, verbes, conditions de contexte, périodes, fenêtres
  glissantes, fuseaux horaires, saisons, égalités, bris d'égalité, tailles d'instantané, ligues et la façon dont leurs
  divisions sont formées, classements par objet, titres et chance des équipes, le nom de la projection, le
  planificateur, le secret de la chance, le sous-dossier des recettes du kit, la célébration et le moment où une
  première place est annoncée — `celebrate_on`, `period_close` par défaut ou `score_change` —, le domaine de traduction
  et le type d'un membre —, les noms des classements gardés tels qu'écrits, vérifiés à la compilation du conteneur.
- `LeaderboardsServices` : les stockages sur Doctrine DBAL ou en mémoire, les pointages dans Valkey sur un client que
  Symfony Cache connecte à partir d'un DSN ou sur le propre client de l'application nommé par `valkey.client` —
  l'extension redis, Relay, Predis ou valkey-glide-php —, avec des commandes courantes seulement et aucune commande de
  persistance, les classements, `Leagues`, `Leaderboards` et la projection étiquetée `tilttek_activity.projection`, qui
  reçoivent l'horloge. Les stockages en mémoire sont étiquetés `kernel.reset`.
- `DispatchingObserver`, `SnapshotTaken`, `LeagueSettled`, `TopReached`, `Summit` et `CelebrateOn` : un instantané pris,
  une semaine d'une ligue réglée et un membre qui a atteint le sommet — premier d'un instantané une fois la période
  close, en tête de la période en cours à mesure que son pointage change, ou promu au palier le plus haut — comme
  événements du répartiteur de l'application, une seule fois par classement, période et membre, chacun portant la façon
  dont le membre a choisi d'apparaître.
- `LeadWatch`, `Lead`, `LeadStore` et `InMemoryLeadStore` : avec `celebrate_on: score_change`, le stockage des pointages
  qui surveille la tête — un pointage gardé dans la période en cours qui met son membre premier parmi au moins deux,
  quand il n'était pas premier avant —, les premières places revendiquées une seule fois par classement, période et
  membre, pour qu'une reconstruction n'annonce rien de nouveau ; un pointage signalé passe par le `keep()` du stockage
  surveillé, et ne prend la tête que s'il a été gardé.
- `LeaderboardsExtension`, `LeagueView`, `CelebrationView`, `KitComponents`, `KitSubdirectoryPass` et les gabarits
  `standing.html.twig`, `division.html.twig`, `league.html.twig`, `teams.html.twig` et `celebration.html.twig` : un
  classement, un instantané, une division, l'écran de ligue d'un membre, un classement des équipes et la célébration
  d'un membre qui a atteint le sommet — les trois derniers faits des recettes `Card`, `Badge`, `Empty` et `Celebration`
  d'un kit du UX Toolkit, nommées d'après le sous-dossier du Toolkit ; la célébration accessible, son message dans une
  région active polie, sans mouvement là où il est réduit, sans son, montrée au membre seul, configurée par
  `celebration`, après la clôture de la période ou pendant que le membre mène la période en cours —, les titres que
  détient un membre, avec leurs traductions en anglais et en français.
- `SnapshotCommand` (`tilttek:leaderboards:snapshot`), `SettleCommand` (`tilttek:leaderboards:settle`), `SlideCommand`
  (`tilttek:leaderboards:slide`) et `ShowCommand` (`tilttek:leaderboards:show`).
- `LeaderboardsSchedule`, `TakeDue` et `TakeDueHandler` : le planificateur `tilttek_leaderboards` sur Symfony Scheduler,
  qui prend les instantanés, règle les semaines et fait glisser les fenêtres dues à la fréquence configurée.
- `LeaderboardsPrivacy` : l'exportation et l'effacement des pointages, des lignes, du choix, des paliers, des adhésions
  et des premières places d'un membre pour `phil/privacy-bundle`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
