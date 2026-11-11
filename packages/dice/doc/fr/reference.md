<!-- phil-dev source-sha256=9be77f704e1f0958ade3750a3c5cbf85d592f0ea2c1b0d4645f16fe958b310b9 -->
# Référence

| Type | Rôle |
|---|---|
| `DiceRoller` | La bibliothèque en un seul objet : analyser, lire DiceParser, écrire, traduire, lancer, lancer de façon vérifiable, lancer pour rejouer |
| `Limits` | Les bornes contre les abus |
| `Parser\DiceParser` | Une entrée en un `Pool`, dans une notation, dans les limites |
| `Parser\Lexer`, `Token`, `TokenType` | L'entrée lue caractère par caractère |
| `Reader\RolisteamReader` | Une commande du DiceParser de Rolisteam en un `Pool` ([le DiceParser de Rolisteam](rolisteam.md)) |
| `Reader\RolisteamRewriter` | Interne : les jetons de la commande réécrits dans la notation de la bibliothèque, et les positions gardées |
| `Contract\Macros` | Les macros que nomme une entrée, `$name` ([macros](macros.md)) |
| `Expression\Pool` | Une instruction ou plus et leurs commentaires ; `canonical()` |
| `Expression\Node` | Un nœud : `Constant`, `Dice`, `ComputedDice`, `ValueList`, `PreviousDice`, `Operation`, `Negation`, `FunctionCall`, `Reference`, `InstructionDice`, `Group`, `TextList`, `Sentence`, `Conditional`, `Choice` |
| `Expression\Dice` | Un nombre, un dé et ses modificateurs |
| `Expression\ComputedDice` | Des dés dont le nombre ou les faces sont lancés d'abord |
| `Expression\ValueList`, `PreviousDice` | Des valeurs prises comme des dés ; les dés de l'ensemble précédent, jugés de nouveau |
| `Expression\Operator` | `+ - * / % ^` et leur priorité |
| `Expression\FunctionCall`, `MathFunction` | `floor ceil round abs min max repeat` et leurs arguments |
| `Expression\Reference`, `InstructionDice` | Le résultat d'une instruction précédente, ou son élément ; les dés d'instructions, liés ou fusionnés |
| `Expression\Group` | Des expressions dont les totaux sont jugés comme des dés |
| `Expression\TextList`, `ListItem` | Des éléments tirés d'une liste de textes, par poids ou par intervalle |
| `Expression\Sentence`, `Placeholder`, `PlaceholderKind` | Un texte et les marqueurs remplacés au moment du lancer |
| `Expression\Conditional`, `Choice`, `SwitchCase` | `if` et `switch`, leurs conditions et leurs branches |
| `Expression\Modifiers` | Les modificateurs d'un ensemble de dés, leur ordre et leurs vérifications |
| `Expression\Reroll`, `Repetition`, `Explosion`, `Transform`, `Selection`, `SelectionMode`, `Occurrences`, `Critical`, `Look`, `Sort` | Les modificateurs |
| `Expression\Validator`, `Comparison`, `Comparator`, `Range`, `Modulo`, `Composite`, `Junction` | Les valeurs auxquelles s'applique un modificateur |
| `Expression\Condition`, `Scope` | Un validateur et ce contre quoi il est vérifié |
| `Die\DieType`, `NumericDie` | Ce qu'est un dé ; les dés qui acceptent des modificateurs et lancent leurs faces |
| `Die\StandardDie`, `FudgeDie`, `RangeDie`, `CustomDie` | Les dés numérotés, Fudge, sur un intervalle et personnalisés |
| `Custom\Face`, `Shape`, `DieDefinition`, `CustomDice` | Les dés personnalisés déclarés par l'application |
| `Notation\Notation`, `Notations` | Les lettres d'une langue |
| `Notation\ExpressionWriter`, `NotationWriter` | Une expression réécrite, canonique ou dans une langue |
| `Rolling\Roller` | Un lot lancé avec le hasard et l'arrondi donnés |
| `Rolling\Arithmetic` | L'arithmétique entière, les divisions arrondies selon un `RoundingMode` |
| `Result\PoolResult`, `ExpressionResult`, `NodeResult` | Un lancer, ses sorties, ses totaux et son arrondi |
| `Result\DiceResult`, `ConstantResult`, `OperationResult`, `NegationResult`, `FunctionResult` | Le résultat de chaque nœud qui donne un nombre |
| `Result\TextResult`, `ListResult`, `BranchResult`, `ReferenceResult` | Un texte, une liste, une branche, une référence |
| `Result\Outputs`, `Notice` | Les sorties écrites ou aplaties ; ce qu'un lancer n'a pas pu faire exactement |
| `Result\RolledDie`, `DieOrigin`, `Mark` | Un dé tel qu'il est tombé, et ce qu'il est devenu |
| `Fairness\Seed`, `Commitment` | Un germe et son SHA-256 |
| `Fairness\FairRoller`, `VerifiableRoll` | Les lancers vérifiables et leur vérification |
| `Replay\Replayer`, `ReplayableRoll` | Les lancers rejoués dé pour dé |
| `Dialect\Dialect`, `Dialects` | La notation d'une table virtuelle, et le registre |
| `Dialect\Roll20`, `FoundryVtt`, `Avrae`, `RolisteamDiceParser` | Les dialectes intégrés |
| `Dialect\Translation`, `Unsupported` | Un lot dans un dialecte, ce qu'il n'a pas pu écrire, et ses avis |
| `Exception\DiceException` et ses cinq exceptions | [Exceptions](exceptions.md) |
