<!-- phil-dev source-sha256=2fe125bb48fd310a8c9d07a44fa18cd35407a8d9af40451b5ac9b827f2de12e2 -->
# Catalogues et licences

## Aucun catalogue n'est fourni

La bibliothèque n'importe le catalogue d'aucun éditeur et n'en fournit aucun : aucune unité, aucune valeur de points,
aucun détachement et aucune règle d'un jeu publié, et aucun importeur d'un catalogue au format d'un autre outil. Les
données d'armée d'un jeu appartiennent à son éditeur ; les copier exige la licence de l'éditeur, et des données
publiées sans licence n'en accordent aucune.

Le moteur est séparé des données : il connaît les unités, les options, les emplacements et les contraintes, jamais un
jeu. Les exemples, les tests et les corpus de fuzzing de la bibliothèque n'emploient qu'un catalogue inventé.

## Comment une application fournit son catalogue

Une application fournit ses catalogues par `Contract\CatalogueProvider::find($code)`, qui donne la version courante d'un
catalogue, ou null. Les données qui l'alimentent appartiennent à l'application :

- le catalogue de son propre jeu, ou d'un jeu que ses membres écrivent ;
- un catalogue inventé, pour les exemples, les tutoriels et les tests ;
- le catalogue d'un jeu sous une licence qu'elle détient et qui permet cet usage — une licence qu'elle a vérifiée, pour
  les unités, les points et les textes qu'elle montre.

Un catalogue se lit avec `Catalogue::fromArray()` ou `fromJson()` depuis les fichiers ou la base de données de
l'application, dans la forme que décrit [Catalogues](catalogues.md), et se garde une fois lu.
`Catalog\InMemoryCatalogueProvider` contient les catalogues qu'une application a lus au démarrage.

Une règle que les données d'un catalogue ne peuvent pas déclarer — le cas particulier d'un jeu — est un
`Contract\Constraint` propre à l'application, comme le montre
[Contraintes](constraints.md#les-contraintes-propres-à-une-application) : le catalogue reste des données.
