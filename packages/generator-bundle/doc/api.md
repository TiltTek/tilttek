# Draw API

## The route

`GET <prefix>/{generator}` — `tilttek_generator_draw` — draws from a generator and answers its results in JSON, never
its lists:

```text
GET /api/generators/tavern?count=2&unique=1&seed=table-7
Authorization: Bearer <token>
```

```json
{"generator": "tavern", "results": [{"name": "…", "keeper": "…", "menu": ["…"]}, {"…": "…"}], "requested": 2, "short": false}
```

| Parameter | Meaning |
|---|---|
| `count` | A whole number from 1 to `api.max_count`; 1 by default. |
| `unique` | `1`, `true`, `0` or `false`; false by default. |
| `seed` | A text of 1 to 128 characters, in UTF-8, that makes the same draw come again. |
| `state` | For a table with a memory, the state its last answer gave, as `state[day]=3&state[season]=winter`: at most 32 names to texts of at most 1024 characters, in UTF-8. |

A table with a memory answers its new state with its results — `"state": {"day": "4", "season": "winter"}` — and the
client sends it back with its next draw. The API keeps no state: a site that wants the bundle to keep it calls
`MemoryTables` in its own controller, behind its own access rules ([Tables with a memory](memory.md)).

Answers carry `Cache-Control: no-store`. An error is a problem detail, `application/problem+json`, with `status` and
`detail`:

| Status | When |
|---|---|
| 404 | The API is disabled, or the generator does not exist. |
| 401 | No token, a token that is not `Bearer`, or an unknown token, for a generator that is not public. |
| 403 | A known client asks for a generator not open to it. |
| 400 | A count, a uniqueness, a seed or a state it cannot read, or a name, a seed or a state that is not UTF-8. |
| 429 | Past the rate limit, with `Retry-After` in seconds. |
| 422 | A draw that fails: a template that fails, a list that runs out, a limit. A client's draw tells the cause; a public draw answers `The draw failed.`, without the names of the lists and templates. |

## Access

The application chooses what it opens, to whom and under which limits:

- **nothing** while `api.enabled` is false;
- **the public generators** of `api.public` to anyone, without a token, counted by the caller's address against
  `api.rate_limiter`, or the bundle's own limit of `api.public_limit` — sixty draws a minute — when none is named;
- **each client's generators** to the client that sends its token as `Authorization: Bearer <token>`, counted by
  client against its own rate limiter. A request with a token is always a client's: an unknown token is refused even
  for a public generator.

Tokens are compared in constant time and never written in a log by the bundle. Behind a proxy, the address is the one
Symfony reads from `Request::getClientIp()`, which follows the application's `trusted_proxies`.

The route has no firewall of its own: an application that wants its own authentication puts the prefix behind its
firewall and keeps the API's access for what it adds.
