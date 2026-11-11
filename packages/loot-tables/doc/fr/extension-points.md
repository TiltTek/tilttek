<!-- phil-dev source-sha256=0bb322d055cbeee1123a62f5cdb1bac767f833e586dddfde1c65a780df1af725 -->
# Points d'extension

La bibliothèque est faite de valeurs et d'un seul service ; elle n'a aucune interface que l'application doive
implémenter.

## Le catalogue

La provenance des tables et des boîtes appartient à l'application : construites dans le code, lues par
`CatalogReader` dans un document YAML ou JSON, ou tirées d'une base de données, chaque fois sous forme de
`LootCatalog`.

## Le hasard

`PendingDraw::open()` reçoit le `Random\Randomizer` dont le germe est tiré : un `Random\Engine\Secure` en production,
un moteur avec germe dans les tests.

## Le lanceur

`LootDrawer` reçoit le `FairRoller` de `tilttek/dice`, qui reçoit son `Roller`, ses limites et ses dés personnalisés :
une application avec d'autres limites passe le sien.

## Où un tirage est gardé

Le germe du tirage en attente, son résultat et sa preuve sont gardés par l'application, liés à ce qui a été vendu ;
`tilttek/loot-tables-bundle` les garde dans Doctrine.
