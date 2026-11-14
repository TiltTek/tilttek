<!-- phil-dev source-sha256=680d5083a788bffbc67b930d0c937230db9ddf5f826e59ec79ed618aaa0f0e17 -->
# Référence

## Le générateur

| Type | Rôle |
|---|---|
| `Generator` | La bibliothèque en un seul objet : vérifie un catalogue à sa construction et y fait des tirages. |
| `Draw` | Un tirage demandé : un nombre, l'unicité, un germe et l'état que le dernier tirage a laissé. |
| `DrawResult` | Les résultats d'un tirage, le nombre demandé, s'il est incomplet, l'état où reste un générateur à mémoire, et les attributions que les résultats ont utilisées. |
| `Tables` | Les tables aléatoires que la bibliothèque livre dans `tables/` : leurs noms, leurs langues, et le répertoire et les fichiers de chaque langue. |
| `Limits` | Les bornes de profondeur, de tirages, de temps, de tentatives, de résultats, de cases, de pas, d'octets et de mémoire. |
| `MapSchema` | La forme JSON d'une carte, versionnée, et son schéma JSON. |
| `Timer`, `SystemTimer` | L'horloge monotone en millisecondes de la limite de temps. |

## Définitions (`Tilttek\Generator\Definition`)

| Type | Rôle |
|---|---|
| `Catalog` | Les listes et les générateurs par nom. |
| `EntryList` | Une liste : son nom, ses entrées et sa chaîne de Markov, si elle en a une ; si une entrée porte une étiquette. |
| `MarkovChain` | Comment une liste invente des mots : son ordre, son mot le plus court et le plus long, et si les entrées sortent. |
| `Entry` | Une entrée : son texte, son poids, ses champs, ses étiquettes et son attribution ; se lit comme un tableau dans un gabarit. |
| `GeneratorDefinition` | Un générateur : son nom, son corps, ses variables, sa langue, son état et son attribution. |
| `Attribution` | D'où vient un texte : sa source, sa licence et l'attribution à afficher. |
| `StateValue` | Une valeur d'un état : son gabarit de départ et son gabarit suivant. |
| `Body` | Ce que retourne un générateur ; `TextBody`, `ObjectBody`, `GridBody` et `GraphBody`. |
| `Template` | Un gabarit pondéré d'un `TextBody`. |
| `Field` | Ce qui remplit un champ ou une case : `TemplateField` ou `GenerateField`. |
| `MapLayout` | `square` ou `hex`. |

## Chargement (`Tilttek\Generator\Loading`)

| Type | Rôle |
|---|---|
| `Reader` | Des documents décodés transformés en catalogue, avec l'emplacement de chaque erreur ; `isName()`. |
| `FileLoader` | Des fichiers et des répertoires YAML et JSON transformés en documents et en catalogue. |

## Importations (`Tilttek\Generator\Import`)

| Type | Rôle |
|---|---|
| `TraceryImporter` | Une grammaire Tracery transformée en listes et en générateurs. |
| `PerchanceImporter` | Des tables dans le sous-ensemble de la syntaxe de Perchance sans JavaScript transformées en listes et en générateurs. |
| `Imported` | Ce qu'une importation a fait : son document, le générateur par où commencer, et le document lu en catalogue. |
| `TemplateText` | Interne : le texte brut et les chaînes écrites dans la syntaxe de Twig. |
| `Tracery\RuleParser`, `Tracery\FreeVariables`, `Tracery\Text`, `Tracery\Tag`, `Tracery\Action` | Interne : les règles d'une grammaire lues, et les variables que reçoit chaque générateur. |
| `Perchance\TableParser`, `Perchance\Branch`, `Perchance\Choice`, `Perchance\Reference`, `Perchance\Range`, `Perchance\Dice`, `Perchance\Text` | Interne : les sections et les entrées des tables lues. |

## Grammaire (`Tilttek\Generator\Grammar`)

| Type | Rôle |
|---|---|
| `Grammar` | Les règles d'une langue derrière les filtres. |
| `French`, `English`, `Spanish`, `German`, `Italian`, `Portuguese` | Les grammaires intégrées. |
| `Grammars` | Les grammaires par locale ou par langue, celles qui sont intégrées et celles d'une application. |
| `ArticleKind` | `definite`, `indefinite`, `partitive`, `demonstrative`. |
| `Gender` | `m`, `f`, `n`. |

## Moteur (`Tilttek\Generator\Engine`)

| Type | Rôle |
|---|---|
| `Problem` | Un défaut d'un catalogue, et son gabarit. |
| `Producer` | Interne : compile les gabarits, les vérifie et produit le rendu des résultats. |
| `Run` | Interne : le hasard, les tirages, les pas, la mémoire, le temps et les langues d'un résultat. |
| `StepVisitor`, `StepNode` | Interne : le pas compilé à chaque tour d'une boucle et après chaque affectation d'un gabarit. |
| `MarkovModel` | Interne : ce qu'une liste de Markov a appris de ses entrées, et les mots qu'elle écrit. |
| `SandboxPolicy` | Interne : ce qu'un gabarit peut utiliser. |
| `ReferenceCollector` | Interne : les noms et les fonctionnalités du bac à sable qu'utilise un gabarit. |
| `GeneratorExtension` | Interne : les fonctions et les filtres des gabarits, `replace` et `join` bornés, et les pas. |

## Exceptions (`Tilttek\Generator\Exception`)

`GeneratorException`, `InvalidDefinition`, `InvalidCatalog`, `UnknownGenerator`, `UnknownList`, `UnknownTag`,
`ExhaustedList`, `LimitExceeded`, `GenerationFailed` ; voyez [Exceptions](exceptions.md).
