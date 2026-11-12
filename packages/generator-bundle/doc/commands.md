# Commands

The commands are registered when `symfony/console` is installed.

## `tilttek:generator:validate`

Reads the lists and the generators from their files — not from the cache — and checks them as the generator does:
documents of the wrong shape, templates that do not compile or leave the sandbox, lists, tags and generators that do
not exist. It lists every problem with its template and fails, or says how many lists and generators are valid.

```text
$ bin/console tilttek:generator:validate
 [ERROR] 2 problems were found.
 * tavern:field:menu: the generator "tavern.dishes" does not exist
 * tavern.name:template:0: no entry of the list "tavern.animal" has the tag "colour"
```

Run it in the continuous integration of the application: a typing error in a generator then fails the build rather
than a page.

## `tilttek:generator:create <name>`

Writes a new generator and its first list in `<name>.yaml` of the first directory of the paths, and refuses a name that
is not one, a kind or a table it does not know, a file that exists, or paths without a directory.

| Option | Meaning |
|---|---|
| `--kind=text` | A text generator (the default). |
| `--kind=fields` | A structured result with two fields. |
| `--kind=map` | A square map of 4 by 4 cells. |
| `--kind=memory` | A table with a memory, whose state counts its draws. |
| `--locale=fr` | The language of the generator. |
| `--from=shop` | A copy of one of the library's random tables, its names `table.shop…` renamed after the new generator; the kind is then that of the table, and `--locale` chooses its language, `en` (by default) or `fr`. |

```text
bin/console tilttek:generator:create tavern.sign --kind=fields --locale=fr
bin/console tilttek:generator:create tavern.shop --from=shop
```

## `tilttek:generator:draw <name>`

Draws from a generator: a text by line, a structured result or a map in JSON. A draw that fails, an unknown generator
or a catalog that does not load is an error.

| Option | Meaning |
|---|---|
| `--count=5` | How many results, 1 by default. |
| `--unique` | Different results only; a warning says when fewer were found. |
| `--seed=x` | A seed that makes the same draw come again. |
| `--key=campaign-7` | A table with a memory goes on from the state kept under this key, and keeps the one it leaves; the state is written after the results. |
| `--reset` | With `--key`: forgets the state first, so that the table starts again. |

```text
$ bin/console tilttek:generator:draw weather --count=3 --key=campaign-7
Fair
Overcast
Sunny
 // State: {"cell":"13"}
```

A key without a store of the states — no `psr/cache` and the `cache` store — is an error.
