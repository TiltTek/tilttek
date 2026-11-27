<!-- phil-dev source-sha256=592df126a4cc1f12ef8fc1f919e074882613fe1a3211abb430cbbe59c39960ec -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/activity-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `DbalActivityLog` attend une position prise par une transaction pas encore validée : `read()` ne remet aucune entrée
  au-delà d'une position manquante, et `lastPosition()` est la position juste en dessous, jusqu'à ce que l'entrée
  au-dessus ait été écrite il y a `gap_wait`, pour qu'un rattrapage n'avance plus un point de reprise au-delà d'une
  entrée validée plus tard. Il prend une horloge et l'attente.
- `DbalActivityLog::append()` et `cancel()` lisent l'entrée déjà présente avant d'insérer : un geste enregistré de
  nouveau ne prend aucune position.
- `MessengerProjections` implémente `ResumableObserver` : une entrée enregistrée ou annulée de nouveau renvoie son
  message à chaque projection encore en retard sur elle, le projecteur donné, pour qu'un message perdu après l'écriture
  de l'entrée soit rattrapé par la requête répétée.
- Le bundle requiert `tilttek/activity` `^0.1.1@dev`, dont `MessengerProjections` implémente `ResumableObserver`.

### Ajouté

- L'option `gap_wait`, `PT10S` par défaut.

### Corrigé

- `ActivityPrivacy` implémente `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et `PersonalDataEraser`,
  les interfaces qu'expose `phil/privacy-bundle`, et le bundle l'enregistre quand elles existent : l'exportation et
  l'effacement d'un membre l'atteignent maintenant.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `DbalActivityLog` : le journal sur Doctrine DBAL, chaque entrée écrite une seule fois par `ON CONFLICT DO NOTHING`,
  `INSERT IGNORE` ou `INSERT OR IGNORE` et relue, les filtres en SQL avec les préfixes de verbes comparés par
  `SUBSTRING`, des totaux sans les activités annulées, l'effacement en une seule transaction, les dates en UTC, les
  lignes illisibles refusées.
- `DbalCheckpoints` : le point de reprise de chaque projection, mis à jour ou inséré, une course d'insertion perdue mise
  à jour.
- `ActivityTables` : les tables `tilttek_activity_entry` — une position auto-incrémentée, un index unique sur la clé
  d'idempotence et le type, des index sur le membre, l'objet et le verbe avec le moment — et
  `tilttek_activity_checkpoint` ; `ActivitySchemaListener` les ajoute au schéma que l'ORM génère pour la connexion du
  journal.
- `TilttekActivityBundle`, alias `tilttek_activity` : le stockage (`doctrine` ou `memory`), la connexion DBAL, les
  verbes déclarés, vérifiés à la compilation du conteneur, la taille des lots, la façon dont les projections suivent le
  journal — `live` dans la requête par défaut, `messenger` ou `manual` —, le bus de leurs messages, la fenêtre dans
  laquelle doit tomber le moment d'un geste (`dates.past`, `dates.ahead`) et le type d'une référence de membre. Le
  journal et les points de reprise en mémoire sont étiquetés `kernel.reset`.
- Les services : `Journal` avec l'horloge de l'application, les verbes déclarés et les observateurs étiquetés
  `tilttek_activity.observer` ; `Projections` à partir des services étiquetés `tilttek_activity.projection` ;
  `Projector` ; `LiveProjections` ; projections et observateurs autoconfigurés par leurs interfaces.
- `MessengerProjections`, `CatchUpProjection` et `CatchUpProjectionHandler` : les projections tenues à jour hors de la
  requête, un message par projection et par entrée qui ne porte que le nom de la projection et la position de l'entrée,
  le journal comme boîte d'envoi et les points de reprise comme boîte de réception, pour qu'un message perdu soit
  rattrapé et qu'un message traité deux fois n'applique rien.
- Les commandes `tilttek:activity:catch-up`, `tilttek:activity:rebuild` (avec `--since`) et `tilttek:activity:status`,
  quand la Console est installée.
- `ActivityPrivacy` : l'exportation et l'effacement de l'activité d'un membre pour `phil/privacy-bundle`, quand il est
  installé.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
