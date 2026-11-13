<!-- phil-dev source-sha256=884ad25e1f057b1930c3c84bd2bc2ab6628c016cab72e4bb9e387893a8cc5cd3 -->
# Catalogues et licences

## Aucun catalogue n'est fourni

La bibliothèque n'importe le catalogue d'aucun éditeur et n'en fournit aucun : aucune liste de cartes, aucun format ni
aucune liste tabou d'un jeu publié, et aucun importeur d'un tel catalogue. Les cartes d'un jeu, leurs noms et leurs
textes, et les listes des formats d'un jeu sont des données que leur éditeur possède ; les copier exige la licence de
l'éditeur, et un catalogue publié sans licence n'en accorde aucune.

Les exemples, les tests et les corpus de fuzzing de la bibliothèque n'emploient que des cartes et des formats inventés.

## Comment une application fournit son catalogue

Une application fournit ses cartes et ses formats par deux ports :

- `Contract\CardCatalog::find($id)` donne une `Card` — son identifiant, son nom et, quand il diffère de celui du
  format, son nombre d'exemplaires — ou null ;
- `Contract\FormatCatalog::find($code)` donne un `Format`, avec ses listes tabou, ou null.

Les données qui les alimentent appartiennent à l'application :

- des cartes qu'elle a écrites elle-même, pour son propre jeu ou un jeu de ses membres ;
- des cartes inventées, pour les exemples, les tutoriels et les tests ;
- des cartes sous une licence qu'elle détient et qui permet cet usage — une licence qu'elle a vérifiée, pour les cartes
  et les textes qu'elle montre.

Un `Format` se lit depuis la configuration ou les fichiers de l'application avec `Format::fromArray()` ou `fromJson()` ;
les cartes viennent de sa propre base de données ou de ses fichiers. La bibliothèque n'a besoin que des identifiants,
des noms et des nombres d'exemplaires : le texte, l'illustration et les règles d'une carte restent à l'application, qui
montre ce que sa licence permet.
