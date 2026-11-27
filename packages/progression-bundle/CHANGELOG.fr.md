<!-- phil-dev source-sha256=169d2dbe0ac5fc5b13440660f57030a32beec47f29dc3dc137235d3535334b01 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/progression-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.2-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.2.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `DbalExperienceStore` et `DbalMilestoneStore` implémentent les avis de `tilttek/progression` 0.2 : une colonne
  `delivered` sur `tilttek_progression_gain` et `tilttek_progression_milestone`, vraie par défaut pour les lignes déjà
  présentes, fausse à l'insertion et mise à vrai une fois l'observateur averti ; `ofMember()` lit les lignes d'un membre
  pour l'exportation. Une table existante reçoit la colonne par la prochaine migration de l'application.
- `LevelUp`, `ReachedMilestone` et `StreakBroken` sont distribués au moins une fois : un écouteur qui a échoué est
  rappelé quand l'entrée est remise de nouveau à la projection, avec la prochaine activité du sujet, ou par
  `ProgressionProjection::deliver()`. Les écouteurs doivent être idempotents.
- Le bundle requiert `tilttek/progression` `^0.2@dev`.

### Corrigé

- L'exportation d'un membre énumère les gains et les jalons que ses gestes ont apportés à un personnage, que
  l'effacement supprimait déjà.
- `ProgressionPrivacy` implémente `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et
  `PersonalDataEraser`, les interfaces qu'expose `phil/privacy-bundle`, et le bundle l'enregistre quand elles existent :
  l'exportation et l'effacement d'un membre l'atteignent maintenant.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `DbalExperienceStore`, `DbalMilestoneStore`, `DbalStreakStore` et `Rows` : les trois stockages sur Doctrine DBAL,
  chaque ligne écrite une seule fois par une instruction qui n'écrit rien sur un doublon, les totaux par sommes, les
  dates en UTC.
- `ProgressionTables` et `ProgressionSchemaListener` : les cinq tables des gains, des jalons, des gestes de série, des
  unités gelées et des états de série, ajoutées au schéma que l'ORM génère pour leur connexion.
- `TilttekProgressionBundle` : la configuration `tilttek_progression` — le stockage, la connexion, le fuseau horaire,
  les pistes avec leurs courbes, sujets, saisons, règles et jalons, les séries avec leur unité, leur grâce et leur
  contexte, la clé de contexte du fuseau horaire d'un membre, le nom de la projection, le domaine de traduction et le
  type d'un membre —, les noms gardés tels qu'écrits, vérifiés à la compilation du conteneur.
- `ProgressionServices` : les stockages sur Doctrine DBAL ou en mémoire, les pistes, les séries, le résolveur de fuseau
  horaire, `StreakKeeper`, `Progression` et la projection étiquetée `tilttek_activity.projection`. Les stockages en
  mémoire sont étiquetés `kernel.reset`. La projection oublie sa reprise quand le noyau se réinitialise.
- `DispatchingObserver`, `LevelUp` et `LevelDown` : les changements de niveau, les jalons atteints et les séries brisées
  comme événements du répartiteur de l'application.
- `ProgressionExtension` et les gabarits `bar.html.twig` et `streak.html.twig`, avec leurs traductions en anglais et en
  français.
- `SweepCommand` (`tilttek:progression:sweep`) et `ShowCommand` (`tilttek:progression:show`).
- `ProgressionPrivacy` : l'exportation et l'effacement de la progression d'un membre pour `phil/privacy-bundle`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
