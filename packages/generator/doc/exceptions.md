# Exceptions

Every exception of the library implements `Tilttek\Generator\Exception\GeneratorException`.

| Exception | When | Message |
|---|---|---|
| `InvalidDefinition` | A document, a list, an entry, a Markov chain, a generator, a state, a limit or a draw of the wrong shape; an unknown file or table; a language without grammar | `inn.yaml > lists > weapon > 2: an entry has a text, and its weight is a whole number` |
| `InvalidCatalog` | Templates that do not compile, leave the sandbox, or name lists, tags or generators that do not exist; `problems()` lists them | One `Problem` by line: `inn:template:0: the list "nope" does not exist` |
| `UnknownGenerator` | A generator asked for by name that the catalog does not have | `No generator is named "npc".` |
| `UnknownList` | A list named by a variable that the catalog does not have | `No list is named "weapon".` |
| `UnknownTag` | A filter on a tag that no entry of the list carries | `No entry of the list "weapon" has the tag "era".` |
| `ExhaustedList` | A single pick with no entry left, or no new word a Markov list could invent | `No entry of the list "weapon" is left to pick.`, `The list "name.elf" invented no word that fits in 50 attempts.` |
| `LimitExceeded` | A draw past one of the limits, or a value of a state too long to bring back | `The limit of 1000 picks per result is exceeded.`, `The limit of 1024 characters per state value is exceeded.` |
| `GenerationFailed` | A template that fails while it renders: an undefined variable or field, a dice expression that does not roll, an access the sandbox refuses | `The template "inn:field:name" failed: Variable "keeper" does not exist in "inn:field:name" at line 1.` |

A template is named by its generator and its place: `name:template:0`, `name:var:gender`, `name:field:keeper`,
`name:cell`, `name:state:weather:start`, `name:state:weather:next`. An exception of the library thrown inside a
template — `UnknownList`, `ExhaustedList`, `LimitExceeded` — reaches the caller as it is, not wrapped in Twig's errors.
