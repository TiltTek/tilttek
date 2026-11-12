# Reference

## The generator

| Type | Role |
|---|---|
| `Generator` | The library in one object: checks a catalog when built, and draws from it. |
| `Draw` | A draw asked for: a count, uniqueness, a seed and the state the last draw left. |
| `DrawResult` | The results of a draw, the count asked for, whether it is short, and the state a generator with a memory is left in. |
| `Tables` | The random tables the library ships in `tables/`: their names, their languages, and the directory and files of each language. |
| `Limits` | The bounds of depth, picks, time, attempts, results and cells. |
| `Timer`, `SystemTimer` | The monotonic clock in milliseconds of the time limit. |

## Definitions (`Tilttek\Generator\Definition`)

| Type | Role |
|---|---|
| `Catalog` | The lists and the generators by name. |
| `EntryList` | A list: its name, its entries and its Markov chain, if it has one; whether an entry carries a tag. |
| `MarkovChain` | How a list invents words: its order, its shortest and longest word, and whether entries come out. |
| `Entry` | An entry: its text, weight, fields and tags; read like an array in a template. |
| `GeneratorDefinition` | A generator: its name, body, variables, language and state. |
| `StateValue` | One value of a state: its start template and its next template. |
| `Body` | What a generator returns; `TextBody`, `ObjectBody`, `GridBody` and `GraphBody`. |
| `Template` | A weighted template of a `TextBody`. |
| `Field` | What fills a field or a cell: `TemplateField` or `GenerateField`. |
| `MapLayout` | `square` or `hex`. |

## Loading (`Tilttek\Generator\Loading`)

| Type | Role |
|---|---|
| `Reader` | Decoded documents into a catalog, with the place of each error; `isName()`. |
| `FileLoader` | YAML and JSON files and directories into documents and a catalog. |

## Grammar (`Tilttek\Generator\Grammar`)

| Type | Role |
|---|---|
| `Grammar` | The rules of a language behind the filters. |
| `French`, `English`, `Spanish`, `German`, `Italian`, `Portuguese` | The built-in grammars. |
| `Grammars` | The grammars by locale or language, the built-in ones and an application's. |
| `ArticleKind` | `definite`, `indefinite`, `partitive`, `demonstrative`. |
| `Gender` | `m`, `f`, `n`. |

## Engine (`Tilttek\Generator\Engine`)

| Type | Role |
|---|---|
| `Problem` | One thing wrong with a catalog, and its template. |
| `Producer` | Internal: compiles the templates, checks them, and renders the results. |
| `Run` | Internal: the randomness, picks, time and languages of one result. |
| `MarkovModel` | Internal: what a Markov list learnt from its entries, and the words it writes. |
| `SandboxPolicy` | Internal: what a template may use. |
| `ReferenceCollector` | Internal: the names and the sandbox features a template uses. |
| `GeneratorExtension` | Internal: the functions and filters of the templates. |

## Exceptions (`Tilttek\Generator\Exception`)

`GeneratorException`, `InvalidDefinition`, `InvalidCatalog`, `UnknownGenerator`, `UnknownList`, `UnknownTag`,
`ExhaustedList`, `LimitExceeded`, `GenerationFailed`; see [Exceptions](exceptions.md).
