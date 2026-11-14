# Extension points

The `Generator` takes its collaborators in its constructor; each has a default and can be replaced.

| Port or collaborator | Default | Use |
|---|---|---|
| `Tilttek\Generator\Grammar\Grammar` | `French`, `English`, `Spanish`, `German`, `Italian`, `Portuguese` | The rules of a language behind the grammar filters: implement it to add a language, and give it in a `Grammars` by language or locale. See [Grammar](grammar.md#adding-a-language). |
| `Tilttek\Generator\Grammar\Grammars` | the built-in grammars | The grammars by locale or language: the application's are added to the built-in ones, and replace one under the same key. |
| `Tilttek\Generator\Timer` | `SystemTimer` | The monotonic clock of the time limit: a stepping clock in tests. |
| `Random\Randomizer` | a secure engine | The randomness of a draw without a seed: a scripted or seeded engine in tests. |
| `Tilttek\Dice\DiceRoller` | a `DiceRoller` with the default limits | Rolls the `roll()` of the templates, with the application's custom dice and notations. |
| `Tilttek\Generator\Loading\Reader`, `FileLoader` | — | Turn documents and files into a `Catalog`; an application that keeps its lists elsewhere builds the `Catalog` itself. |
| `TraceryImporter`, `PerchanceImporter` | — | Turn grammars and tables of other tools into documents; an importer of another format writes the same documents and lets `Reader` check them. See [Imports](imports.md). |
| The cache directory (`$cache`) | `false` | Where Twig writes the compiled templates, loaded from their files rather than by `eval()`. See [Drawing](drawing.md#limits). |
