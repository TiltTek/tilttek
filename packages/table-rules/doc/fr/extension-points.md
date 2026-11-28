<!-- phil-dev source-sha256=95df27c09fb6e25dc25886d3445dbc2b893f92fb5ff0415954c0dd03d53d5f2b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les livres de règles et leurs versions | Implémentez `Contract\RulebookStore`, et passez-le à `TableRules`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\TableRulesObserver`, et passez-le à `TableRules`. |
| Vérifier les variantes contre un système de jeu | Passez `Catalogue\SystemVariantCatalogue` sur ses systèmes de `tilttek/rpg-systems` à `TableRules`, ou implémentez `Contract\VariantCatalogue` pour des variantes propres. |
| Donner l'heure | Passez une `ClockInterface` PSR-20. |
| Nommer les joueurs d'une table | Passez-les à `TableRules::roster()` ; le livre de règles ne connaît que ceux qui ont répondu. |
| Dire qui peut publier les règles, et qu'un joueur ne répond que pour lui-même | Vérifiez-le dans l'application avant d'appeler `TableRules`. |

## Le magasin

`Contract\RulebookStore` garde le `Rulebook` de chaque table à une révision, et chaque `RulebookVersion` publiée :

- `rulebook()` donne le livre de règles d'une table, ou null quand aucun n'a jamais été gardé ;
- `version()` donne une version d'une table par son numéro, ou null ;
- `save()` garde le livre de règles à la révision suivante, avec la version qui vient d'être publiée quand on en donne
  une — les deux ou aucune — et retourne le livre de règles gardé. Un livre de règles à la révision 0 est nouveau, et
  le magasin n'en a aucun pour la table ; tout autre n'est gardé que si le magasin le détient à cette révision, et
  `RulesConflict` est levée sinon, rien n'étant gardé.

Deux joueurs qui acceptent en même temps lisent tous deux la révision 7 : la première sauvegarde garde la révision 8,
et la seconde est refusée avec `RulesConflict` ; l'application relit et répond de nouveau.

Le livre de règles contient lui-même la version courante, pour que les appels courants lisent un seul enregistrement ;
les versions antérieures se lisent une à la fois, pour les comparer. `Rulebook` contient des chaînes et des entiers — la
table, la base de référence, la révision — la version courante et jusqu'à 500 `Acceptance`, chacune un joueur, un
numéro de version, une `Decision` dont les valeurs sont ses cas de chaîne, et un moment. Une `RulebookVersion` contient
des entiers, des chaînes, un moment, un booléen et jusqu'à 200 `Rule` de quatre chaînes ; `toArray()` donne ses données
simples.

`Store\InMemoryRulebookStore` les garde en mémoire, tant que l'objet dure : il sert aux tests. Une application les
garde dans sa base de données, les versions à part du livre de règles.

## L'observateur et ses événements

`Contract\TableRulesObserver::notify()` est informé de chaque fait une fois le livre de règles gardé, par identifiants
seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\RulebookPublished` | Une nouvelle version a été publiée. | son numéro, et si elle exige une acceptation |
| `Event\RulesAccepted` | Un joueur a accepté la version qui fait foi. | le joueur et la version |
| `Event\RulesDeclined` | Un joueur a refusé la version qui fait foi. | le joueur et la version |
| `Event\PlayerForgotten` | La décision d'un joueur a été oubliée. | le joueur |

Chaque événement implémente `Event\RulesEvent` : la table, un identifiant. Une réponse qui ne change rien n'envoie
aucun événement. Une application en tire un message — par Messenger, avec les seuls identifiants — et lit le reste dans
le magasin : un avis à chaque joueur que les règles ont changé, une marque sur la page de la table une fois que tout le
monde a accepté.

## Le catalogue de variantes

`Contract\VariantCatalogue::has()` dit si un système de jeu offre une variante ; le système est null quand la version
n'en nomme aucun. `Catalogue\SystemVariantCatalogue` l'appuie sur les variantes de règles des systèmes de
`tilttek/rpg-systems` ; une application dont les variantes vivent ailleurs l'implémente sur ses propres données. Sans
catalogue, tout identifiant est une variante.

## L'horloge

`TableRules` lit l'heure de la `ClockInterface` PSR-20 qu'on lui donne, et de rien d'autre : le moment où une version
est publiée et le moment où un joueur répond.
