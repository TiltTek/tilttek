<!-- phil-dev source-sha256=6d7f966ca21584db6315cdc3bfc904a33f0ddcea2d39baa0ab9c751208a838fa -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Generator\Exception\GeneratorException`.

| Exception | Quand | Message |
|---|---|---|
| `InvalidDefinition` | Un document, une liste, une entrée, une attribution, une chaîne de Markov, un générateur, un état, une limite ou un tirage de mauvaise forme ; un fichier ou une table inconnus ; une langue sans grammaire ; une grammaire ou des tables qu'un importateur refuse ; un tableau qui n'est pas une carte, donné à `MapSchema` | `inn.yaml > lists > weapon > 2: an entry has a text, and its weight is a whole number`, `tracery > origin: the symbol "nope" does not exist` |
| `InvalidCatalog` | Des gabarits qui ne compilent pas, sortent du bac à sable, ou nomment des listes, des étiquettes ou des générateurs inexistants ; `problems()` les énumère | Un `Problem` par ligne : `inn:template:0: the list "nope" does not exist` |
| `UnknownGenerator` | Un générateur demandé par un nom que le catalogue n'a pas | `No generator is named "npc".` |
| `UnknownList` | Une liste nommée par une variable que le catalogue n'a pas | `No list is named "weapon".` |
| `UnknownTag` | Un filtre sur une étiquette qu'aucune entrée de la liste ne porte | `No entry of the list "weapon" has the tag "era".` |
| `ExhaustedList` | Un tirage simple quand il ne reste aucune entrée, ou quand une liste de Markov n'a pu inventer aucun mot nouveau | `No entry of the list "weapon" is left to pick.`, `The list "name.elf" invented no word that fits in 50 attempts.` |
| `LimitExceeded` | Un tirage qui dépasse l'une des limites, ou une valeur d'état trop longue pour être rapportée | `The limit of 1000 picks per result is exceeded.`, `The limit of 10000 steps per result is exceeded.`, `The limit of 16 megabytes per result is exceeded.`, `The limit of 65536 bytes made by "replace" is exceeded.`, `The limit of 1024 characters per state value is exceeded.` |
| `GenerationFailed` | Un gabarit qui échoue pendant son rendu : une variable ou un champ non définis, une expression de dés qui ne se lance pas, un accès que le bac à sable refuse | `The template "inn:field:name" failed: Variable "keeper" does not exist in "inn:field:name" at line 1.` |

Un gabarit est nommé par son générateur et son emplacement : `name:template:0`, `name:var:gender`,
`name:field:keeper`, `name:cell`, `name:state:weather:start`, `name:state:weather:next`. Une exception de la
bibliothèque levée dans un gabarit (`UnknownList`, `ExhaustedList`, `LimitExceeded`) parvient à l'appelant telle quelle,
sans être enveloppée dans les erreurs de Twig.
