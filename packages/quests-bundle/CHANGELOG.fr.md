<!-- phil-dev source-sha256=2460b079d8d5247144f362b9a3fbab145b9c858365af9fde2fc63cd8f1e6f62d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/quests-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.3.2-alpha - 2026-11-25

### Sécurité

- `twig/twig` antérieur à 3.31.1 refusé, pour les correctifs de sécurité de cette version : son bac à sable vérifie la
  politique à plus d'endroits, et un gabarit ne peut plus remplacer le contenu d'un `Markup`.

## 0.3.1-alpha - 2026-11-24

### Modifié

- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31.

## 0.3.0-alpha - 2026-11-17

### Modifié

- `Tilttek\Quests\Contract\TeamMembership` est un service du bundle, donné à `Quests` pour `tilttek/quests` 0.3 : le
  `NoTeamMembership` de la bibliothèque, qui refuse toute quête d'équipe, jusqu'à ce que l'application crée un alias de
  l'interface vers son propre service. Une application qui utilise des quêtes d'équipe crée cet alias avant la mise à
  niveau.
- Il requiert `tilttek/quests` 0.3.

## 0.2.0-alpha - 2026-11-14

### Modifié

- `DbalDuelStore::transition()` : un `UPDATE … WHERE status = ?` qui ne change un duel qu'à partir du statut dans lequel
  il a été lu, pour `tilttek/quests`, dont les réponses, les expirations et les victoires ne s'écrasent plus les unes
  les autres.
- Le bundle requiert `tilttek/quests` `^0.2@dev`, dont `DbalDuelStore` implémente `Store\DuelStore::transition()`.

### Corrigé

- `QuestsPrivacy` implémente `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et `PersonalDataEraser`, les
  interfaces qu'expose `phil/privacy-bundle`, et le bundle l'enregistre quand elles existent : l'exportation et
  l'effacement d'un membre l'atteignent maintenant.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `AlmanacSchedule` et `AlmanacScheduleReader` : des quêtes qui reviennent aux dates que calcule `phil/almanac` — à
  partir d'une règle, jusqu'à une autre règle ou pendant un certain nombre de jours —, chaque occurrence nommée par la
  date de son ouverture.
- `DbalContributionStore`, `DbalEnrolmentStore`, `DbalCompletionStore` et `DbalDuelStore` : les quatre stockages de la
  bibliothèque sur Doctrine DBAL, les dates en UTC.
- `DbalCatalog` : des quêtes gardées dans la base de données comme définitions JSON, lues par le lecteur du bundle, les
  chaînes vérifiées à l'enregistrement et au retrait.
- `QuestsTables`, `QuestsSchemaListener` et `Rows` : les cinq tables — contributions, inscriptions, accomplissements,
  duels, définitions — ajoutées au schéma que l'ORM génère pour leur connexion, et l'insertion écrite une seule fois
  selon la plateforme.
- `TilttekQuestsBundle` et sa configuration `tilttek_quests` : le stockage — Doctrine DBAL ou mémoire —, la connexion,
  le fuseau horaire des dates des quêtes, le catalogue — configuré ou gardé dans la base de données, pas les deux —, les
  quêtes vérifiées à la compilation du conteneur, la durée de vie d'une offre de duel, le nom de la projection, le
  domaine de traduction et le type d'un membre pour le module de vie privée. Les stockages en mémoire sont étiquetés
  `kernel.reset`. La projection oublie sa reprise quand le noyau se réinitialise.
- `QuestsServices` : les stockages, le lecteur, le catalogue, `Quests`, `Duels`, et la projection étiquetée pour
  `tilttek/activity-bundle`, avec l'écouteur du schéma, les commandes et les fonctions Twig quand leurs composants sont
  installés.
- `DispatchingObserver`, `DuelWon` et `DuelExpired` : le `QuestCompleted` de la bibliothèque distribué tel quel, avec
  les récompenses et leurs membres, et les duels gagnés et expirés.
- `QuestsExtension` et les gabarits `list.html.twig` et `detail.html.twig`, avec leurs textes en anglais et en français.
- `ListCommand`, `ShowCommand` et `ExpireCommand` : `tilttek:quests:list`, `tilttek:quests:show` et
  `tilttek:quests:expire`.
- `QuestsPrivacy` : l'exportation et l'effacement d'un membre pour `phil/privacy-bundle`, module `tilttek_quests`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
