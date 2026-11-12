# Extension points

| Service | Use |
|---|---|
| `Tilttek\Generator\Generator` | The application's generator, public: inject it to draw in a controller, a message handler or a service. |
| `Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory` | Builds a generator from another catalog with the same limits, language, grammars and dice. |
| `Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog` | The catalog of the files, cached, and read again from the files by `fresh()`. |
| `Tilttek\GeneratorBundle\Application\ApiAccess` | Who may draw what from the API: define it again to build the clients from elsewhere than the configuration. |
| `Tilttek\Generator\Grammar\Grammars` | The grammars: define it again to add a language. |
| `Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter` | The port of a rate limit, implemented on Symfony's rate limiters. |
| `Tilttek\GeneratorBundle\Application\MemoryTables` | The tables with a memory, public: draw from the state of a key, read it, set it, forget it. |
| `Tilttek\GeneratorBundle\Application\Contract\StateStore` | The port of the store of the states, public: implemented on a PSR-6 pool and in memory; name the application's own service in `state.store`. |
| `Tilttek\GeneratorBundle\Application\Contract\StateLock` | The port of the lock of a state, implemented on Symfony's lock factory, which `state.lock` names; an application that holds its locks elsewhere passes its own to `MemoryTables`. |

## The dice

When `tilttek/dice-bundle` is installed, its `Tilttek\Dice\DiceRoller` service — with the application's limits, custom
dice and notations — rolls the `roll()` of the templates; otherwise a `DiceRoller` with the default limits does.

## Another language

Define `Grammars` again with a factory that adds the application's grammar to the built-in ones:

```php
$services->set(Grammars::class)->factory([AppGrammars::class, 'create']);
```
