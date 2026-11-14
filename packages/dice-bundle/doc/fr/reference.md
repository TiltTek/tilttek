<!-- phil-dev source-sha256=fea3dceab336178781b5b58d4e174d9f09a5fd04090b33046da45cebb84bcfd0 -->
# Référence

| Type | Rôle |
|---|---|
| `TilttekDiceBundle` | La configuration, et le journal et les macros associés pour Doctrine |
| `Infrastructure\DependencyInjection\DiceServices` | Les services, et les fabriques des dés personnalisés, des notations et des dialectes |
| `Application\DiceRolls` | Le service que l'application appelle |
| `Application\Replay` | Un lancer enregistré relancé, et s'il correspond |
| `Application\DiceMacros` | Les macros des sites, des tables et des membres, définies et résolues |
| `Application\MacroShelves` | Le site, la table et le membre dont une entrée nomme les macros |
| `Application\MacroResolution`, `MacroSession` | Interne : les macros d'une entrée cherchées, développées et comptées |
| `Application\Port\DiceAppearance` | Le point d'extension qui habille les dés d'un membre |
| `Application\DieAppearance`, `NoDiceAppearance` | Une apparence, avec son encre lisible ; des dés ordinaires |
| `Application\Contrast` | Le contraste de deux couleurs tel que le mesure WCAG 2, et l'encre qui se lit sur une face |
| `Application\Event\RollRecorded` | Un lancer rejouable gardé dans le journal, par identifiants |
| `Application\Exception\UnknownRoll` | Le rejeu d'un lancer que le journal ne contient pas |
| `Application\Exception\InvalidMacro` | Une macro dont le nom n'en est pas un, ou dont le niveau n'a pas de propriétaire |
| `Domain\MacroLevel` | Site, table ou membre |
| `Domain\Model\DiceMacro` | Une ligne des macros |
| `Domain\Repository\DiceMacroRepository` | Le port des macros |
| `Infrastructure\Doctrine\DoctrineDiceMacroRepository`, `Infrastructure\Memory\InMemoryDiceMacroRepository` | Les macros dans Doctrine, en mémoire |
| `Domain\Model\RecordedRoll` | Une ligne du journal |
| `Domain\Repository\RecordedRollRepository` | Le port du journal |
| `Infrastructure\Doctrine\DoctrineRecordedRollRepository` | Le journal dans Doctrine |
| `Infrastructure\Memory\InMemoryRecordedRollRepository` | Le journal en mémoire |
| `Infrastructure\Validator\DiceExpression`, `DiceExpressionValidator` | La contrainte |
| `Infrastructure\Form\DiceExpressionType` | Le champ |
| `Infrastructure\Twig\DiceExtension` | Les filtres et fonctions Twig, et la vue d'un lancer |
