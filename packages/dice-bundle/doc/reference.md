# Reference

| Type | Role |
|---|---|
| `TilttekDiceBundle` | The configuration, and the journal and the macros mapped for Doctrine |
| `Infrastructure\DependencyInjection\DiceServices` | The services, and the factories of the custom dice, notations and dialects |
| `Application\DiceRolls` | The service the application calls |
| `Application\Replay` | A recorded roll rolled again, and whether it matches |
| `Application\DiceMacros` | The macros of the sites, the tables and the members, defined and resolved |
| `Application\MacroShelves` | The site, the table and the member whose macros an input names |
| `Application\MacroResolution`, `MacroSession` | Internal: the macros of one input looked for, expanded and counted |
| `Application\Port\DiceAppearance` | The extension point that dresses a member's dice |
| `Application\DieAppearance`, `NoDiceAppearance` | An appearance, with its readable ink; plain dice |
| `Application\Contrast` | The contrast of two colours as WCAG 2 measures it, and the ink that reads on a face |
| `Application\Event\RollRecorded` | A replayable roll kept in the journal, by identifiers |
| `Application\Exception\UnknownRoll` | A replay of a roll the journal does not hold |
| `Application\Exception\InvalidMacro` | A macro whose name is not one, or whose level has no owner |
| `Domain\MacroLevel` | Site, table or member |
| `Domain\Model\DiceMacro` | A row of the macros |
| `Domain\Repository\DiceMacroRepository` | The macros' port |
| `Infrastructure\Doctrine\DoctrineDiceMacroRepository`, `Infrastructure\Memory\InMemoryDiceMacroRepository` | The macros in Doctrine, in memory |
| `Domain\Model\RecordedRoll` | A row of the journal |
| `Domain\Repository\RecordedRollRepository` | The journal's port |
| `Infrastructure\Doctrine\DoctrineRecordedRollRepository` | The journal in Doctrine |
| `Infrastructure\Memory\InMemoryRecordedRollRepository` | The journal in memory |
| `Infrastructure\Validator\DiceExpression`, `DiceExpressionValidator` | The constraint |
| `Infrastructure\Form\DiceExpressionType` | The field |
| `Infrastructure\Twig\DiceExtension` | The Twig filters and functions, and the view of a roll |
