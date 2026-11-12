# Tables with a memory

A table with a memory is a generator of `tilttek/generator` with a `state`: each draw goes on from the state the last
one left. The library keeps nothing between draws; the bundle keeps the states, by generator and by key — a campaign, a
table, a person, as the application names them.

## Drawing

`MemoryTables` is a public service, registered when a store of the states exists:

```php
use Tilttek\GeneratorBundle\Application\MemoryTables;

final readonly class CampaignWeather
{
    public function __construct(private MemoryTables $tables)
    {
    }

    public function nextDays(string $campaign): array
    {
        return $this->tables->draw('weather', 'campaign-'.$campaign, count: 3)->results;
    }
}
```

| Method | What it does |
|---|---|
| `draw($generator, $key, $count, $unique, $seed)` | Draws from the state kept under the key — none at the first draw — and keeps the state the draw leaves. A generator without a state draws as usual and keeps nothing. |
| `state($generator, $key)` | The state kept under the key, or `null`. |
| `set($generator, $key, $state)` | Sets the state, read as `Draw` reads one: a new heading, a chaos factor raised by a scene. |
| `forget($generator, $key)` | Forgets the state: the next draw starts again. |

A key is a text of 1 to 200 characters, any character included; an empty or longer key is an `InvalidStateKey`.

## The lock

Without a lock, two draws on the same generator and key at the same moment start from the same state, and the last one
kept wins: the other day of weather, the other step of the journey, is lost. An application whose draws may cross — two
people at the same table, two workers — names a lock factory of `symfony/lock`:

```yaml
framework:
    lock: '%env(LOCK_DSN)%'

tilttek_generator:
    state:
        lock: lock.factory
```

`MemoryTables` then holds the lock of the generator and the key from the load of the state to the save of the new one:
the second draw waits for the first and goes on from the state it left. `set()` and `forget()` take the same lock. The
lock is named from a hash of the generator and the key, waited for, and released when the draw ends, by a result or by
an exception; a process that dies holding it keeps the others waiting 30 seconds at most. The lock's store must be
shared by the processes that draw — a flock store for one server, Redis, Valkey or a database for several — as Symfony's
documentation explains.

The lock is off by default, and `symfony/lock` is only suggested: an application that draws from one process at a time
needs neither. An application that holds its locks elsewhere implements the `StateLock` port and passes it to
`MemoryTables` as its third argument.

## The store

The states go through the `StateStore` port, chosen by `state.store`:

| Store | Where the states are |
|---|---|
| `cache` (default) | In the PSR-6 pool of `state.pool`, `cache.app` by default, under a key hashed from the generator and the key, without expiry. `cache.app` forgets the states when the cache is cleared: give the states a pool of their own on Doctrine DBAL or Valkey to keep them across deployments ([Configuration](configuration.md)). |
| `memory` | In memory, for tests: forgotten at the end of the process and when the kernel is reset. |
| a service id | The application's own `StateStore`: a table of its own on Doctrine, a column of a campaign entity, a remote service. |

A store of the application implements three methods:

```php
use Tilttek\GeneratorBundle\Application\Contract\StateStore;

final readonly class CampaignStates implements StateStore
{
    public function load(string $generator, string $key): ?array { /* … */ }

    public function save(string $generator, string $key, array $state): void { /* … */ }

    public function forget(string $generator, string $key): void { /* … */ }
}
```

## Through the API and the terminal

The draw API keeps no state: a table with a memory answers its new state with its results, and the client sends it
back as `state[name]=value` with its next draw ([Draw API](api.md)). The command takes `--key` to draw from a kept
state and `--reset` to start again ([Commands](commands.md)).
