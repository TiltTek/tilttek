<!-- phil-dev source-sha256=eb8c45c101b14914cdd345b7ff69649e75cf27b4981834827065d641fa26b02f -->
# Les échanges en base de données

## Les tables

| Table | Contenu |
|---|---|
| `tilttek_exchange_reward` | Le catalogue, avec `catalog: doctrine` : code, prix, devise, stock, période, limite, conditions en liste JSON |
| `tilttek_exchange_reservation` | Une ligne par réservation, sa clé comme clé primaire |
| `tilttek_exchange_stock_lock` | Une ligne par récompense avec un stock ou une limite, mise à jour par chaque réservation pour tenir son verrou |
| `tilttek_exchange_transfer` | Le journal des transferts, sa position en auto-incrément, sa clé unique |
| `tilttek_exchange_offer` | Une ligne par offre, sa clé comme clé primaire |
| `tilttek_exchange_offer_item` | Les objets de chaque offre, dans l'ordre de chaque côté |
| `tilttek_exchange_hold` | Une ligne par objet que retient une offre ouverte, l'objet comme clé primaire |

`ExchangeSchemaListener` les ajoute au schéma que génère l'ORM pour la connexion des échanges, la table du catalogue
seulement avec `catalog: doctrine`. Ainsi, `doctrine:migrations:diff` les écrit et `doctrine:schema:validate` ne demande
pas de les supprimer.

## Écrit une seule fois, sous verrou

- La réservation d'une récompense avec un stock ou une limite s'exécute dans une transaction qui met d'abord à jour la
  ligne de verrou de la récompense. Elle tient ainsi le verrou en écriture de la ligne (ou, sur SQLite, le verrou de
  toute la base) jusqu'à la fin de la transaction : deux réservations de la même récompense comptent les unités
  retenues l'une après l'autre.
- Les lignes qui doivent être écrites une seule fois sont insérées par une instruction qui n'écrit rien quand la clé
  existe : `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur
  SQLite. Ailleurs, c'est une insertion simple, dont la violation d'unicité parvient à l'appelant.
- Une offre retient chaque objet offert en l'insérant dans `tilttek_exchange_hold` : un objet qu'une autre offre ouverte
  retient est refusé, et la transaction de l'offre est annulée.
- Une réservation quitte l'état `paying` puis l'état `reserved`, et une offre l'état `open`, par une mise à jour qui
  nomme l'état quitté : un seul processus la fait changer. Une réservation en paiement est écrite avant la dépense de
  ses points et devient réservée une fois qu'ils le sont.

Les moments sont des colonnes du type `datetime_utc_immutable`, et leurs paramètres sont liés avec ce type : un moment
de n'importe quel fuseau est stocké en UTC, à la seconde, comparé en UTC et relu en UTC.

## Une seule transaction

`DbalTransaction` exécute les étapes d'une acceptation et d'une contre-offre dans une seule transaction de la
connexion. Le registre de `tilttek/points-bundle` y prend part quand il est sur la même connexion, tout comme un
inventaire qui y est gardé.
