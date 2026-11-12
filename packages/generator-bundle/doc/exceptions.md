# Exceptions

The bundle's own exceptions are those of the API's access and of the keys of the states; the others are those of
`tilttek/generator`, which the commands write and the API answers.

| Exception | When | Answer of the API |
|---|---|---|
| `Application\Exception\AccessDenied` | A missing or unknown token (`tokenMissing`), or a generator not open to the client | 401, or 403 |
| `Application\Exception\InvalidDrawRequest` | A count, a uniqueness, a seed or a state it cannot read | 400 |
| `Application\Exception\InvalidStateKey` | A key of `MemoryTables` or of `--key` that is empty or longer than 200 characters | — |
| `Tilttek\Generator\Exception\UnknownGenerator` | A generator that does not exist | 404 |
| Any other `Tilttek\Generator\Exception\GeneratorException` | A draw that fails | 422 |

An invalid catalog — `InvalidDefinition` or `InvalidCatalog` — fails where the generator is first built: the first
template that calls `tilttek_draw`, the first request to the API, or the command, which lists its problems.
`tilttek:generator:validate` finds them before.
