<!-- phil-dev source-sha256=aab4b8e669ebb001a293f61716e229d5940761d44678907792de3f8b7518c2c3 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Déclarer les tables du système | Écrivez des documents du [format](format.md) et lisez-les avec `Format\TableReader`, ou construisez des `ReferenceTable` en code. |
| Garder les tables | Gardez les documents, ou les lignes de votre base de données, et construisez-en le `TableCatalogue` ; mettez-le en cache entre les requêtes. |
| Réagir à un lancer | Implémentez `Contract\TableObserver` et passez-le à `Rolling\TableRoller`. |
| Fournir le hasard | Passez un `Random\Randomizer` à chaque lancer : un moteur sécurisé en production, un germe dans les tests. |
| Borner ou étendre les dés | Passez à `Rolling\TableRoller` un `DiceRoller` de `tilttek/dice`, avec ses limites et ses dés personnalisés. |
| Montrer d'où vient une table | Donnez `TableCatalogue::provenances()` à l'`AttributionRenderer` de `tilttek/rpg-systems`. |
| Rechercher dans un corpus plus grand | Indexez les noms et les cellules de texte dans un moteur de recherche, en gardant la clé de la table et la position de la ligne. |
| Dire qui peut voir une table | Filtrez le catalogue dans l'application avant de le montrer ou d'y chercher. |

## L'observateur et son événement

`Contract\TableObserver::notify()` apprend chaque lancer une fois sa ligne trouvée, sous la forme d'un
`Event\TableRolled` : la clé de la table, la position de la ligne, le total et si le dé a été entré. Une application
envoie un message à partir de lui, avec ces seuls identifiants, et lit le reste dans son catalogue.
[Lancer](rolling.md#lobservateur) donne les détails.

## Les données du système

Les tables sont des données simples : la bibliothèque n'exécute aucun code du site. Ce qu'une table peut contenir est
une liste fermée — des textes, des entiers, des dés —, et ce que fait une consultation aussi — une clé, une plage, un dé,
un modificateur, une borne aux extrémités. Une règle hors de la liste reste dans l'application, qui lit la ligne et
agit : un résultat qui renvoie à une autre table en nomme la clé dans une cellule de texte, et l'application lance
ensuite celle-là.

## La frontière avec le générateur

`tilttek/generator` tire du contenu de listes pondérées et de gabarits Twig — noms, auberges, rumeurs — et en façonne le
texte. Une table de référence rend la ligne que nomme une valeur ou une clé, comme la source l'imprime, avec sa source
et sa page ; elle ne produit aucun rendu. Une table aléatoire destinée à fabriquer du contenu appartient au
générateur ; une table qu'un maître de jeu consulte ou lance pour lire une règle appartient ici.

## Les services

`TableReader`, `TableRoller` et `TableSearch` sont en lecture seule et ne gardent rien entre deux appels : une seule
instance sert toutes les requêtes d'un *worker*. Un `TableCatalogue` et ses tables sont des valeurs immuables que les
requêtes partagent.
