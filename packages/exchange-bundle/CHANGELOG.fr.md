<!-- phil-dev source-sha256=acc6c754e5ce810b63580bbf5b30e3773484475f719428dcc9f9c47fa6f861d3 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/exchange-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.3, la version publiée le 2026-11-24, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les prérequis de développement acceptent `phil/privacy-bundle` 0.2, sa dernière version mineure publiée, et aucune
  version antérieure, pour que le paquet s'installe seul à côté de la version publiée de ce bundle.
- Requiert la dernière version mineure de chaque dépendance : `doctrine/orm` 3.8, `doctrine/dbal` 4.6,
  `doctrine/doctrine-bundle` 3.4.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `ExchangeTables` et `ExchangeSchemaListener` : le catalogue, les réservations avec une ligne de verrou par récompense,
  le journal des transferts, les offres avec leurs objets et les retenues des objets offerts, ajoutés au schéma que
  l'ORM génère pour la connexion des échanges.
- `Rows` : l'insertion qui n'écrit rien quand sa clé est là — `ON CONFLICT DO NOTHING`, `INSERT IGNORE`,
  `INSERT OR IGNORE` — et les colonnes relues.
- `DbalCatalog`, `DbalReservationStore`, `DbalTransferLog`, `DbalOfferStore` et `DbalTransaction` : les stockages de
  `tilttek/exchange` sur une connexion DBAL, le stock compté sous la ligne de verrou de la récompense, les objets
  retenus par la clé primaire de leur table, et les étapes d'un échange en une seule transaction ; les moments dans des
  colonnes et des paramètres du type `datetime_utc_immutable`, enregistrés, comparés et relus en UTC ; testés sur
  SQLite, MySQL 8.4 et PostgreSQL 17. `DbalReservationStore::transition()` fait passer une réservation du statut où elle
  se trouve par une mise à jour qui le nomme — de `paying` à `reserved` une fois ses points payés, d'un statut ouvert à
  un statut réglé — ; `discard()` supprime une réservation en paiement, et `due()` renvoie les réservations en paiement
  et réservées qui ont dépassé leur durée de vie.
- La configuration `tilttek_exchange` : le stockage (`doctrine` ou `memory`), la connexion, le catalogue
  (`configuration` ou `doctrine`) et les récompenses, la durée de vie d'une réservation, les services des conditions et
  des dates d'adhésion, le fuseau horaire, les transferts et les échanges avec leur allocation et leur délai pour les
  nouveaux comptes, l'inventaire et la durée de vie d'une offre, et le type de la référence d'un membre ; les
  récompenses, les durées, les fuseaux horaires et les services manquants refusés à la compilation du conteneur. Les
  stockages en mémoire sont étiquetés `kernel.reset`.
- `PointsHold` : le contrat des points d'une récompense réservée — retenus, confirmés à la livraison, libérés à une
  annulation, à un refus ou à l'expiration — avec les points retenus et le solde disponible ; `SpendingPointsHold`, son
  implémentation sur `Points`, dépense tout de suite et rembourse sous la clé de dépense de la réservation, comme le
  fait `Redemptions`.
- `ExchangeServices` : `Redemptions`, l'alias `PointsHold`, `Transfers` et `Trades` avec leurs garde-fous, les stockages
  et leurs alias, l'effacement, et les commandes et les formulaires quand leurs composants sont installés.
- `DispatchingAnnouncer` : les événements des échanges distribués au répartiteur d'événements de l'application.
- Les commandes `tilttek:exchange:expire` et `tilttek:exchange:transfers`.
- `RedeemRewardType`, `TransferPointsType` et `OfferType` : les formulaires qui réservent une récompense, donnent des
  points et font une offre ou une contre-offre.
- `ExchangePrivacy` : l'exportation et l'effacement des réservations, des transferts et des offres d'un membre pour
  `phil/privacy-bundle`.
- La recette Flex dans `recipe/0.1` : le bundle et `config/packages/tilttek_exchange.yaml`, avec les transferts et les
  échanges désactivés.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
