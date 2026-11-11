<!-- phil-dev source-sha256=12cc9b4cd03a955272bb0842b4b5a2e7b131966baa66d01b4de0ee7adf4128c7 -->
# La base de données

## Les tables

| Table | Une ligne par | Contient |
|---|---|---|
| `tilttek_collections_copy` | Identifiant d'exemplaire | L'objet, le propriétaire, la source, la clé, le moment, s'il est échangeable et s'il a été déplacé |
| `tilttek_collections_completion` | Ensemble et membre | Le moment où le membre a complété l'ensemble pour la première fois |
| `tilttek_collections_opening` | Clé d'ouverture | Le membre, le paquet, le germe, son engagement, le germe du client, les objets et les exemplaires sous forme de listes JSON, le moment |
| `tilttek_collections_choice` | Membre et nombre de faces | L'exemplaire que lance le membre |
| `tilttek_collections_daily` | Jour (`Y-m-d`) | L'objet et pourquoi : `rule`, `drawn` ou `chosen` |
| `tilttek_collections_definition` | Sorte et code | La définition en JSON, et sa position parmi celles de sa sorte |

Les dates sont écrites en UTC, à la seconde près.

## Concurrence

Un exemplaire, une complétion et une ouverture sont insérés par une instruction qui n'écrit rien quand la ligne est
déjà présente (`ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur
SQLite ; ailleurs, une insertion simple dont la violation d'unicité atteint l'appelant) : deux processus qui remettent
la même clé remettent un seul exemplaire. Un exemplaire change de mains par une mise à jour qui nomme son propriétaire
attendu : deux échanges du même exemplaire ne peuvent donc pas réussir tous les deux. Exécutez un échange dans une
transaction de la connexion, comme le fait `tilttek/exchange-bundle`, pour qu'un exemplaire refusé annule tout
l'échange.

## Le catalogue dans la base de données

Avec `catalog: doctrine`, `DbalCatalog` sert le catalogue à partir de `tilttek_collections_definition` et constitue le
service `WritableCatalog` : `define(DefinitionKind::Item, 'pirate', [...])` ajoute ou remplace une définition,
`remove(DefinitionKind::Item, 'pirate')` en supprime une, et le catalogue qui en résulte est vérifié en entier avant
toute écriture — un objet dont la rareté a disparu, un paquet qui n'a rien à tirer, est refusé. Une définition garde la
position de sa première définition, pour que les raretés gardent leur ordre. Définissez un ensemble avant les objets
qui lui appartiennent ; un ensemble encore sans objets n'est jamais complet.
