<!-- phil-dev source-sha256=1217bd156a6f6ce88f3b3b4f1cb2da96ef3414fa83f1e4b3f07e37c409fc7a99 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder le catalogue | Implémentez `Contract\CatalogStore`, et passez-la aux services. |
| Alimenter le catalogue | Implémentez `Contract\EntrySource`, et passez-la à `Catalog::feed()`. |
| Identifier une boîte que le catalogue ne connaît pas | Implémentez `Contract\BarcodeResolver`, et passez-la à `Catalog`. |
| Informer les membres des errata et des corrections | Implémentez `Contract\CatalogObserver`, et passez-la à `Catalog` et à `Corrections`. |
| Montrer l'expérience d'un critique | Implémentez `Contract\PlayHistory` et `Contract\ReviewSystem`, et passez-les à `Reviews`. |
| Lire un autre fichier de collection | Donnez ses en-têtes à `Import\Columns`, et ses bornes à `Import\Limits`. |

## L'entrepôt

`Contract\CatalogStore` garde les entrées, chacune à une version : `entry()` en lit une, `save()` la garde à la version
suivante et la renvoie. Une entrée à la version 0 est nouvelle, et l'entrepôt n'a aucune entrée de son identifiant ;
toute autre n'est gardée que si l'entrepôt la détient à cette version, sinon `CatalogConflict` est levée et rien n'est
gardé. `save()` indexe aussi les identifiants et l'origine de l'entrée, et lève `CatalogRefused` quand une autre entrée
en détient un, sans rien garder. Les corrections sont gardées à une version de la même façon. L'entrepôt garde aussi les
codes-barres, les liens par clé, les bulletins par entrée et par membre, et l'historique, le plus récent d'abord.

`Store\InMemoryCatalogStore` garde tout en mémoire, aussi longtemps que l'objet vit : il sert aux tests. Une
application garde le catalogue dans sa base de données ; chaque valeur ne contient que des chaînes, des nombres, des
énumérations et des dates.

## Les sources

`Contract\EntrySource` donne son nom et ses entrées. `Catalog::feed()` les lit jusqu'à sa limite et synchronise chacune,
comme l'expliquent [les entrées et les sources](catalogue.md#envoyee-par-une-source). Une source écrite sur un service
tiers est un adaptateur de l'application et suit les conditions de ce service : la bibliothèque garde ce que la source
envoie, et une source de BoardGameGeek ou de RPGGeek n'envoie que l'identifiant.

## Le résolveur de codes-barres

`Contract\BarcodeResolver::resolve()` renvoie l'identifiant d'un jeu dans une base ouverte pour un code-barres, ou
`null`. On l'interroge une fois par code-barres : `Catalog::identify()` garde la réponse quand une entrée détient
l'identifiant.

## L'observateur

`Contract\CatalogObserver` est informé, par identifiants seulement, d'un erratum publié, d'une correction proposée et
d'une correction tranchée. Une application en tire une notification ou un message, et lit le reste dans le catalogue,
si bien qu'un message ne porte aucun renseignement personnel.

## Les parties et les critiques

`Contract\PlayHistory` compte les parties d'une entrée qu'un membre a jouées et animées, d'après les parties que
l'application enregistre. `Contract\ReviewSystem` garde l'`Experience` d'une critique à côté d'elle, dans le système de
critiques de l'application, et la rend.

## Sûr en mode worker

Les services et tout ce qu'ils utilisent ne gardent rien entre les appels : ils peuvent vivre aussi longtemps qu'un
*worker* et servir plusieurs sites d'une même application à la fois, l'entrepôt décidant quel catalogue il lit.
