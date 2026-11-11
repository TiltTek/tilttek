<!-- phil-dev source-sha256=6cc674e9a7084a975283e5cb4c7abc08bcaf2ef89286abdb1dbcf8416be310b2 -->
# Points d'extension

| Point | Ce que fournit une application | Où |
|---|---|---|
| `Contract\Macros` | Les macros que nomme une entrée, `$name`, résolues en leur lot | [Macros](macros.md) |
| `Dialect\Dialect` | La notation d'une autre table, ajoutée à `Dialects` ou remplaçant un dialecte intégré | [Dialectes](dialects.md#ajouter-un-dialecte) |
| `Notation\Notation` | Les lettres d'une autre langue, ajoutées à `Notations` | [Langues](languages.md) |
| `Custom\DieDefinition` | Des dés dont les faces sont des symboles, déclarés dans `CustomDice` | [Dés personnalisés](custom-dice.md) |
| `Limits` | Les bornes contre les abus | [Limites](limits.md) |
| `Random\Randomizer` | Le hasard d'un lancer : un moteur sûr, un germe | [Lancer](rolling.md) |

Chaque point est passé à `DiceRoller` ou à l'appel qui l'utilise ; la bibliothèque ne garde rien entre deux appels.
