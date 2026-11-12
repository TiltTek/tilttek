<!-- phil-dev source-sha256=2f15e952490c2558abf2eb423a415b696a7ec51ad4983a6abfbf6a4995ebd78c -->
# Points d'extension

| Service | Usage |
|---|---|
| `Tilttek\Generator\Generator` | Le générateur de l'application, public : injectez-le pour faire un tirage dans un contrôleur, un *handler* de messages ou un service. |
| `Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory` | Construit un générateur à partir d'un autre catalogue, avec les mêmes limites, la même langue, les mêmes grammaires et les mêmes dés. |
| `Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog` | Le catalogue des fichiers, mis en cache, et relu dans les fichiers par `fresh()`. |
| `Tilttek\GeneratorBundle\Application\ApiAccess` | Qui peut tirer quoi dans l'API : redéfinissez-le pour construire les clients ailleurs que dans la configuration. |
| `Tilttek\Generator\Grammar\Grammars` | Les grammaires : redéfinissez-le pour ajouter une langue. |
| `Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter` | Le port d'une limite de débit, implémenté sur les limiteurs de débit de Symfony. |
| `Tilttek\GeneratorBundle\Application\MemoryTables` | Les tables à mémoire, public : tirer à partir de l'état d'une clé, le lire, le fixer, l'oublier. |
| `Tilttek\GeneratorBundle\Application\Contract\StateStore` | Le port du stockage des états, public : implémenté sur un pool PSR-6 et en mémoire ; nommez le service propre à l'application dans `state.store`. |
| `Tilttek\GeneratorBundle\Application\Contract\StateLock` | Le port du verrou d'un état, implémenté sur la fabrique de verrous de Symfony que nomme `state.lock` ; une application qui garde ses verrous ailleurs passe le sien à `MemoryTables`. |

## Les dés

Quand `tilttek/dice-bundle` est installé, son service `Tilttek\Dice\DiceRoller` (avec les limites, les dés
personnalisés et les notations de l'application) lance les `roll()` des gabarits ; sinon, un `DiceRoller` avec les
limites par défaut s'en charge.

## Une autre langue

Redéfinissez `Grammars` avec une fabrique qui ajoute la grammaire de l'application aux grammaires intégrées :

```php
$services->set(Grammars::class)->factory([AppGrammars::class, 'create']);
```
