<!-- phil-dev source-sha256=85a8cb62b8a9defa164a88601b9ad08b3cc8955326c28e2dbc619001761917f8 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/points-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `PointsTables` ajoute une troisième table, `tilttek_points_lots`, qui garde le point de reprise des lots de chaque
  compte dans une monnaie, en JSON à une position ; la prochaine migration de l'application la crée.
- `DbalLedger` implémente `Ledger::appendAfter()` sous la ligne de verrou du compte débité, que prennent aussi les
  paiements, et `keptLots()` et `keepLots()` sur `tilttek_points_lots` ; l'oubli des mouvements dérivés et l'effacement
  d'un membre suppriment les points de reprise.
- Le bundle requiert `tilttek/points` `^0.2@dev`, dont `DbalLedger` implémente `Ledger::appendAfter()`, `keptLots()` et
  `keepLots()`.

### Corrigé

- Une expiration ne laisse plus un solde sous zéro quand une dépense du même compte se glisse entre sa lecture des lots
  et son écriture, et ne relit plus tout l'historique du compte à chaque dépense.
- `PointsPrivacy` implémente `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et `PersonalDataEraser`, les
  interfaces qu'expose `phil/privacy-bundle`, et le bundle l'enregistre quand elles existent : l'exportation et
  l'effacement d'un membre l'atteignent maintenant.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `PointsTables` — `tilttek_points_movement`, une ligne par mouvement avec sa clé unique, et `tilttek_points_lock`, une
  ligne de verrou par compte payeur et par monnaie qui compte ses paiements — et `PointsSchemaListener`, qui les ajoute
  au schéma que l'ORM génère pour la connexion du grand livre.
- `DbalLedger` : un mouvement écrit une seule fois par clé, l'insertion qui n'écrit rien sur un doublon selon la
  plateforme (`ON CONFLICT DO NOTHING`, `INSERT IGNORE`, `INSERT OR IGNORE`), un paiement qui met à jour la ligne de
  verrou de son compte pour tenir son verrou avant de rien lire, en READ COMMITTED sur MySQL et MariaDB et avec des
  lectures verrouillantes dans une transaction que l'application a ouverte, pour qu'il vérifie le solde qu'a laissé le
  dernier paiement, les soldes, les nets et les relevés en SQL, les mouvements dérivés oubliés avant une reconstruction,
  l'effacement d'un membre en une seule transaction, les dates en UTC, et les lignes illisibles refusées.
- `TilttekPointsBundle` et sa configuration `tilttek_points` : le stockage (`doctrine` ou `memory`), la connexion DBAL,
  les monnaies avec leur durée de vie, la monnaie par défaut, le fuseau horaire des plafonds, les règles de gain, le nom
  de la projection, la taille des lots de traitement et le type d'un membre pour le module de vie privée ; les monnaies
  et les règles sont vérifiées à la compilation du conteneur. Le grand livre en mémoire est étiqueté `kernel.reset`.
- `PointsServices` : le grand livre, `Currencies`, `Rules`, `Points`, et `PointsProjection` étiquetée
  `tilttek_activity.projection` pour `tilttek/activity-bundle`.
- Les commandes `tilttek:points:expire` et `tilttek:points:history` (`--currency`, `--after`, `--limit`).
- `PointsPrivacy` : l'exportation et l'effacement des points d'un membre pour `phil/privacy-bundle` (module
  `tilttek_points`).
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
