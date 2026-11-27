<!-- phil-dev source-sha256=dbed30802eedabb6415403235fc956a2de46c48fdf168789cba09ae6e714f261 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/game-engine-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.2-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.2.1-alpha - 2026-11-24

### Modifié

- `phil/ux-kit` est un prérequis de développement : le test fonctionnel produit le rendu du gabarit d'une table avec ses
  recettes Card et Badge lues depuis `vendor/`, et vérifie leur balisage à chaque exécution, sans chercher le kit à côté
  du dépôt.
- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8.

## 0.2.0-alpha - 2026-11-14

### Corrigé

- `GameTables::log()` donnait le journal d'une table, germe compris, pendant que sa partie continuait, alors que le
  germe révèle les tirages à venir. Il est maintenant refusé avec `GameInProgress` jusqu'à la fin de la partie, comme
  `finishedLog()`.

### Modifié

- `tilttek:game-engine:verify` lit le journal par les `Tables` de la bibliothèque, une partie encore en cours comprise :
  son constructeur prend `Tables` au lieu de `GameTables`. Elle montre l'engagement envers le germe, jamais le germe.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `TilttekGameEngineBundle` et sa configuration `tilttek_game_engine` : le stockage, sa connexion, le cache des états,
  le verrou, Mercure, la personne qui regarde, la scène de jeu de rôle, le domaine de traduction et le sous-dossier du
  kit.
- Les jeux : chaque service qui implémente `Contract\Game` de `tilttek/game-engine`, étiqueté par autoconfiguration et
  rassemblé par nom, et la scène de jeu de rôle de la bibliothèque sur demande.
- `DbalLogStore` et `GameEngineTables` : les journaux sur Doctrine DBAL, sur MySQL, PostgreSQL ou SQLite, dans deux
  tables, les places, les options et les charges utiles en texte JSON dans l'ordre où ils ont été écrits ; l'en-tête
  d'un journal lu à partir d'une seule ligne, avec son nombre de coups et une révision qui ne descend jamais ; les coups
  lus fenêtre par fenêtre ; un coup ajouté ou repris en une seule instruction qui compare le nombre de coups et la
  révision, un coup concurrent refusé, la clé primaire contre deux coups d'un même numéro, un journal entier relu
  jusqu'au nombre de coups de sa table, et une table supprimée avec ses coups.
- `GameEngineSchemaListener` : les tables des journaux dans le schéma que l'ORM génère pour leur connexion.
- `CacheStateCache` : les états dans un pool PSR-6 sous forme du texte JSON de `Snapshot::toArray()`, avec la révision
  de leur journal et l'engagement envers leur germe, un état que JSON ne rendrait pas tel quel laissé hors du cache, ce
  que rend le pool vérifié et oublié quand il est refusé, une révision ultérieure d'une table jamais écrasée — sous le
  verrou de sa clé quand une fabrique de verrous est configurée —, et une durée de vie facultative.
- `GameTables`, `Contract\TableLock` et `SymfonyTableLock` : les tables de la bibliothèque sur les services configurés,
  étiquetées `kernel.reset`, chaque coup et chaque coup repris faits sous le verrou de leur table sur la fabrique de
  verrous de Symfony, avec sa durée de vie, et aucun coup repris une fois la partie finie.
- `SeatIdentifiers` et `IdentifyingSeat` : une table ouverte seulement avec des identifiants de place opaques, une place
  qui ressemble à une adresse courriel ou à une référence de membre refusée.
- `MercureTablePublisher` et `TableTopic` : chaque changement publié en privé sur le sujet de sa table, avec des
  identifiants et des compteurs seulement, un hub qui échoue journalisé plutôt que levé.
- `TableController`, `Viewer`, `Contract\ViewerResolver` et `AudienceViewerResolver` : les points de terminaison de la
  vue d'une place, avec ses préférences, et du journal d'une table dont la partie est finie, dont on vérifie qu'il est
  le journal de son état fini, par le résolveur de l'application, le public ouvert seulement sur demande, jamais mis en
  cache.
- `tilttek:game-engine:setup`, qui crée les tables des journaux ou montre leur SQL, et `tilttek:game-engine:verify`, qui
  rejoue un journal contre l'engagement envers son germe.
- Le gabarit d'une table fait des recettes Card et Badge du kit PHIL UX, les fonctions Twig `tilttek_game_table()`,
  `tilttek_game_engine_kit()` et `tilttek_game_engine_domain()`, et ses textes en anglais et en français.
- Le contrôleur Stimulus `game-table`, qui relit la vue de la place du lecteur à chaque mise à jour que pousse le hub
  Mercure et la distribue comme événement.
- La recette Flex, dans `recipe/0.1/`.
- Des bancs d'essai du stockage d'une table et d'une table de 8 000 coups.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
